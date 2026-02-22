# NoteHub – Notes & Tasks for Nextcloud

> 🇩🇪 [Deutsche Version](README.de.md)

**Your notes, tasks, and ideas in one place – built on plain Markdown files.**

NoteHub is a modern note-taking and task management app for Nextcloud. Every note is a simple `.md` file with YAML frontmatter – no proprietary format, no vendor lock-in. Your data belongs to you.

---

## ✨ What Makes NoteHub Special

### Plain Files, Full Control
NoteHub stores everything as readable Markdown files in your Nextcloud. Open them with any text editor, sync them across devices, or migrate to another tool anytime. Your notes are never locked in.

### Notes & Tasks United
Any note can become a task with a single click. Add due dates, priorities, and reminders. Color-coded indicators show urgency at a glance: 🟢 plenty of time, 🟡 getting close, 🔴 overdue.

### Address Book Integration
Link notes and tasks to contacts from your Nextcloud address book. See all notes related to a person or company. Each contact is stored with a unique ID – even two people with the same name stay distinct.

### Flexible Organization
Use tags instead of rigid folders. A note can belong to multiple topics. Connect ideas with `[[wikilinks]]` and discover relationships through automatic backlinks.

### Templates
Create your own templates or use the built-in ones for journals, meeting notes, work orders, shopping lists, and project notes. Placeholders like date and time are filled in automatically.

### Share & Collaborate
Share individual notes with other Nextcloud users. Choose between read-only and edit permissions. Shared notes appear seamlessly in the recipient's note list.

---

## 📋 Features

- Markdown editor with formatting toolbar
- Live preview for rendered Markdown
- Tasks with due dates, priorities, and color indicators
- Reminders via Nextcloud notifications and email
- Tags as virtual folders
- Wikilinks & backlinks
- Contact linking with Nextcloud address book
- Templates with automatic placeholders
- Insert images via clipboard (Ctrl+V) or file upload
- Share with Nextcloud users (read / edit)
- Full-text search with database index
- Sort by title, date, due date, priority
- Mobile-friendly responsive design
- YAML frontmatter compatible with Obsidian and Joplin

---

## 🖥️ Also Available as Desktop App

**[NoteHub Desktop](https://github.com/Voelzke/notehub-desktop)** – A portable desktop app for Windows, Mac, and Linux. Works directly with your local Markdown files. Combine it with Nextcloud Desktop Client for automatic cloud sync.

---

## 📦 Requirements

- Nextcloud 28 or newer
- PHP 8.1 – 8.4

## 🚀 Installation

1. Download the latest release
2. Extract to your Nextcloud `apps/` directory
3. Enable NoteHub in the Nextcloud admin panel

Or install directly from the Nextcloud App Store (coming soon).

## 📁 How It Works

NoteHub stores notes as `.md` files in `NoteHub/` inside your Nextcloud user folder:

```yaml
---
title: My Note
tags: [project, ideas]
type: task
due: 2026-03-15
priority: high
contacts:
  - name: John Smith
    company: Acme Corp
    uid: abc-123-def
---

# My Note

Your content here in Markdown...
```

A database index ensures fast search and sorting, while the files remain the single source of truth.

---

## 🤝 Contributing

Contributions are welcome! Feel free to:

- Report bugs via [Issues](https://github.com/Voelzke/notehub/issues)
- Suggest features via [Discussions](https://github.com/Voelzke/notehub/discussions)
- Submit Pull Requests

This project is developed by [IT-Dienstleistungen Ralf Völzke](https://voelzke.de) in Nastätten, Germany. Questions and discussions in German are welcome!

## 📄 License

AGPL-3.0 – see [LICENSE](LICENSE) for details.
