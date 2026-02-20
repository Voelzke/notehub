<?php

declare(strict_types=1);

namespace OCA\NoteHub\Service;

use OCA\NoteHub\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Files\File;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;

class NoteService {
    private IRootFolder $rootFolder;
    private IDBConnection $db;
    private IndexService $indexService;

    private const TABLE = 'notehub_notes';

    public function __construct(
        IRootFolder $rootFolder,
        IDBConnection $db,
        IndexService $indexService
    ) {
        $this->rootFolder = $rootFolder;
        $this->db = $db;
        $this->indexService = $indexService;
    }

    private function getNotesFolder(string $userId): Folder {
        $userFolder = $this->rootFolder->getUserFolder($userId);

        try {
            $folder = $userFolder->get(Application::NOTES_FOLDER);
            if ($folder instanceof Folder) {
                return $folder;
            }
        } catch (NotFoundException $e) {
        }

        return $userFolder->newFolder(Application::NOTES_FOLDER);
    }

    // ── Frontmatter helpers ─────────────────────────────────

    public function parseFrontmatter(string $rawContent): array {
        $meta = ['type' => 'note'];
        $body = $rawContent;

        if (!str_starts_with($rawContent, "---\n")) {
            return ['meta' => $meta, 'content' => $body];
        }

        $end = strpos($rawContent, "\n---\n", 4);
        if ($end === false) {
            // check for --- at very end of file
            $end = strpos($rawContent, "\n---", 4);
            if ($end === false || $end + 4 < strlen($rawContent)) {
                return ['meta' => $meta, 'content' => $body];
            }
            $body = ltrim(substr($rawContent, $end + 4));
        } else {
            $body = ltrim(substr($rawContent, $end + 5));
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
                case 'remind':
                case 'person':
                    $meta[$key] = $value;
                    break;
                case 'priority':
                    $meta[$key] = (int)$value;
                    break;
                case 'reminded':
                    $meta[$key] = ($value === 'true' || $value === '1');
                    break;
                case 'tags':
                    // parse [tag1, tag2] format
                    if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                        $inner = substr($value, 1, -1);
                        $meta[$key] = array_map('trim', explode(',', $inner));
                    } else {
                        $meta[$key] = $value !== '' ? array_map('trim', explode(',', $value)) : [];
                    }
                    break;
                case 'template':
                    $meta[$key] = ($value === 'true' || $value === '1');
                    break;
                case 'template_name':
                    $meta[$key] = $value;
                    break;
            }
        }

        return ['meta' => $meta, 'content' => $body];
    }

    public function buildFileContent(array $meta, string $body): string {
        $type = $meta['type'] ?? 'note';

        // Templates always need frontmatter
        $isTemplate = !empty($meta['template']);

        // If it's just a plain note with no task fields and not a template, skip frontmatter
        if ($type === 'note' && !$isTemplate) {
            $hasTaskFields = false;
            foreach (['status', 'due', 'priority', 'tags', 'remind', 'person', 'start', 'reminded'] as $field) {
                if (isset($meta[$field]) && $meta[$field] !== '' && $meta[$field] !== [] && $meta[$field] !== 0) {
                    $hasTaskFields = true;
                    break;
                }
            }
            if (!$hasTaskFields) {
                return $body;
            }
        }

        $lines = ['---'];
        $lines[] = 'type: ' . ($meta['type'] ?? 'note');

        if (isset($meta['status']) && $meta['status'] !== '') {
            $lines[] = 'status: ' . $meta['status'];
        }
        if (isset($meta['due']) && $meta['due'] !== '') {
            $lines[] = 'due: ' . $meta['due'];
        }
        if (isset($meta['priority']) && $meta['priority'] > 0) {
            $lines[] = 'priority: ' . $meta['priority'];
        }
        if (isset($meta['tags']) && !empty($meta['tags'])) {
            $tags = is_array($meta['tags']) ? $meta['tags'] : [$meta['tags']];
            $lines[] = 'tags: [' . implode(', ', $tags) . ']';
        }
        if (isset($meta['remind']) && $meta['remind'] !== '') {
            $lines[] = 'remind: ' . $meta['remind'];
        }
        if (isset($meta['person']) && $meta['person'] !== '') {
            $lines[] = 'person: ' . $meta['person'];
        }
        if (isset($meta['start']) && $meta['start'] !== '') {
            $lines[] = 'start: ' . $meta['start'];
        }
        if (!empty($meta['reminded'])) {
            $lines[] = 'reminded: true';
        }
        if (!empty($meta['template'])) {
            $lines[] = 'template: true';
        }
        if (isset($meta['template_name']) && $meta['template_name'] !== '') {
            $lines[] = 'template_name: ' . $meta['template_name'];
        }

        $lines[] = '---';
        $lines[] = '';

        return implode("\n", $lines) . $body;
    }

    // ── DB-backed list queries ──────────────────────────────

    /**
     * Convert a DB row to the note array format used by the API.
     */
    private function dbRowToNote(array $row): array {
        $tags = $row['tags'] ?? '[]';
        if (is_string($tags)) {
            $tags = json_decode($tags, true) ?: [];
        }

        return [
            'id' => (int)$row['file_id'],
            'title' => $row['title'] ?? '',
            'folder' => $row['path'] ?? '',
            'modified' => (int)($row['modified'] ?? 0),
            'type' => $row['type'] ?? 'note',
            'status' => $row['status'] ?? '',
            'due' => $row['due'] ?? '',
            'priority' => (int)($row['priority'] ?? 0),
            'tags' => $tags,
            'remind' => $row['remind'] ?? '',
            'person' => $row['person'] ?? '',
            'start' => $row['start'] ?? '',
            'reminded' => !empty($row['reminded']),
            'template' => !empty($row['template']),
            'template_name' => $row['template_name'] ?? '',
            'shared' => !empty($row['shared']),
        ];
    }

    /**
     * @return array<int, array{id: int, title: string, folder: string, modified: int, type: string, status: string}>
     */
    public function findAll(string $userId, ?string $tag = null): array {
        $this->indexService->ensureSync($userId);

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('template', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));

        $result = $qb->executeQuery();
        $notes = [];
        while ($row = $result->fetch()) {
            $notes[] = $this->dbRowToNote($row);
        }
        $result->closeCursor();

        // Filter by tag if provided
        if ($tag !== null && $tag !== '') {
            $notes = array_values(array_filter($notes, function ($note) use ($tag) {
                return in_array($tag, $note['tags'] ?? [], true);
            }));
        }

        // Sort: open overdue tasks -> open tasks -> normal notes -> done tasks
        $today = date('Y-m-d');
        usort($notes, function ($a, $b) use ($today) {
            $orderA = $this->sortOrder($a, $today);
            $orderB = $this->sortOrder($b, $today);
            if ($orderA !== $orderB) {
                return $orderA - $orderB;
            }
            return $b['modified'] - $a['modified'];
        });

        return $notes;
    }

    public function getTags(string $userId): array {
        $this->indexService->ensureSync($userId);

        $qb = $this->db->getQueryBuilder();
        $qb->select('tags')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('template', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));

        $result = $qb->executeQuery();
        $tagCounts = [];
        while ($row = $result->fetch()) {
            $tags = json_decode($row['tags'] ?? '[]', true) ?: [];
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if ($tag === '') continue;
                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
            }
        }
        $result->closeCursor();

        ksort($tagCounts);

        $resultArr = [];
        foreach ($tagCounts as $name => $count) {
            $resultArr[] = ['name' => $name, 'count' => $count];
        }

        return $resultArr;
    }

    public function getTitles(string $userId): array {
        $this->indexService->ensureSync($userId);

        $qb = $this->db->getQueryBuilder();
        $qb->select('file_id', 'title')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('template', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));

        $result = $qb->executeQuery();
        $titles = [];
        while ($row = $result->fetch()) {
            $titles[] = ['id' => (int)$row['file_id'], 'title' => $row['title']];
        }
        $result->closeCursor();

        return $titles;
    }

    public function getBacklinks(int $id, string $userId): array {
        $note = $this->find($id, $userId);
        $title = $note['title'];
        $folder = $this->getNotesFolder($userId);
        return $this->searchBacklinks($folder, '', $title, $id);
    }

    private function searchBacklinks(Folder $folder, string $path, string $title, int $excludeId): array {
        $results = [];
        $pattern = '[[' . $title . ']]';

        foreach ($folder->getDirectoryListing() as $node) {
            if ($node instanceof File && str_ends_with($node->getName(), '.md')) {
                if ($node->getId() === $excludeId) {
                    continue;
                }

                $raw = $node->getContent();
                $parsed = $this->parseFrontmatter($raw);
                $body = $parsed['content'];
                $lines = explode("\n", $body);

                foreach ($lines as $lineIdx => $line) {
                    if (mb_stripos($line, $pattern) !== false) {
                        $context = mb_strlen($line) > 150 ? mb_substr($line, 0, 150) . '...' : $line;
                        $results[] = [
                            'noteId' => $node->getId(),
                            'title' => substr($node->getName(), 0, -3),
                            'line' => $lineIdx + 1,
                            'context' => $context,
                        ];
                    }
                }
            } elseif ($node instanceof Folder) {
                $subPath = $path ? $path . '/' . $node->getName() : $node->getName();
                $results = array_merge($results, $this->searchBacklinks($node, $subPath, $title, $excludeId));
            }
        }

        return $results;
    }

    private function sortOrder(array $note, string $today): int {
        $type = $note['type'] ?? 'note';
        $status = $note['status'] ?? '';

        if ($type === 'task' && $status === 'done') {
            return 3; // done tasks last
        }
        if ($type === 'task' && $status === 'open') {
            $due = $note['due'] ?? '';
            if ($due !== '' && $due < $today) {
                return 0; // overdue first
            }
            return 1; // open tasks
        }
        return 2; // normal notes
    }

    // ── Single-note read (unchanged — reads file content) ───

    public function find(int $id, string $userId): array {
        $folder = $this->getNotesFolder($userId);
        $nodes = $folder->getById($id);

        if (empty($nodes)) {
            throw new NotFoundException('Note not found');
        }

        $file = $nodes[0];
        if (!($file instanceof File)) {
            throw new NotFoundException('Note not found');
        }

        $parsed = $this->parseFrontmatter($file->getContent());
        $meta = $parsed['meta'];

        $startVal = $meta['start'] ?? '';
        if ($startVal === '') {
            $ctime = $file->getCreationTime();
            $ts = ($ctime && $ctime > 0) ? $ctime : $file->getMTime();
            $startVal = date('Y-m-d', $ts);
        }

        return [
            'id' => $file->getId(),
            'title' => substr($file->getName(), 0, -3),
            'content' => $parsed['content'],
            'folder' => $this->getRelativePath($folder, $file),
            'modified' => $file->getMTime(),
            'type' => $meta['type'] ?? 'note',
            'status' => $meta['status'] ?? '',
            'due' => $meta['due'] ?? '',
            'priority' => $meta['priority'] ?? 0,
            'tags' => $meta['tags'] ?? [],
            'remind' => $meta['remind'] ?? '',
            'person' => $meta['person'] ?? '',
            'start' => $startVal,
            'reminded' => !empty($meta['reminded']),
            'template' => !empty($meta['template']),
            'template_name' => $meta['template_name'] ?? '',
        ];
    }

    // ── CRUD (with index sync) ──────────────────────────────

    public function create(string $title, string $content, string $folderPath, string $userId, string $type = 'note'): array {
        $notesFolder = $this->getNotesFolder($userId);
        $targetFolder = $this->getOrCreateSubfolder($notesFolder, $folderPath);

        $filename = $this->sanitizeFilename($title) . '.md';
        $filename = $this->getUniqueFilename($targetFolder, $filename);

        $meta = ['type' => $type];
        if ($type === 'task') {
            $meta['status'] = 'open';
        }

        if (empty($content)) {
            $content = '# ' . $title . "\n";
        }

        $fileContent = $this->buildFileContent($meta, $content);
        $file = $targetFolder->newFile($filename, $fileContent);

        $result = [
            'id' => $file->getId(),
            'title' => substr($file->getName(), 0, -3),
            'content' => $content,
            'folder' => $folderPath,
            'modified' => $file->getMTime(),
            'type' => $meta['type'],
            'status' => $meta['status'] ?? '',
            'due' => '',
            'priority' => 0,
            'tags' => [],
            'remind' => '',
            'person' => '',
            'start' => date('Y-m-d'),
            'reminded' => false,
        ];

        $this->indexService->syncSingle($userId, $file->getId(), $result);

        return $result;
    }

    public function update(
        int $id,
        string $title,
        string $content,
        string $folderPath,
        string $userId,
        ?string $type = null,
        ?string $status = null,
        ?string $due = null,
        ?int $priority = null,
        ?array $tags = null,
        ?string $remind = null,
        ?string $person = null
    ): array {
        $notesFolder = $this->getNotesFolder($userId);
        $nodes = $notesFolder->getById($id);

        if (empty($nodes)) {
            throw new NotFoundException('Note not found');
        }

        $file = $nodes[0];
        if (!($file instanceof File)) {
            throw new NotFoundException('Note not found');
        }

        // Read existing frontmatter to merge with updates
        $parsed = $this->parseFrontmatter($file->getContent());
        $meta = $parsed['meta'];

        // Apply provided meta fields (only override if explicitly passed)
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
            // Reset reminded when remind date changes
            $oldRemind = $meta['remind'] ?? '';
            $meta['remind'] = $remind;
            if ($remind !== $oldRemind) {
                $meta['reminded'] = false;
            }
        }
        if ($person !== null) {
            $meta['person'] = $person;
        }

        $fileContent = $this->buildFileContent($meta, $content);
        $file->putContent($fileContent);

        $currentTitle = substr($file->getName(), 0, -3);
        if ($currentTitle !== $title) {
            $newFilename = $this->sanitizeFilename($title) . '.md';
            $file->move($file->getParent()->getPath() . '/' . $newFilename);
        }

        $result = [
            'id' => $file->getId(),
            'title' => $title,
            'content' => $content,
            'folder' => $folderPath,
            'modified' => $file->getMTime(),
            'type' => $meta['type'] ?? 'note',
            'status' => $meta['status'] ?? '',
            'due' => $meta['due'] ?? '',
            'priority' => $meta['priority'] ?? 0,
            'tags' => $meta['tags'] ?? [],
            'remind' => $meta['remind'] ?? '',
            'person' => $meta['person'] ?? '',
            'start' => $meta['start'] ?? '',
            'reminded' => !empty($meta['reminded']),
            'template' => !empty($meta['template']),
            'template_name' => $meta['template_name'] ?? '',
        ];

        $this->indexService->syncSingle($userId, $file->getId(), $result);

        return $result;
    }

    public function toggleTask(int $id, string $userId): array {
        $folder = $this->getNotesFolder($userId);
        $nodes = $folder->getById($id);

        if (empty($nodes)) {
            throw new NotFoundException('Note not found');
        }

        $file = $nodes[0];
        if (!($file instanceof File)) {
            throw new NotFoundException('Note not found');
        }

        $parsed = $this->parseFrontmatter($file->getContent());
        $meta = $parsed['meta'];

        // Toggle status
        $meta['status'] = ($meta['status'] ?? '') === 'done' ? 'open' : 'done';

        // Ensure type is task
        if (($meta['type'] ?? 'note') !== 'task') {
            $meta['type'] = 'task';
        }

        $fileContent = $this->buildFileContent($meta, $parsed['content']);
        $file->putContent($fileContent);

        $result = [
            'id' => $file->getId(),
            'title' => substr($file->getName(), 0, -3),
            'content' => $parsed['content'],
            'folder' => $this->getRelativePath($folder, $file),
            'modified' => $file->getMTime(),
            'type' => $meta['type'],
            'status' => $meta['status'],
            'due' => $meta['due'] ?? '',
            'priority' => $meta['priority'] ?? 0,
            'tags' => $meta['tags'] ?? [],
            'remind' => $meta['remind'] ?? '',
            'person' => $meta['person'] ?? '',
            'start' => $meta['start'] ?? '',
            'reminded' => !empty($meta['reminded']),
        ];

        $this->indexService->syncSingle($userId, $file->getId(), $result);

        return $result;
    }

    public function setTask(int $id, string $userId): array {
        $folder = $this->getNotesFolder($userId);
        $nodes = $folder->getById($id);

        if (empty($nodes)) {
            throw new NotFoundException('Note not found');
        }

        $file = $nodes[0];
        if (!($file instanceof File)) {
            throw new NotFoundException('Note not found');
        }

        $parsed = $this->parseFrontmatter($file->getContent());
        $meta = $parsed['meta'];

        // Set as task if not already
        if (($meta['type'] ?? 'note') !== 'task') {
            $meta['type'] = 'task';
            $meta['status'] = 'open';
        }

        $fileContent = $this->buildFileContent($meta, $parsed['content']);
        $file->putContent($fileContent);

        $result = [
            'id' => $file->getId(),
            'title' => substr($file->getName(), 0, -3),
            'content' => $parsed['content'],
            'folder' => $this->getRelativePath($folder, $file),
            'modified' => $file->getMTime(),
            'type' => $meta['type'],
            'status' => $meta['status'] ?? 'open',
            'due' => $meta['due'] ?? '',
            'priority' => $meta['priority'] ?? 0,
            'tags' => $meta['tags'] ?? [],
            'remind' => $meta['remind'] ?? '',
            'person' => $meta['person'] ?? '',
            'start' => $meta['start'] ?? '',
            'reminded' => !empty($meta['reminded']),
        ];

        $this->indexService->syncSingle($userId, $file->getId(), $result);

        return $result;
    }

    public function unsetTask(int $id, string $userId): array {
        $folder = $this->getNotesFolder($userId);
        $nodes = $folder->getById($id);

        if (empty($nodes)) {
            throw new NotFoundException('Note not found');
        }

        $file = $nodes[0];
        if (!($file instanceof File)) {
            throw new NotFoundException('Note not found');
        }

        $parsed = $this->parseFrontmatter($file->getContent());
        $meta = $parsed['meta'];

        $meta['type'] = 'note';
        $meta['status'] = '';

        $fileContent = $this->buildFileContent($meta, $parsed['content']);
        $file->putContent($fileContent);

        $result = [
            'id' => $file->getId(),
            'title' => substr($file->getName(), 0, -3),
            'content' => $parsed['content'],
            'folder' => $this->getRelativePath($folder, $file),
            'modified' => $file->getMTime(),
            'type' => $meta['type'],
            'status' => $meta['status'],
            'due' => $meta['due'] ?? '',
            'priority' => $meta['priority'] ?? 0,
            'tags' => $meta['tags'] ?? [],
            'remind' => $meta['remind'] ?? '',
            'person' => $meta['person'] ?? '',
            'start' => $meta['start'] ?? '',
            'reminded' => !empty($meta['reminded']),
        ];

        $this->indexService->syncSingle($userId, $file->getId(), $result);

        return $result;
    }

    /**
     * Find all tasks with pending reminders (remind <= now, status=open, not yet reminded).
     */
    public function findPendingReminders(string $userId): array {
        $this->indexService->ensureSync($userId);

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('type', $qb->createNamedParameter('task')))
            ->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('open')))
            ->andWhere($qb->expr()->eq('template', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->andWhere($qb->expr()->eq('reminded', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->andWhere($qb->expr()->neq('remind', $qb->createNamedParameter('')));

        $result = $qb->executeQuery();
        $now = date('Y-m-d H:i');
        $pending = [];

        while ($row = $result->fetch()) {
            $remind = str_replace('T', ' ', $row['remind'] ?? '');
            if (strlen($remind) === 10) {
                $remind .= ' 00:00';
            }
            if ($remind <= $now) {
                $pending[] = $this->dbRowToNote($row);
            }
        }
        $result->closeCursor();

        return $pending;
    }

    /**
     * Mark a note as reminded by setting reminded: true in frontmatter.
     */
    public function markReminded(int $id, string $userId): void {
        $notesFolder = $this->getNotesFolder($userId);
        $nodes = $notesFolder->getById($id);

        if (empty($nodes)) return;

        $file = $nodes[0];
        if (!($file instanceof File)) return;

        $parsed = $this->parseFrontmatter($file->getContent());
        $meta = $parsed['meta'];
        $meta['reminded'] = true;

        $fileContent = $this->buildFileContent($meta, $parsed['content']);
        $file->putContent($fileContent);

        // Update index
        $this->indexService->syncSingle($userId, $file->getId(), [
            'id' => $file->getId(),
            'title' => substr($file->getName(), 0, -3),
            'folder' => $this->getRelativePath($notesFolder, $file),
            'modified' => $file->getMTime(),
            'type' => $meta['type'] ?? 'note',
            'status' => $meta['status'] ?? '',
            'due' => $meta['due'] ?? '',
            'priority' => $meta['priority'] ?? 0,
            'tags' => $meta['tags'] ?? [],
            'remind' => $meta['remind'] ?? '',
            'person' => $meta['person'] ?? '',
            'start' => $meta['start'] ?? '',
            'reminded' => true,
            'template' => !empty($meta['template']),
            'template_name' => $meta['template_name'] ?? '',
        ]);
    }

    // ── Templates ─────────────────────────────────────────

    public function getTemplates(string $userId): array {
        $this->indexService->ensureSync($userId);

        $qb = $this->db->getQueryBuilder();
        $qb->select('file_id', 'template_name', 'title')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('template', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));

        $result = $qb->executeQuery();
        $templates = [];
        while ($row = $result->fetch()) {
            $tplName = $row['template_name'] ?: $row['title'];
            $templates[] = [
                'id' => (int)$row['file_id'],
                'template_name' => $tplName,
            ];
        }
        $result->closeCursor();

        usort($templates, fn($a, $b) => strcasecmp($a['template_name'], $b['template_name']));

        return $templates;
    }

    public function createFromTemplate(int $templateId, string $userId, string $type = 'note'): array {
        $folder = $this->getNotesFolder($userId);
        $nodes = $folder->getById($templateId);

        if (empty($nodes)) {
            throw new NotFoundException('Template not found');
        }

        $file = $nodes[0];
        if (!($file instanceof File)) {
            throw new NotFoundException('Template not found');
        }

        $parsed = $this->parseFrontmatter($file->getContent());
        $templateMeta = $parsed['meta'];
        $body = $parsed['content'];

        // Replace placeholders
        $now = new \DateTime();
        $body = str_replace('{{date}}', $now->format('Y-m-d'), $body);
        $body = str_replace('{{time}}', $now->format('H:i'), $body);
        $body = str_replace('{{datetime}}', $now->format('Y-m-d H:i'), $body);

        // Title = template_name + date
        $templateName = $templateMeta['template_name'] ?? substr($file->getName(), 0, -3);
        $title = $templateName . ' ' . $now->format('Y-m-d');

        // Build meta for new note (copy tags from template, but not template fields)
        $meta = ['type' => $type];
        if ($type === 'task') {
            $meta['status'] = 'open';
        }
        if (!empty($templateMeta['tags'])) {
            $meta['tags'] = $templateMeta['tags'];
        }

        $fileContent = $this->buildFileContent($meta, $body);
        $filename = $this->sanitizeFilename($title) . '.md';
        $filename = $this->getUniqueFilename($folder, $filename);
        $newFile = $folder->newFile($filename, $fileContent);

        $result = [
            'id' => $newFile->getId(),
            'title' => substr($newFile->getName(), 0, -3),
            'content' => $body,
            'folder' => '',
            'modified' => $newFile->getMTime(),
            'type' => $type,
            'status' => $meta['status'] ?? '',
            'due' => '',
            'priority' => 0,
            'tags' => $meta['tags'] ?? [],
            'remind' => '',
            'person' => '',
            'start' => date('Y-m-d'),
            'reminded' => false,
            'template' => false,
            'template_name' => '',
        ];

        $this->indexService->syncSingle($userId, $newFile->getId(), $result);

        return $result;
    }

    public function setTemplate(int $id, string $templateName, string $userId): array {
        $folder = $this->getNotesFolder($userId);
        $nodes = $folder->getById($id);

        if (empty($nodes)) {
            throw new NotFoundException('Note not found');
        }

        $file = $nodes[0];
        if (!($file instanceof File)) {
            throw new NotFoundException('Note not found');
        }

        $parsed = $this->parseFrontmatter($file->getContent());
        $meta = $parsed['meta'];

        $meta['template'] = true;
        $meta['template_name'] = $templateName ?: substr($file->getName(), 0, -3);

        $fileContent = $this->buildFileContent($meta, $parsed['content']);
        $file->putContent($fileContent);

        $result = [
            'id' => $file->getId(),
            'title' => substr($file->getName(), 0, -3),
            'content' => $parsed['content'],
            'folder' => $this->getRelativePath($folder, $file),
            'modified' => $file->getMTime(),
            'type' => $meta['type'] ?? 'note',
            'status' => $meta['status'] ?? '',
            'due' => $meta['due'] ?? '',
            'priority' => $meta['priority'] ?? 0,
            'tags' => $meta['tags'] ?? [],
            'remind' => $meta['remind'] ?? '',
            'person' => $meta['person'] ?? '',
            'start' => $meta['start'] ?? '',
            'reminded' => !empty($meta['reminded']),
            'template' => true,
            'template_name' => $meta['template_name'],
        ];

        $this->indexService->syncSingle($userId, $file->getId(), $result);

        return $result;
    }

    public function unsetTemplate(int $id, string $userId): array {
        $folder = $this->getNotesFolder($userId);
        $nodes = $folder->getById($id);

        if (empty($nodes)) {
            throw new NotFoundException('Note not found');
        }

        $file = $nodes[0];
        if (!($file instanceof File)) {
            throw new NotFoundException('Note not found');
        }

        $parsed = $this->parseFrontmatter($file->getContent());
        $meta = $parsed['meta'];

        unset($meta['template']);
        unset($meta['template_name']);

        $fileContent = $this->buildFileContent($meta, $parsed['content']);
        $file->putContent($fileContent);

        $result = [
            'id' => $file->getId(),
            'title' => substr($file->getName(), 0, -3),
            'content' => $parsed['content'],
            'folder' => $this->getRelativePath($folder, $file),
            'modified' => $file->getMTime(),
            'type' => $meta['type'] ?? 'note',
            'status' => $meta['status'] ?? '',
            'due' => $meta['due'] ?? '',
            'priority' => $meta['priority'] ?? 0,
            'tags' => $meta['tags'] ?? [],
            'remind' => $meta['remind'] ?? '',
            'person' => $meta['person'] ?? '',
            'start' => $meta['start'] ?? '',
            'reminded' => !empty($meta['reminded']),
            'template' => false,
            'template_name' => '',
        ];

        $this->indexService->syncSingle($userId, $file->getId(), $result);

        return $result;
    }

    public function delete(int $id, string $userId): array {
        $folder = $this->getNotesFolder($userId);
        $nodes = $folder->getById($id);

        if (empty($nodes)) {
            throw new NotFoundException('Note not found');
        }

        $fileId = $nodes[0]->getId();
        $nodes[0]->delete();

        $this->indexService->deleteFromIndex($userId, $fileId);

        return ['status' => 'ok'];
    }

    /**
     * Search: title matches from DB, content matches still require file reads.
     */
    public function search(string $query, string $userId): array {
        $this->indexService->ensureSync($userId);

        // Phase 1: title matches from DB
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('template', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->andWhere($qb->expr()->iLike('title', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($query) . '%')));

        $result = $qb->executeQuery();
        $notes = [];
        $matchedFileIds = [];
        while ($row = $result->fetch()) {
            $note = $this->dbRowToNote($row);
            $notes[] = $note;
            $matchedFileIds[$note['id']] = true;
        }
        $result->closeCursor();

        // Phase 2: content search — read files that weren't already matched by title
        $folder = $this->getNotesFolder($userId);
        $contentMatches = $this->searchFolderContent($folder, '', $query, $matchedFileIds);
        $notes = array_merge($notes, $contentMatches);

        return $notes;
    }

    /**
     * Search file contents, skipping files already matched by title.
     */
    private function searchFolderContent(Folder $folder, string $path, string $query, array $excludeIds): array {
        $notes = [];

        foreach ($folder->getDirectoryListing() as $node) {
            if ($node instanceof File && str_ends_with($node->getName(), '.md')) {
                if (isset($excludeIds[$node->getId()])) {
                    continue;
                }

                $parsed = $this->parseFrontmatter($node->getContent());
                $meta = $parsed['meta'];

                if (!empty($meta['template'])) {
                    continue;
                }

                if (mb_stripos($parsed['content'], $query) !== false) {
                    $startVal = $meta['start'] ?? '';
                    if ($startVal === '') {
                        $ctime = $node->getCreationTime();
                        $ts = ($ctime && $ctime > 0) ? $ctime : $node->getMTime();
                        $startVal = date('Y-m-d', $ts);
                    }

                    $notes[] = [
                        'id' => $node->getId(),
                        'title' => substr($node->getName(), 0, -3),
                        'folder' => $path,
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
                    ];
                }
            } elseif ($node instanceof Folder) {
                $subPath = $path ? $path . '/' . $node->getName() : $node->getName();
                $notes = array_merge($notes, $this->searchFolderContent($node, $subPath, $query, $excludeIds));
            }
        }

        return $notes;
    }

    public function getFolders(string $userId): array {
        $folder = $this->getNotesFolder($userId);
        return $this->listFolders($folder, '');
    }

    private function listFolders(Folder $folder, string $path): array {
        $folders = [];

        foreach ($folder->getDirectoryListing() as $node) {
            if ($node instanceof Folder) {
                $currentPath = $path ? $path . '/' . $node->getName() : $node->getName();
                $folders[] = ['name' => $node->getName(), 'path' => $currentPath];
                $folders = array_merge($folders, $this->listFolders($node, $currentPath));
            }
        }

        return $folders;
    }

    private function getOrCreateSubfolder(Folder $base, string $path): Folder {
        if (empty($path)) {
            return $base;
        }

        $parts = explode('/', $path);
        $current = $base;

        foreach ($parts as $part) {
            try {
                $node = $current->get($part);
                if ($node instanceof Folder) {
                    $current = $node;
                }
            } catch (NotFoundException $e) {
                $current = $current->newFolder($part);
            }
        }

        return $current;
    }

    private function getRelativePath(Folder $base, File $file): string {
        $basePath = $base->getPath();
        $filePath = $file->getParent()->getPath();

        if ($basePath === $filePath) {
            return '';
        }

        return substr($filePath, strlen($basePath) + 1);
    }

    private function sanitizeFilename(string $name): string {
        $name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', $name);
        $name = trim($name);

        if (empty($name)) {
            $name = 'Neue Notiz';
        }

        return $name;
    }

    private function getUniqueFilename(Folder $folder, string $filename): string {
        if (!$folder->nodeExists($filename)) {
            return $filename;
        }

        $name = substr($filename, 0, -3);
        $counter = 2;

        while ($folder->nodeExists($name . ' (' . $counter . ').md')) {
            $counter++;
        }

        return $name . ' (' . $counter . ').md';
    }
}
