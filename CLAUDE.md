# NoteHub - Nextcloud App

## Overview
Markdown note-taking app for Nextcloud. Notes stored as `.md` files in user's `NoteHub/` folder.
A note can optionally be a **task** — controlled via YAML frontmatter (`type: task`, `status: open/done`, `due`, `priority`, `tags`, `remind`, `person`).
A note can also be a **template** — controlled via frontmatter (`template: true`, `template_name`). Templates are hidden from the regular note list and appear as choices when creating new notes.

## Tech Stack
- **Backend**: PHP 8.1+, Nextcloud App Framework (NC 30-32)
- **Frontend**: Vue 2.7, @nextcloud/vue 8.x, Webpack
- **Storage**: Nextcloud filesystem (IRootFolder) + MySQL index cache (`oc_notehub_notes`)

## Project Structure
```
appinfo/info.xml          # App metadata, namespace NoteHub
appinfo/routes.php        # Manual routes (NO resources block!)
lib/AppInfo/Application.php   # Bootstrap, APP_ID='notehub', NOTES_FOLDER='NoteHub'
lib/Controller/PageController.php  # Loads Vue SPA (@NoAdminRequired @NoCSRFRequired)
lib/Controller/NoteController.php  # REST API, $userId is ?string (nullable!)
lib/Service/NoteService.php        # File ops via IRootFolder, .md files, frontmatter parsing
lib/Service/IndexService.php       # DB cache sync engine (full/incremental/single/syncFileNode)
lib/Service/ShareService.php       # Sharing via OCP\Share\IManager (create/list/delete shares, user search, shared-with-me)
lib/Listener/FileChangeListener.php # File event listener (auto-detect NoteHub/ changes)
lib/Migration/Version001000Date20260215120000.php  # DB migration for notehub_notes table
lib/Migration/Version001001Date20260220120000.php  # DB migration: adds 'shared' boolean column
lib/BackgroundJob/IndexJob.php     # TimedJob (5min), incremental index sync
lib/BackgroundJob/ReminderJob.php  # TimedJob (5min), sends reminder notifications + emails
lib/Notification/Notifier.php      # INotifier for reminder notifications
src/main.js               # Vue 2 entry, mounts on #notehub
src/App.vue               # SPA (navigation, editor, task-bar, markdown toolbar, sidebar, sharing)
templates/main.php        # Mount point <div id="notehub">
css/style.css             # App styling
img/app.svg               # App icon
```

## API Routes
| Method | URL                          | Action            |
|--------|------------------------------|--------------------|
| GET    | /                            | page#index         |
| GET    | /api/notes                   | note#index         |
| GET    | /api/notes?tag=arbeit        | note#index (filter) |
| GET    | /api/notes/search            | note#search        |
| GET    | /api/notes/titles            | note#titles        |
| GET    | /api/notes/{id}              | note#show          |
| POST   | /api/notes                   | note#create        |
| PUT    | /api/notes/{id}              | note#update        |
| DELETE | /api/notes/{id}              | note#destroy       |
| GET    | /api/tags                    | note#tags          |
| GET    | /api/folders                 | note#folders       |
| PUT    | /api/notes/{id}/toggle-task  | note#toggleTask    |
| PUT    | /api/notes/{id}/set-task     | note#setTask       |
| GET    | /api/notes/{id}/backlinks    | note#backlinks     |
| GET    | /api/templates               | note#templates     |
| POST   | /api/notes/from-template     | note#createFromTemplate |
| PUT    | /api/notes/{id}/set-template | note#setTemplate   |
| PUT    | /api/notes/{id}/unset-template | note#unsetTemplate |
| GET    | /api/index/status              | note#syncStatus    |
| POST   | /api/index/sync                | note#syncIndex     |
| GET    | /api/notes/{id}/shares         | note#shares        |
| POST   | /api/notes/{id}/share          | note#share         |
| DELETE | /api/shares/{shareId}          | note#unshare       |
| GET    | /api/users/search              | note#searchUsers   |
| GET    | /api/shared-with-me            | note#sharedWithMe  |
| GET    | /api/shared-notes/{id}         | note#showSharedNote |
| PUT    | /api/shared-notes/{id}         | note#updateSharedNote |

## Frontmatter
Notes can have YAML frontmatter to mark them as tasks:
```yaml
---
type: task
status: open
due: 2025-03-01
priority: 1
tags: [arbeit, projekt]
remind: 2025-02-28T09:00
reminded: true
person: Max
---
```
- Parsed by `NoteService::parseFrontmatter()` (simple line-based, no external YAML lib)
- Built by `NoteService::buildFileContent()` — plain notes with no task fields get no frontmatter
- `reminded` tracks whether a reminder notification was already sent; auto-reset when `remind` changes
- `find()` returns `content` without frontmatter block + separate meta fields
- `findAll()` sorts: open overdue tasks → open tasks → normal notes → done tasks
- `findAll()` accepts optional `$tag` parameter to filter by tag
- `getTags()` returns all tags with counts across all notes (excludes templates)
- `getTitles()` returns id+title pairs for wikilink autocomplete (excludes templates)

## Tags & Wikilinks
- Tags are managed via chips in editor (for ALL notes, not just tasks)
- Tags in frontmatter trigger tag-based virtual folder navigation in sidebar
- `[[Notiztitel]]` wikilinks: autocomplete on `[[` typing, Ctrl+Click to open linked note
- `saveNote()` always sends tags (not only for tasks), so normal notes get frontmatter when tagged
- Delete button in editor header (trash icon) calls `confirmDelete()` with browser confirm dialog
- Search debounce is 500ms to avoid excessive requests on large collections

## Sidebar Navigation
- Tasks and notes are displayed in separate sections (shared notes integrated into both)
- Tasks section is collapsible (▶/▼ toggle), state persisted in `localStorage` key `notehub-tasks-expanded`
- Sort order within each section: open overdue → open → normal → done (tasks); by sort mode (notes)
- Shared notes show 👥 prefix and "von [Name]" indicator; delete button hidden for shared notes

## Markdown Toolbar
- Formatting toolbar between task-bar and textarea: Bold, Italic, Strikethrough, H1-H3, HR, bullet/numbered/checkbox lists, date/datetime insert, link
- Methods: `toolbarWrap()`, `toolbarLinePrefix()`, `toolbarInsertLine()`, `toolbarInsertDate()`, `toolbarInsertDatetime()`, `toolbarInsertLink()`
- Operates on textarea via `selectionStart`/`selectionEnd` and `setSelectionRange()`

## Save Mechanism
- `saveNote()` uses a lock (`saving` flag) + queue (`savePending` flag) to prevent race conditions
- If a save is in progress, the next change is queued and executed after the current save finishes
- Debounce is 2000ms (in `onContentChange` watcher)

## Templates
- Templates are normal `.md` files with `template: true` in frontmatter
- Hidden from `findAll()`, `getTags()`, `getTitles()`, `search()`, and `findPendingReminders()`
- `getTemplates()` returns `[{id, template_name}]` sorted alphabetically
- `createFromTemplate()` replaces `{{date}}`, `{{time}}`, `{{datetime}}` placeholders in body
- New note title = `template_name + ' ' + Y-m-d`, tags copied from template
- `setTemplate()` / `unsetTemplate()` follow `setTask()` / `unsetTask()` pattern
- Frontend: NcActions dropdown menus for "+ Notiz" and "+ Aufgabe" with template choices
- Meta-bar buttons: "Als Vorlage speichern" / "Vorlage entfernen"
- 5 default templates: Tagebuch-Eintrag, Meeting-Protokoll, Auftrag, Einkaufsliste, Projekt-Notiz

## Sharing
- Notes can be shared with other Nextcloud users via `OCP\Share\IManager`
- `ShareService` handles all sharing logic (create, list, delete shares, user search, shared-with-me, read/update shared notes)
- Uses Nextcloud's native share API (`IShare::TYPE_USER`) for user-to-user file sharing
- `shared` boolean column in `notehub_notes` tracks whether a note has active shares (for sidebar indicator)
- `IndexService::updateSharedFlag()` updates the `shared` column independently from other sync operations
- **Integrated view**: Shared notes appear in the normal note list alongside own notes (no separate section)
- Shared notes have `shared: true`, `sharedBy`, `sharedByDisplayName`, `permissions` in their data
- `NoteController::index()` merges own notes + shared notes from `ShareService::getSharedWithMe()`
- `NoteController::tags()` merges own tags + tags from shared notes
- `NoteController::show()` tries own folder first, falls back to `ShareService::readSharedNote()` via share API
- `NoteController::update()` tries own folder first, falls back to `ShareService::updateSharedNote()` (checks write permission)
- `NoteController::toggleTask()` tries own folder first, falls back to `ShareService::toggleSharedTask()`
- Shared notes in sidebar: 👥 emoji prefix + "von [Name]" indicator, no delete button
- Permissions: 1 = read only (editor readonly), permissions with UPDATE bit (& 2) = read + write
- Frontend: "Teilen" button in meta-bar opens share dialog (user search, permission select, share list)
- Tag sharing: share icon next to each tag in sidebar, shares all notes with that tag at once

## DB Index Cache
- Table `oc_notehub_notes` caches note metadata (title, type, status, tags, etc.) for fast list queries
- `.md` files remain the single source of truth — DB is a read cache
- `IndexService::ensureSync()` only runs `fullSync()` when DB is empty (first visit)
- All mutating methods (create/update/delete/toggle/set/unset) call `syncSingle()` or `deleteFromIndex()` to keep cache current
- **File Event Hooks**: `FileChangeListener` auto-detects changes to `.md` files in `NoteHub/` via Nextcloud events:
  - `NodeCreatedEvent` / `NodeWrittenEvent` → `syncFileNode()` (parse + upsert index)
  - `NodeDeletedEvent` → `deleteFromIndex()`
  - `NodeRenamedEvent` → delete old + sync new (handles move in/out of NoteHub/)
- `IndexJob` runs every 5 minutes via Nextcloud cron as additional safety net
- List queries (`findAll`, `getTags`, `getTitles`, `getTemplates`, `findPendingReminders`) use DB
- Single-note reads (`find`), backlinks, and content search still read `.md` files
- Manual re-sync: Refresh button in sidebar, `POST /api/index/sync`, or `occ background-job:execute <id> --force-execute`

## Background Job & Notifications
- `IndexJob` runs every 5 minutes via Nextcloud cron (TimedJob), incremental sync
- `ReminderJob` runs every 5 minutes via Nextcloud cron (TimedJob)
- Scans all users for notes with `remind` datetime in the past and `reminded != true`
- Sends Nextcloud notification + email via `IMailer`
- After sending, marks note as `reminded: true` in frontmatter
- Notifier registered in `Application.php`, job registered in `appinfo/info.xml`
- Manual test: `sudo -u www-data php /var/www/nextcloud/occ background-job:execute <ID> --force-execute` (find ID with `SELECT id, class FROM oc_jobs WHERE class LIKE '%NoteHub%'`)

## Build Commands
```bash
cd /var/www/nextcloud/apps/notehub
npm install
npm run build
sudo chown -R www-data:www-data /var/www/nextcloud/apps/notehub/
sudo -u www-data php /var/www/nextcloud/occ app:enable notehub
```

## Known Pitfalls
- NO `resources` block in routes.php (collides with manual routes)
- `$userId` in NoteController MUST be `?string` (nullable)
- After npm build always `chown -R www-data:www-data`
- Curl tests need `OCS-APIREQUEST: true` header
- Clear brute force before testing: `redis-cli FLUSHALL` + `occ security:bruteforce:reset ::1`

## Testing
```bash
# Credentials: admin:Gismo,338
curl -u admin:Gismo,338 -H "OCS-APIREQUEST: true" http://localhost/index.php/apps/notehub/api/notes
```
