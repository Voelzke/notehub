<?php

declare(strict_types=1);

namespace OCA\NoteHub\Service;

use OCA\NoteHub\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class ShareService {
    private IShareManager $shareManager;
    private IRootFolder $rootFolder;
    private IUserManager $userManager;
    private IDBConnection $db;
    private IndexService $indexService;
    private NoteService $noteService;
    private LoggerInterface $logger;

    public function __construct(
        IShareManager $shareManager,
        IRootFolder $rootFolder,
        IUserManager $userManager,
        IDBConnection $db,
        IndexService $indexService,
        NoteService $noteService,
        LoggerInterface $logger
    ) {
        $this->shareManager = $shareManager;
        $this->rootFolder = $rootFolder;
        $this->userManager = $userManager;
        $this->db = $db;
        $this->indexService = $indexService;
        $this->noteService = $noteService;
        $this->logger = $logger;
    }

    /**
     * Get all shares for a specific note.
     */
    public function getSharesForNote(string $userId, int $fileId): array {
        $node = $this->getFileNode($userId, $fileId);
        if ($node === null) {
            return [];
        }

        try {
            $shares = $this->shareManager->getSharesBy($userId, IShare::TYPE_USER, $node, false, -1, 0);
        } catch (\Exception $e) {
            $this->logger->warning('NoteHub ShareService: getSharesForNote failed: ' . $e->getMessage());
            return [];
        }

        $result = [];
        foreach ($shares as $share) {
            $sharedWithUser = $this->userManager->get($share->getSharedWith());
            $result[] = [
                'id' => $share->getId(),
                'sharedWith' => $share->getSharedWith(),
                'sharedWithDisplayName' => $sharedWithUser ? $sharedWithUser->getDisplayName() : $share->getSharedWith(),
                'permissions' => $share->getPermissions(),
            ];
        }

        return $result;
    }

    /**
     * Create a share for a note with another user.
     */
    public function createShareForNote(string $userId, int $fileId, string $shareWith, int $permissions): array {
        $this->logger->info('NoteHub ShareService: createShareForNote called', [
            'userId' => $userId,
            'fileId' => $fileId,
            'shareWith' => $shareWith,
            'permissions' => $permissions,
        ]);

        $node = $this->getFileNode($userId, $fileId);
        if ($node === null) {
            throw new NotFoundException('Note not found');
        }

        $this->logger->info('NoteHub ShareService: file node resolved', [
            'path' => $node->getPath(),
            'name' => $node->getName(),
        ]);

        // Check for existing share with this user
        try {
            $existingShares = $this->shareManager->getSharesBy($userId, IShare::TYPE_USER, $node, false, -1, 0);
            foreach ($existingShares as $existing) {
                if ($existing->getSharedWith() === $shareWith) {
                    throw new \Exception('Bereits geteilt mit diesem Benutzer');
                }
            }
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Bereits geteilt mit diesem Benutzer') {
                throw $e;
            }
            // Other errors: log and continue
            $this->logger->warning('NoteHub ShareService: duplicate check failed: ' . $e->getMessage());
        }

        // File shares only allow READ (1) and UPDATE (2) — cap at 3
        $permissions = $permissions & 3;
        if ($permissions < 1) {
            $permissions = 1;
        }

        $share = $this->shareManager->newShare();
        $share->setNode($node);
        $share->setShareType(IShare::TYPE_USER);
        $share->setSharedWith($shareWith);
        $share->setPermissions($permissions);
        $share->setSharedBy($userId);

        try {
            $share = $this->shareManager->createShare($share);
        } catch (\Throwable $e) {
            $this->logger->error('NoteHub ShareService: createShare failed', [
                'exception' => $e,
                'message' => $e->getMessage(),
                'userId' => $userId,
                'fileId' => $fileId,
                'shareWith' => $shareWith,
            ]);
            throw $e;
        }

        // Update shared flag in index
        $this->indexService->updateSharedFlag($userId, $fileId, true);

        $sharedWithUser = $this->userManager->get($shareWith);

        return [
            'id' => $share->getId(),
            'sharedWith' => $share->getSharedWith(),
            'sharedWithDisplayName' => $sharedWithUser ? $sharedWithUser->getDisplayName() : $shareWith,
            'permissions' => $share->getPermissions(),
        ];
    }

    /**
     * Delete a share.
     */
    public function deleteShareForNote(string $userId, int $shareId): void {
        $share = $this->shareManager->getShareById('ocinternal:' . $shareId);

        // Security check: only the share owner can delete
        if ($share->getSharedBy() !== $userId) {
            throw new \Exception('Not authorized to delete this share');
        }

        $fileId = $share->getNodeId();
        $this->shareManager->deleteShare($share);

        // Check if note still has other shares
        $remainingShares = $this->getSharesForNote($userId, $fileId);
        if (empty($remainingShares)) {
            $this->indexService->updateSharedFlag($userId, $fileId, false);
        }
    }

    /**
     * Search for users to share with.
     */
    public function searchUsers(string $query, string $currentUserId): array {
        $this->logger->info('NoteHub ShareService: searchUsers called', [
            'query' => $query,
            'currentUserId' => $currentUserId,
        ]);
        $users = $this->userManager->searchDisplayName($query, 10);
        $this->logger->info('NoteHub ShareService: searchUsers found ' . count($users) . ' users');
        $result = [];

        foreach ($users as $user) {
            if ($user->getUID() === $currentUserId) {
                continue;
            }
            $result[] = [
                'id' => $user->getUID(),
                'displayName' => $user->getDisplayName(),
            ];
        }

        return $result;
    }

    /**
     * Get notes shared with the current user.
     */
    public function getSharedWithMe(string $userId): array {
        try {
            $shares = $this->shareManager->getSharedWith($userId, IShare::TYPE_USER);
        } catch (\Exception $e) {
            $this->logger->warning('NoteHub ShareService: getSharedWithMe failed: ' . $e->getMessage());
            return [];
        }

        $result = [];

        foreach ($shares as $share) {
            try {
                $node = $share->getNode();
                if (!($node instanceof File) || !str_ends_with($node->getName(), '.md')) {
                    continue;
                }

                // Check if file is in a NoteHub folder
                $path = $node->getPath();
                if (strpos($path, '/' . Application::NOTES_FOLDER . '/') === false) {
                    continue;
                }

                $raw = $node->getContent();
                $parsed = $this->noteService->parseFrontmatter($raw);
                $meta = $parsed['meta'];

                // Skip templates
                if (!empty($meta['template'])) {
                    continue;
                }

                $startVal = $meta['start'] ?? '';
                if ($startVal === '') {
                    $ctime = $node->getCreationTime();
                    $ts = ($ctime && $ctime > 0) ? $ctime : $node->getMTime();
                    $startVal = date('Y-m-d', $ts);
                }

                $sharedByUser = $this->userManager->get($share->getSharedBy());

                $result[] = [
                    'id' => $node->getId(),
                    'title' => substr($node->getName(), 0, -3),
                    'folder' => '',
                    'modified' => $node->getMTime(),
                    'type' => $meta['type'] ?? 'note',
                    'status' => $meta['status'] ?? '',
                    'due' => $meta['due'] ?? '',
                    'priority' => $meta['priority'] ?? 0,
                    'tags' => $meta['tags'] ?? [],
                    'remind' => $meta['remind'] ?? '',
                    'person' => $meta['person'] ?? '',
                    'start' => $startVal,
                    'reminded' => !empty($meta['reminded']),
                    'template' => false,
                    'template_name' => '',
                    'shared' => true,
                    'sharedBy' => $share->getSharedBy(),
                    'sharedByDisplayName' => $sharedByUser ? $sharedByUser->getDisplayName() : $share->getSharedBy(),
                    'permissions' => $share->getPermissions(),
                ];
            } catch (\Exception $e) {
                $this->logger->debug('NoteHub ShareService: skipping share: ' . $e->getMessage());
                continue;
            }
        }

        return $result;
    }

    /**
     * Toggle task status on a shared note.
     */
    public function toggleSharedTask(string $userId, int $fileId): array {
        $share = $this->findShareForUser($userId, $fileId);
        if ($share === null) {
            throw new NotFoundException('Shared note not found');
        }

        if (($share->getPermissions() & 2) === 0) {
            throw new \Exception('No write permission for this shared note');
        }

        $node = $share->getNode();
        if (!($node instanceof File)) {
            throw new NotFoundException('Shared note not found');
        }

        $parsed = $this->noteService->parseFrontmatter($node->getContent());
        $meta = $parsed['meta'];

        $meta['status'] = ($meta['status'] ?? '') === 'done' ? 'open' : 'done';
        if (($meta['type'] ?? 'note') !== 'task') {
            $meta['type'] = 'task';
        }

        $fileContent = $this->noteService->buildFileContent($meta, $parsed['content']);
        $node->putContent($fileContent);

        $startVal = $meta['start'] ?? '';
        if ($startVal === '') {
            $ctime = $node->getCreationTime();
            $ts = ($ctime && $ctime > 0) ? $ctime : $node->getMTime();
            $startVal = date('Y-m-d', $ts);
        }

        return [
            'id' => $node->getId(),
            'title' => substr($node->getName(), 0, -3),
            'content' => $parsed['content'],
            'folder' => '',
            'modified' => $node->getMTime(),
            'type' => $meta['type'],
            'status' => $meta['status'],
            'due' => $meta['due'] ?? '',
            'priority' => $meta['priority'] ?? 0,
            'tags' => $meta['tags'] ?? [],
            'remind' => $meta['remind'] ?? '',
            'person' => $meta['person'] ?? '',
            'start' => $startVal,
            'reminded' => !empty($meta['reminded']),
            'shared' => true,
            'permissions' => $share->getPermissions(),
        ];
    }

    /**
     * Find an incoming share for a specific file and user.
     */
    private function findShareForUser(string $userId, int $fileId): ?IShare {
        $shares = $this->shareManager->getSharedWith($userId, IShare::TYPE_USER);
        foreach ($shares as $share) {
            try {
                if ($share->getNodeId() === $fileId) {
                    return $share;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return null;
    }

    /**
     * Read a shared note's content via the share.
     */
    public function readSharedNote(string $userId, int $fileId): array {
        $share = $this->findShareForUser($userId, $fileId);
        if ($share === null) {
            throw new NotFoundException('Shared note not found');
        }

        $node = $share->getNode();
        if (!($node instanceof File)) {
            throw new NotFoundException('Shared note not found');
        }

        $parsed = $this->noteService->parseFrontmatter($node->getContent());
        $meta = $parsed['meta'];

        $startVal = $meta['start'] ?? '';
        if ($startVal === '') {
            $ctime = $node->getCreationTime();
            $ts = ($ctime && $ctime > 0) ? $ctime : $node->getMTime();
            $startVal = date('Y-m-d', $ts);
        }

        $sharedByUser = $this->userManager->get($share->getSharedBy());

        return [
            'id' => $node->getId(),
            'title' => substr($node->getName(), 0, -3),
            'content' => $parsed['content'],
            'folder' => '',
            'modified' => $node->getMTime(),
            'type' => $meta['type'] ?? 'note',
            'status' => $meta['status'] ?? '',
            'due' => $meta['due'] ?? '',
            'priority' => $meta['priority'] ?? 0,
            'tags' => $meta['tags'] ?? [],
            'remind' => $meta['remind'] ?? '',
            'person' => $meta['person'] ?? '',
            'start' => $startVal,
            'reminded' => !empty($meta['reminded']),
            'shared' => true,
            'permissions' => $share->getPermissions(),
            'sharedBy' => $share->getSharedBy(),
            'sharedByDisplayName' => $sharedByUser ? $sharedByUser->getDisplayName() : $share->getSharedBy(),
        ];
    }

    /**
     * Update a shared note's content (requires write permission).
     */
    public function updateSharedNote(
        string $userId,
        int $fileId,
        string $title,
        string $content,
        ?string $type = null,
        ?string $status = null,
        ?string $due = null,
        ?int $priority = null,
        ?array $tags = null,
        ?string $remind = null,
        ?string $person = null
    ): array {
        $share = $this->findShareForUser($userId, $fileId);
        if ($share === null) {
            throw new NotFoundException('Shared note not found');
        }

        // Check write permission (UPDATE bit = 2)
        if (($share->getPermissions() & 2) === 0) {
            throw new \Exception('No write permission for this shared note');
        }

        $node = $share->getNode();
        if (!($node instanceof File)) {
            throw new NotFoundException('Shared note not found');
        }

        $parsed = $this->noteService->parseFrontmatter($node->getContent());
        $meta = $parsed['meta'];

        if ($type !== null) {
            $meta['type'] = $type;
        }
        if ($status !== null) {
            $meta['status'] = $status;
        }
        if ($due !== null) {
            $meta['due'] = $due;
        }
        if ($priority !== null) {
            $meta['priority'] = $priority;
        }
        if ($tags !== null) {
            $meta['tags'] = $tags;
        }
        if ($remind !== null) {
            $meta['remind'] = $remind;
        }
        if ($person !== null) {
            $meta['person'] = $person;
        }

        $fileContent = $this->noteService->buildFileContent($meta, $content);
        $node->putContent($fileContent);

        return [
            'id' => $node->getId(),
            'title' => substr($node->getName(), 0, -3),
            'content' => $content,
            'folder' => '',
            'modified' => $node->getMTime(),
            'type' => $meta['type'] ?? 'note',
            'status' => $meta['status'] ?? '',
            'due' => $meta['due'] ?? '',
            'priority' => $meta['priority'] ?? 0,
            'tags' => $meta['tags'] ?? [],
            'remind' => $meta['remind'] ?? '',
            'person' => $meta['person'] ?? '',
            'start' => $meta['start'] ?? '',
            'reminded' => !empty($meta['reminded']),
            'shared' => true,
            'permissions' => $share->getPermissions(),
        ];
    }

    /**
     * Get file node by file ID from user's folder.
     */
    private function getFileNode(string $userId, int $fileId): ?File {
        try {
            $userFolder = $this->rootFolder->getUserFolder($userId);
            $notesFolder = $userFolder->get(Application::NOTES_FOLDER);
            $nodes = $notesFolder->getById($fileId);

            if (!empty($nodes) && $nodes[0] instanceof File) {
                return $nodes[0];
            }
        } catch (\Throwable $e) {
            $this->logger->warning('NoteHub ShareService: getFileNode failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Simple frontmatter parser (same logic as NoteService).
     */
    private function parseFrontmatter(string $rawContent): array {
        $meta = ['type' => 'note'];

        if (!str_starts_with($rawContent, "---\n")) {
            return $meta;
        }

        $end = strpos($rawContent, "\n---\n", 4);
        if ($end === false) {
            $end = strpos($rawContent, "\n---", 4);
            if ($end === false || $end + 4 < strlen($rawContent)) {
                return $meta;
            }
        }

        $yamlBlock = substr($rawContent, 4, $end - 4);
        $lines = explode("\n", $yamlBlock);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, ':') === false) {
                continue;
            }

            $colonPos = strpos($line, ':');
            $key = trim(substr($line, 0, $colonPos));
            $value = trim(substr($line, $colonPos + 1));

            switch ($key) {
                case 'type':
                case 'status':
                case 'start':
                case 'due':
                    $meta[$key] = $value;
                    break;
                case 'priority':
                    $meta[$key] = (int)$value;
                    break;
                case 'tags':
                    if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                        $inner = substr($value, 1, -1);
                        $meta[$key] = array_map('trim', explode(',', $inner));
                    } else {
                        $meta[$key] = $value !== '' ? array_map('trim', explode(',', $value)) : [];
                    }
                    break;
            }
        }

        return $meta;
    }
}
