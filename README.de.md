# NoteHub – Notizen & Aufgaben für Nextcloud

> 🇬🇧 [English Version](README.md)

**Deine Notizen, Aufgaben und Ideen an einem Ort – basierend auf einfachen Markdown-Dateien.**

NoteHub ist eine moderne Notiz- und Aufgabenverwaltung für Nextcloud. Jede Notiz ist eine einfache `.md`-Datei mit YAML-Frontmatter – kein proprietäres Format, kein Vendor Lock-in. Deine Daten gehören dir.

---

## ✨ Was NoteHub besonders macht

### Einfache Dateien, volle Kontrolle
NoteHub speichert alles als lesbare Markdown-Dateien in deiner Nextcloud. Öffne sie mit jedem Texteditor, synchronisiere sie über Geräte hinweg oder wechsle jederzeit zu einem anderen Tool. Deine Notizen sind nie eingesperrt.

### Notizen & Aufgaben vereint
Jede Notiz kann mit einem Klick zur Aufgabe werden. Setze Fälligkeitsdaten, Prioritäten und Erinnerungen. Farbige Ampelpunkte zeigen die Dringlichkeit auf einen Blick: 🟢 viel Zeit, 🟡 wird knapp, 🔴 überfällig.

### Adressbuch-Integration
Verknüpfe Notizen und Aufgaben mit Kontakten aus deinem Nextcloud-Adressbuch. Sieh alle Notizen zu einer Person oder Firma. Jeder Kontakt wird mit einer eindeutigen ID gespeichert – auch zwei Personen mit gleichem Namen bleiben unterscheidbar.

### Flexible Organisation
Nutze Tags statt starrer Ordnerstrukturen. Eine Notiz kann zu mehreren Themen gehören. Verknüpfe Gedanken mit `[[Wikilinks]]` und entdecke Zusammenhänge durch automatische Backlinks.

### Vorlagen
Erstelle eigene Vorlagen oder nutze die mitgelieferten für Tagebuch, Meeting-Protokoll, Aufträge, Einkaufslisten und Projekt-Notizen. Platzhalter wie Datum und Uhrzeit werden automatisch ausgefüllt.

### Teilen & Zusammenarbeiten
Teile einzelne Notizen mit anderen Nextcloud-Benutzern. Wähle zwischen Lese- und Bearbeitungsrechten. Geteilte Notizen erscheinen nahtlos in der Notizliste des Empfängers.

---

## 📋 Features

- Markdown-Editor mit Formatierungs-Toolbar
- Live-Vorschau für gerendertes Markdown
- Aufgaben mit Fälligkeit, Priorität und Farbampel
- Erinnerungen über Nextcloud-Benachrichtigungen und E-Mail
- Tags als virtuelle Ordner
- Wikilinks & Backlinks
- Kontakte-Verknüpfung mit Nextcloud-Adressbuch
- Vorlagen mit automatischen Platzhaltern
- Bilder einfügen per Zwischenablage (Strg+V) oder Datei-Upload
- Teilen mit Nextcloud-Benutzern (Lesen / Bearbeiten)
- Volltextsuche mit Datenbank-Index
- Sortierung nach Titel, Datum, Fälligkeit, Priorität
- Mobilfreundliches responsives Design
- YAML-Frontmatter kompatibel mit Obsidian und Joplin

---

## 🖥️ Auch als Desktop-App verfügbar

**[NoteHub Desktop](https://github.com/Voelzke/notehub-desktop)** – Eine portable Desktop-App für Windows, Mac und Linux. Arbeitet direkt mit deinen lokalen Markdown-Dateien. Kombiniere sie mit dem Nextcloud Desktop Client für automatische Cloud-Synchronisation.

---

## 📦 Voraussetzungen

- Nextcloud 28 oder neuer
- PHP 8.1 – 8.4

## 🚀 Installation

1. Lade das neueste Release herunter
2. Entpacke es in dein Nextcloud `apps/`-Verzeichnis
3. Aktiviere NoteHub in der Nextcloud-Verwaltung

Oder installiere direkt aus dem Nextcloud App Store (in Kürze verfügbar).

## 📁 So funktioniert es

NoteHub speichert Notizen als `.md`-Dateien im Ordner `NoteHub/` innerhalb deines Nextcloud-Benutzerverzeichnisses:

```yaml
---
title: Meine Notiz
tags: [projekt, ideen]
type: task
due: 2026-03-15
priority: high
contacts:
  - name: Max Mustermann
    company: Firma GmbH
    uid: abc-123-def
---

# Meine Notiz

Dein Inhalt hier in Markdown...
```

Ein Datenbank-Index sorgt für schnelle Suche und Sortierung, während die Dateien die einzige Wahrheitsquelle bleiben.

---

## 🤝 Mitmachen

Beiträge sind willkommen! Du kannst:

- Fehler melden über [Issues](https://github.com/Voelzke/notehub/issues)
- Ideen vorschlagen über [Discussions](https://github.com/Voelzke/notehub/discussions)
- Pull Requests einreichen

Dieses Projekt wird entwickelt von [IT-Dienstleistungen Ralf Völzke](https://voelzke.de) in Nastätten, Deutschland. Fragen und Diskussionen auf Deutsch sind ausdrücklich willkommen!

## 📄 Lizenz

AGPL-3.0 – siehe [LICENSE](LICENSE) für Details.
