<?php

declare(strict_types=1);

namespace OCA\NoteHub\Service;

use OCA\NoteHub\AppInfo\Application;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class IndexService {
    private IDBConnection $db;
    private IRootFolder $rootFolder;
    private LoggerInterface $logger;

    private const TABLE = 'notehub_notes';

    public function __construct(
        IDBConnection $db,
        IRootFolder $rootFolder,
        LoggerInterface $logger
    ) {
        $this->db = $db;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
    }

    /**
     * Get index status for a user.
     */
    public function getStatus(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*) as cnt'))
            ->from(self::TABLE)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        $count = (int)($row['cnt'] ?? 0);

        return [
            'indexed' => $count > 0,
            'count' => $count,
        ];
    }

    /**
     * Ensure the index exists. Only runs fullSync when the index is empty.
     * Incremental sync is handled by the background job and syncSingle on mutations.
     */
    public function ensureSync(string $userId): void {
        $status = $this->getStatus($userId);
        if (!$status['indexed']) {
            $this->fullSync($userId);
        }
    }

    /**
     * Full sync: scan all .md files and rebuild the index completely.
     */
    public function fullSync(string $userId): array {
        $folder = $this->getNotesFolder($userId);
        if ($folder === null) {
            return ['total' => 0, 'updated' => 0];
        }

        // Delete all existing rows for this user
        $qb = $this->db->getQueryBuilder();
        $qb->delete(self::TABLE)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        $qb->executeStatement();

        // Scan all files
        $files = $this->scanFilesRecursive($folder, '');
        $count = 0;

        foreach ($files as $fileData) {
            $this->insertRow($userId, $fileData);
            $count++;
        }

        $this->logger->debug('NoteHub IndexService: fullSync for ' . $userId . ' indexed ' . $count . ' files');

        return ['total' => $count, 'updated' => $count];
    }

    /**
     * Incremental sync: compare file mtimes with DB, update changed files, remove deleted.
     */
    public function incrementalSync(string $userId): array {
        $folder = $this->getNotesFolder($userId);
        if ($folder === null) {
            return ['total' => 0, 'updated' => 0];
        }

        // Load DB map: file_id → modified
        $dbMap = $this->loadDbMap($userId);

        // Walk filesystem
        $files = $this->scanFilesRecursive($folder, '');
        $seenFileIds = [];
        $updated = 0;

        foreach ($files as $fileData) {
            $fileId = $fileData['file_id'];
            $seenFileIds[$fileId] = true;

            if (!isset($dbMap[$fileId])) {
                // New file
                $this->insertRow($userId, $fileData);
                $updated++;
            } elseif ($fileData['modified'] > $dbMap[$fileId]) {
                // Changed file
                $this->updateRow($userId, $fileId, $fileData);
                $updated++;
            }
        }

        // Delete orphaned rows (files that no longer exist)
        foreach ($dbMap as $fileId => $modified) {
            if (!isset($seenFileIds[$fileId])) {
                $this->deleteRow($fileId);
                $updated++;
            }
        }

        return ['total' => count($files), 'updated' => $updated];
    }

    /**
     * Sync a single note into the index (called after create/update).
     */
    public function syncSingle(string $userId, int $fileId, array $noteData): void {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));

        $result = $qb->executeQuery();
        $exists = $result->fetch();
        $result->closeCursor();

        $row = $this->noteDataToRow($userId, $noteData);

        if ($exists) {
            $this->updateRow($userId, $fileId, $row);
        } else {
            $row['file_id'] = $fileId;
            $this->insertRow($userId, $row);
        }
    }

    /**
     * Remove a note from the index.
     */
    public function deleteFromIndex(string $userId, int $fileId): void {
        $this->deleteRow($fileId);
    }

    /**
     * Update the shared flag for a note in the index.
     */
    public function updateSharedFlag(string $userId, int $fileId, bool $shared): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update(self::TABLE)
            ->set('shared', $qb->createNamedParameter($shared, IQueryBuilder::PARAM_BOOL))
            ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    /**
     * Parse and sync a single File node into the index.
     */
    public function syncFileNode(string $userId, File $file, string $relativePath): void {
        $raw = $file->getContent();
        $parsed = $this->parseFrontmatter($raw);
        $meta = $parsed['meta'];

        $startVal = $meta['start'] ?? '';
        if ($startVal === '') {
            $ctime = $file->getCreationTime();
            $ts = ($ctime && $ctime > 0) ? $ctime : $file->getMTime();
            $startVal = date('Y-m-d', $ts);
        }

        $noteData = [
            'file_id'       => $file->getId(),
            'title'         => substr($file->getName(), 0, -3),
            'path'          => $relativePath,
            'type'          => $meta['type'] ?? 'note',
            'status'        => $meta['status'] ?? '',
            'due'           => $meta['due'] ?? '',
            'priority'      => $meta['priority'] ?? 0,
            'tags'          => $meta['tags'] ?? [],
            'remind'        => $meta['remind'] ?? '',
            'reminded'      => !empty($meta['reminded']),
            'person'        => $meta['person'] ?? '',
            'start'         => $startVal,
            'template'      => !empty($meta['template']),
            'template_name' => $meta['template_name'] ?? '',
            'modified'      => $file->getMTime(),
        ];

        $this->syncSingle($userId, $file->getId(), $noteData);
    }

    // ── Private helpers ──────────────────────────────────

    private function getNotesFolder(string $userId): ?Folder {
        try {
            $userFolder = $this->rootFolder->getUserFolder($userId);
            $folder = $userFolder->get(Application::NOTES_FOLDER);
            if ($folder instanceof Folder) {
                return $folder;
            }
        } catch (NotFoundException $e) {
        } catch (\Exception $e) {
            $this->logger->warning('NoteHub IndexService: cannot access NoteHub folder for ' . $userId . ': ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Recursively scan folder for .md files, returning parsed metadata for each.
     */
    private function scanFilesRecursive(Folder $folder, string $path): array {
        $results = [];

        foreach ($folder->getDirectoryListing() as $node) {
            if ($node instanceof File && str_ends_with($node->getName(), '.md')) {
                try {
                    $raw = $node->getContent();
                    $parsed = $this->parseFrontmatter($raw);
                    $meta = $parsed['meta'];

                    $startVal = $meta['start'] ?? '';
                    if ($startVal === '') {
                        $ctime = $node->getCreationTime();
                        $ts = ($ctime && $ctime > 0) ? $ctime : $node->getMTime();
                        $startVal = date('Y-m-d', $ts);
                    }

                    $results[] = [
                        'file_id' => $node->getId(),
                        'title' => substr($node->getName(), 0, -3),
                        'path' => $path,
                        'type' => $meta['type'] ?? 'note',
                        'status' => $meta['status'] ?? '',
                        'due' => $meta['due'] ?? '',
                        'priority' => $meta['priority'] ?? 0,
                        'tags' => $meta['tags'] ?? [],
                        'remind' => $meta['remind'] ?? '',
                        'reminded' => !empty($meta['reminded']),
                        'person' => $meta['person'] ?? '',
                        'start' => $startVal,
                        'template' => !empty($meta['template']),
                        'template_name' => $meta['template_name'] ?? '',
                        'modified' => $node->getMTime(),
                    ];
                } catch (\Exception $e) {
                    $this->logger->warning('NoteHub IndexService: failed to parse ' . $node->getPath() . ': ' . $e->getMessage());
                }
            } elseif ($node instanceof Folder) {
                $subPath = $path !== '' ? $path . '/' . $node->getName() : $node->getName();
                $results = array_merge($results, $this->scanFilesRecursive($node, $subPath));
            }
        }

        return $results;
    }

    /**
     * Parse YAML frontmatter from raw .md content (same logic as NoteService).
     */
    private function parseFrontmatter(string $rawContent): array {
        $meta = ['type' => 'note'];
        $body = $rawContent;

        if (!str_starts_with($rawContent, "---\n")) {
            return ['meta' => $meta, 'content' => $body];
        }

        $end = strpos($rawContent, "\n---\n", 4);
        if ($end === false) {
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

    /**
     * Load file_id → modified map from DB for a user.
     */
    private function loadDbMap(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('file_id', 'modified')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        $result = $qb->executeQuery();
        $map = [];
        while ($row = $result->fetch()) {
            $map[(int)$row['file_id']] = (int)$row['modified'];
        }
        $result->closeCursor();

        return $map;
    }

    /**
     * Convert note data array to DB row values.
     */
    private function noteDataToRow(string $userId, array $data): array {
        $tags = $data['tags'] ?? [];
        if (is_array($tags)) {
            $tags = json_encode(array_values($tags));
        }

        return [
            'file_id' => $data['file_id'] ?? ($data['id'] ?? 0),
            'title' => $data['title'] ?? '',
            'path' => $data['path'] ?? ($data['folder'] ?? ''),
            'type' => $data['type'] ?? 'note',
            'status' => $data['status'] ?? '',
            'due' => $data['due'] ?? '',
            'priority' => (int)($data['priority'] ?? 0),
            'tags' => $tags,
            'remind' => $data['remind'] ?? '',
            'reminded' => !empty($data['reminded']),
            'person' => $data['person'] ?? '',
            'start' => $data['start'] ?? '',
            'template' => !empty($data['template']),
            'template_name' => $data['template_name'] ?? '',
            'modified' => (int)($data['modified'] ?? 0),
        ];
    }

    private function insertRow(string $userId, array $data): void {
        $row = is_string($data['tags'] ?? null) ? $data : $this->noteDataToRow($userId, $data);

        $qb = $this->db->getQueryBuilder();
        $qb->insert(self::TABLE)
            ->values([
                'user_id' => $qb->createNamedParameter($userId),
                'file_id' => $qb->createNamedParameter((int)$row['file_id'], IQueryBuilder::PARAM_INT),
                'title' => $qb->createNamedParameter($row['title']),
                'path' => $qb->createNamedParameter($row['path']),
                'type' => $qb->createNamedParameter($row['type']),
                'status' => $qb->createNamedParameter($row['status']),
                'due' => $qb->createNamedParameter($row['due']),
                'priority' => $qb->createNamedParameter((int)$row['priority'], IQueryBuilder::PARAM_INT),
                'tags' => $qb->createNamedParameter($row['tags']),
                'remind' => $qb->createNamedParameter($row['remind']),
                'reminded' => $qb->createNamedParameter($row['reminded'], IQueryBuilder::PARAM_BOOL),
                'person' => $qb->createNamedParameter($row['person']),
                'start' => $qb->createNamedParameter($row['start']),
                'template' => $qb->createNamedParameter($row['template'], IQueryBuilder::PARAM_BOOL),
                'template_name' => $qb->createNamedParameter($row['template_name']),
                'modified' => $qb->createNamedParameter((int)$row['modified'], IQueryBuilder::PARAM_INT),
            ]);
        $qb->executeStatement();
    }

    private function updateRow(string $userId, int $fileId, array $data): void {
        $row = is_string($data['tags'] ?? null) ? $data : $this->noteDataToRow($userId, $data);

        $qb = $this->db->getQueryBuilder();
        $qb->update(self::TABLE)
            ->set('title', $qb->createNamedParameter($row['title']))
            ->set('path', $qb->createNamedParameter($row['path']))
            ->set('type', $qb->createNamedParameter($row['type']))
            ->set('status', $qb->createNamedParameter($row['status']))
            ->set('due', $qb->createNamedParameter($row['due']))
            ->set('priority', $qb->createNamedParameter((int)$row['priority'], IQueryBuilder::PARAM_INT))
            ->set('tags', $qb->createNamedParameter($row['tags']))
            ->set('remind', $qb->createNamedParameter($row['remind']))
            ->set('reminded', $qb->createNamedParameter($row['reminded'], IQueryBuilder::PARAM_BOOL))
            ->set('person', $qb->createNamedParameter($row['person']))
            ->set('start', $qb->createNamedParameter($row['start']))
            ->set('template', $qb->createNamedParameter($row['template'], IQueryBuilder::PARAM_BOOL))
            ->set('template_name', $qb->createNamedParameter($row['template_name']))
            ->set('modified', $qb->createNamedParameter((int)$row['modified'], IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    private function deleteRow(int $fileId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete(self::TABLE)
            ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
