<?php

declare(strict_types=1);

namespace OCA\NoteHub\Listener;

use OCA\NoteHub\AppInfo\Application;
use OCA\NoteHub\Service\IndexService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\Files\Node;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event> */
class FileChangeListener implements IEventListener {
    private IndexService $indexService;
    private LoggerInterface $logger;

    public function __construct(IndexService $indexService, LoggerInterface $logger) {
        $this->indexService = $indexService;
        $this->logger = $logger;
    }

    public function handle(Event $event): void {
        try {
            if ($event instanceof NodeRenamedEvent) {
                $this->handleRenamed($event);
            } elseif ($event instanceof NodeDeletedEvent) {
                $this->handleDeleted($event->getNode());
            } elseif ($event instanceof NodeCreatedEvent || $event instanceof NodeWrittenEvent) {
                $this->handleCreateOrUpdate($event->getNode());
            }
        } catch (\Exception $e) {
            $this->logger->warning('NoteHub FileChangeListener: ' . $e->getMessage());
        }
    }

    private function handleCreateOrUpdate(Node $node): void {
        if (!($node instanceof File)) {
            return;
        }
        $info = $this->parseNodePath($node);
        if ($info === null) {
            return;
        }
        $this->indexService->syncFileNode($info['userId'], $node, $info['relativePath']);
    }

    private function handleDeleted(Node $node): void {
        $info = $this->parseNodePath($node);
        if ($info === null) {
            return;
        }
        $this->indexService->deleteFromIndex($info['userId'], $node->getId());
    }

    private function handleRenamed(NodeRenamedEvent $event): void {
        $source = $event->getSource();
        $target = $event->getTarget();

        $sourceInfo = $this->parseNodePath($source);
        $targetInfo = $this->parseNodePath($target);

        // Moved out of NoteHub → delete from index
        if ($sourceInfo !== null && $targetInfo === null) {
            $this->indexService->deleteFromIndex($sourceInfo['userId'], $source->getId());
            return;
        }

        // Moved into NoteHub or renamed within → sync
        if ($targetInfo !== null && $target instanceof File) {
            $this->indexService->syncFileNode($targetInfo['userId'], $target, $targetInfo['relativePath']);
        }
    }

    /**
     * Check if node is a .md file inside NoteHub/ folder.
     * Returns ['userId' => string, 'relativePath' => string] or null.
     */
    private function parseNodePath(Node $node): ?array {
        $name = $node->getName();
        if (!str_ends_with($name, '.md')) {
            return null;
        }

        $path = $node->getPath();
        $folder = Application::NOTES_FOLDER;

        // Path format: /{userId}/files/{NOTES_FOLDER}/[subdir/...]file.md
        if (!preg_match('#^/([^/]+)/files/' . preg_quote($folder, '#') . '/(.*)?$#', $path, $matches)) {
            return null;
        }

        $userId = $matches[1];
        $innerPath = $matches[2] ?? '';

        // relativePath = directory part only (without filename)
        $lastSlash = strrpos($innerPath, '/');
        $relativePath = ($lastSlash !== false) ? substr($innerPath, 0, $lastSlash) : '';

        return [
            'userId' => $userId,
            'relativePath' => $relativePath,
        ];
    }
}
