<?php

declare(strict_types=1);

namespace OCA\NoteHub\Controller;

use OCA\NoteHub\AppInfo\Application;
use OCA\NoteHub\Service\NoteService;
use OCA\NoteHub\Service\IndexService;
use OCA\NoteHub\Service\ShareService;
use OCA\NoteHub\Service\ContactService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

class NoteController extends Controller {
    private NoteService $noteService;
    private IndexService $indexService;
    private ShareService $shareService;
    private ContactService $contactService;
    private IUserSession $userSession;

    public function __construct(
        IRequest $request,
        NoteService $noteService,
        IndexService $indexService,
        ShareService $shareService,
        ContactService $contactService,
        IUserSession $userSession
    ) {
        parent::__construct(Application::APP_ID, $request);
        $this->noteService = $noteService;
        $this->indexService = $indexService;
        $this->shareService = $shareService;
        $this->contactService = $contactService;
        $this->userSession = $userSession;
    }

    private function getUserId(): ?string {
        return $this->userSession->getUser()?->getUID();
    }

    /**
     * @NoAdminRequired
     */
    public function index(string $tag = ''): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        $tagParam = $tag !== '' ? $tag : null;
        $notes = $this->noteService->findAll($userId, $tagParam);

        try {
            $shared = $this->shareService->getSharedWithMe($userId);
            foreach ($shared as $note) {
                if ($tagParam !== null && !in_array($tagParam, $note['tags'] ?? [], true)) {
                    continue;
                }
                $notes[] = $note;
            }
        } catch (\Throwable $e) {
            // Shared notes unavailable — return own notes only
        }

        return new JSONResponse($notes);
    }

    /**
     * @NoAdminRequired
     */
    public function tags(): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        $tags = $this->noteService->getTags($userId);

        try {
            $shared = $this->shareService->getSharedWithMe($userId);
            $tagCounts = [];
            foreach ($tags as $t) {
                $tagCounts[$t['name']] = $t['count'];
            }
            foreach ($shared as $note) {
                foreach ($note['tags'] ?? [] as $tag) {
                    $tag = trim($tag);
                    if ($tag === '') continue;
                    $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                }
            }
            ksort($tagCounts);
            $tags = [];
            foreach ($tagCounts as $name => $count) {
                $tags[] = ['name' => $name, 'count' => $count];
            }
        } catch (\Throwable $e) {
            // Use own tags only
        }

        return new JSONResponse($tags);
    }

    /**
     * @NoAdminRequired
     */
    public function titles(): JSONResponse {
        return new JSONResponse($this->noteService->getTitles($this->getUserId()));
    }

    /**
     * @NoAdminRequired
     */
    public function show(int $id): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        try {
            return new JSONResponse($this->noteService->find($id, $userId));
        } catch (NotFoundException $e) {
            try {
                return new JSONResponse($this->shareService->readSharedNote($userId, $id));
            } catch (\Throwable $e2) {
                return new JSONResponse(['error' => 'Note not found'], 404);
            }
        }
    }

    /**
     * @NoAdminRequired
     */
    public function create(string $title, string $content = '', string $folder = '', string $type = 'note'): JSONResponse {
        return new JSONResponse($this->noteService->create($title, $content, $folder, $this->getUserId(), $type));
    }

    /**
     * @NoAdminRequired
     */
    public function update(
        int $id,
        string $title,
        string $content,
        string $folder = '',
        ?string $type = null,
        ?string $status = null,
        ?string $due = null,
        ?int $priority = null,
        ?array $tags = null,
        ?string $remind = null,
        ?string $person = null,
        ?array $contacts = null
    ): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        try {
            return new JSONResponse($this->noteService->update(
                $id, $title, $content, $folder, $userId,
                $type, $status, $due, $priority, $tags, $remind, $person, $contacts
            ));
        } catch (NotFoundException $e) {
            try {
                return new JSONResponse($this->shareService->updateSharedNote(
                    $userId, $id, $title, $content,
                    $type, $status, $due, $priority, $tags, $remind, $person
                ));
            } catch (\Throwable $e2) {
                $code = str_contains($e2->getMessage(), 'No write permission') ? 403 : 400;
                return new JSONResponse(['error' => $e2->getMessage()], $code);
            }
        }
    }

    /**
     * @NoAdminRequired
     */
    public function destroy(int $id): JSONResponse {
        return new JSONResponse($this->noteService->delete($id, $this->getUserId()));
    }

    /**
     * @NoAdminRequired
     */
    public function search(string $q = ''): JSONResponse {
        if (empty($q)) {
            return new JSONResponse($this->noteService->findAll($this->getUserId()));
        }
        return new JSONResponse($this->noteService->search($q, $this->getUserId()));
    }

    /**
     * @NoAdminRequired
     */
    public function folders(): JSONResponse {
        return new JSONResponse($this->noteService->getFolders($this->getUserId()));
    }

    /**
     * @NoAdminRequired
     */
    public function toggleTask(int $id): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        try {
            return new JSONResponse($this->noteService->toggleTask($id, $userId));
        } catch (NotFoundException $e) {
            try {
                return new JSONResponse($this->shareService->toggleSharedTask($userId, $id));
            } catch (\Throwable $e2) {
                $code = str_contains($e2->getMessage(), 'No write permission') ? 403 : 400;
                return new JSONResponse(['error' => $e2->getMessage()], $code);
            }
        }
    }

    /**
     * @NoAdminRequired
     */
    public function setTask(int $id): JSONResponse {
        return new JSONResponse($this->noteService->setTask($id, $this->getUserId()));
    }

    /**
     * @NoAdminRequired
     */
    public function unsetTask(int $id): JSONResponse {
        return new JSONResponse($this->noteService->unsetTask($id, $this->getUserId()));
    }

    /**
     * @NoAdminRequired
     */
    public function backlinks(int $id): JSONResponse {
        return new JSONResponse($this->noteService->getBacklinks($id, $this->getUserId()));
    }

    /**
     * @NoAdminRequired
     */
    public function templates(): JSONResponse {
        return new JSONResponse($this->noteService->getTemplates($this->getUserId()));
    }

    /**
     * @NoAdminRequired
     */
    public function createFromTemplate(int $templateId, string $type = 'note'): JSONResponse {
        return new JSONResponse($this->noteService->createFromTemplate($templateId, $this->getUserId(), $type));
    }

    /**
     * @NoAdminRequired
     */
    public function setTemplate(int $id, string $templateName = ''): JSONResponse {
        return new JSONResponse($this->noteService->setTemplate($id, $templateName, $this->getUserId()));
    }

    /**
     * @NoAdminRequired
     */
    public function unsetTemplate(int $id): JSONResponse {
        return new JSONResponse($this->noteService->unsetTemplate($id, $this->getUserId()));
    }

    /**
     * @NoAdminRequired
     */
    public function syncStatus(): JSONResponse {
        return new JSONResponse($this->indexService->getStatus($this->getUserId()));
    }

    /**
     * @NoAdminRequired
     */
    public function syncIndex(): JSONResponse {
        return new JSONResponse($this->indexService->fullSync($this->getUserId()));
    }

    // ── Sharing ────────────────────────────────────────

    /**
     * @NoAdminRequired
     */
    public function shares(int $id): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        try {
            return new JSONResponse($this->shareService->getSharesForNote($userId, $id));
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function share(int $id): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        $shareWith = $this->request->getParam('shareWith', '');
        $permissions = (int)$this->request->getParam('permissions', 1);

        try {
            $result = $this->shareService->createShareForNote($userId, $id, $shareWith, $permissions);
            return new JSONResponse($result);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function unshare(int $shareId): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        try {
            $this->shareService->deleteShareForNote($userId, $shareId);
            return new JSONResponse(['status' => 'ok']);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function searchUsers(): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        $query = $this->request->getParam('q', '');
        try {
            return new JSONResponse($this->shareService->searchUsers($query, $userId));
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function sharedWithMe(): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        try {
            return new JSONResponse($this->shareService->getSharedWithMe($userId));
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function showSharedNote(int $id): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        try {
            return new JSONResponse($this->shareService->readSharedNote($userId, $id));
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => $e->getMessage()], 404);
        }
    }

    // ── Contacts ──────────────────────────────────────────

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function searchContacts(string $q = ''): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        try {
            return new JSONResponse($this->contactService->searchContacts($q));
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function contacts(): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        return new JSONResponse($this->noteService->getContacts($userId));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function contactNotes(string $name): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        return new JSONResponse($this->noteService->getNotesForContact($userId, $name));
    }

    // ── Image Upload ─────────────────────────────────────

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function uploadImage(int $id): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        try {
            $file = $this->request->getUploadedFile('image');
            if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
                return new JSONResponse(['error' => 'No image uploaded'], 400);
            }
            if ($file['size'] > 10 * 1024 * 1024) {
                return new JSONResponse(['error' => 'File too large (max 10MB)'], 400);
            }
            $mime = $file['type'] ?? '';
            if (!str_starts_with($mime, 'image/')) {
                return new JSONResponse(['error' => 'Not an image'], 400);
            }
            $result = $this->noteService->uploadImage($userId, $file);
            return new JSONResponse($result);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getImage(string $filename): Response {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        try {
            return $this->noteService->getImage($userId, $filename);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'Image not found'], 404);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function updateSharedNote(int $id): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        $title = $this->request->getParam('title', '');
        $content = $this->request->getParam('content', '');
        $type = $this->request->getParam('type');
        $status = $this->request->getParam('status');
        $due = $this->request->getParam('due');
        $priority = $this->request->getParam('priority');
        $tags = $this->request->getParam('tags');
        $remind = $this->request->getParam('remind');
        $person = $this->request->getParam('person');

        try {
            $result = $this->shareService->updateSharedNote(
                $userId, $id, $title, $content,
                $type, $status, $due,
                $priority !== null ? (int)$priority : null,
                $tags, $remind, $person
            );
            return new JSONResponse($result);
        } catch (\Throwable $e) {
            $code = str_contains($e->getMessage(), 'No write permission') ? 403 : 400;
            return new JSONResponse(['error' => $e->getMessage()], $code);
        }
    }
}
