# NoteHub – Benutzerhandbuch

**Notizen & Aufgaben für Nextcloud**

Version 0.1 | Februar 2026

*IT-Dienstleistungen Ralf Völzke, Nastätten, Deutschland*

---

## Inhaltsverzeichnis

1. [Einführung](#1-einführung)
2. [Erste Schritte](#2-erste-schritte)
3. [Notizen erstellen und bearbeiten](#3-notizen-erstellen-und-bearbeiten)
4. [Aufgaben und Fälligkeiten](#4-aufgaben-und-fälligkeiten)
5. [Tags und Organisation](#5-tags-und-organisation)
6. [Kontakte verknüpfen](#6-kontakte-verknüpfen)
7. [Vorlagen](#7-vorlagen)
8. [Notizen teilen](#8-notizen-teilen)
9. [Wikilinks und Backlinks](#9-wikilinks-und-backlinks)
10. [Suche und Sortierung](#10-suche-und-sortierung)
11. [Markdown-Formatierung](#11-markdown-formatierung)
12. [Bilder einfügen](#12-bilder-einfügen)
13. [Mobile Nutzung](#13-mobile-nutzung)
14. [Desktop-App](#14-desktop-app)
15. [Tastaturkürzel](#15-tastaturkürzel)
16. [Fehlerbehebung](#16-fehlerbehebung)
17. [Community und Support](#17-community-und-support)

---

## 1. Einführung

NoteHub ist eine Notiz- und Aufgabenverwaltung für Nextcloud. Sie kombiniert die Einfachheit von Markdown-Dateien mit den Kollaborations-Funktionen von Nextcloud.

Jede Notiz wird als einfache `.md`-Datei mit YAML-Frontmatter in deiner Nextcloud gespeichert. Deine Daten sind nie eingesperrt – du kannst deine Notizen mit jedem Texteditor öffnen, mit jedem Tool synchronisieren oder jederzeit zu einer anderen Anwendung wechseln.

### Was macht NoteHub besonders?

- **Einfache Markdown-Dateien** – kein proprietäres Format
- **Notizen und Aufgaben in einer App** – jede Notiz kann zur Aufgabe werden
- **Nextcloud-Adressbuch-Integration** – Notizen mit Kontakten verknüpfen
- **Tags statt Ordner** – flexible Organisation
- **Wikilinks und Backlinks** – Ideen verbinden
- **Vorlagen** mit automatischen Platzhaltern
- **Teilen** mit anderen Nextcloud-Benutzern
- **Desktop-App** für Offline-Arbeit
- **Mobilfreundliches** responsives Design

> **Tipp:** NoteHub ist kompatibel mit Obsidian, Joplin und jedem anderen Markdown-Editor. Deine Notizen funktionieren überall.

---

## 2. Erste Schritte

### Installation

1. Lade das neueste Release von GitHub herunter
2. Entpacke die Dateien in dein Nextcloud `apps/`-Verzeichnis
3. Gehe zu Nextcloud-Verwaltung → Apps
4. Finde NoteHub und klicke auf **Aktivieren**

NoteHub benötigt Nextcloud 28 oder neuer und PHP 8.1–8.4.

### Erster Start

Beim ersten Öffnen von NoteHub werden automatisch sechs Starter-Vorlagen erstellt: Tagebuch, Meeting-Protokoll, Auftrag, Einkaufsliste, Projekt-Notizen und Schnellnotiz.

Die App hat zwei Hauptbereiche:

- **Seitenleiste (links)** – enthält Notizliste, Tags, Kontakte, Vorlagen, Aufgaben und Sortierung
- **Editor (rechts)** – zum Schreiben und Bearbeiten deiner Notizen

> **Tipp:** Auf Mobilgeräten ist die Seitenleiste standardmäßig ausgeblendet. Tippe auf das Hamburger-Menü (☰), um sie zu öffnen.

### Dateispeicherung

NoteHub speichert alle Notizen im Ordner `NoteHub/` in deinem Nextcloud-Benutzerverzeichnis. Jede Notiz ist eine `.md`-Datei mit YAML-Frontmatter:

```yaml
---
title: Meine erste Notiz
tags: [projekt, ideen]
type: note
---

Dein Inhalt hier...
```

---

## 3. Notizen erstellen und bearbeiten

### Neue Notiz erstellen

1. Klicke auf den Button **„Neue Notiz"** oben in der Seitenleiste
2. Eine neue Notiz mit Standard-Titel und heutigem Datum wird erstellt
3. Der Editor öffnet sich automatisch – fang an zu tippen!
4. Der Titel wird zum Dateinamen (z.B. „Meine Notiz 2026-02-22.md")

> **Tipp:** Wenn eine Datei mit dem gleichen Namen existiert, hängt NoteHub automatisch eine Nummer an: „Meine Notiz (2).md".

### Der Editor

Der Editor ist ein Textfeld, in dem du im Markdown-Format schreibst. Über dem Textfeld findest du die Formatierungs-Toolbar:

- Fett, Kursiv, Durchgestrichen
- Überschriften (H1, H2, H3)
- Listen (Aufzählung und nummeriert)
- Checkboxen
- Links und Bilder
- Code-Blöcke
- Wikilinks

### Markdown-Vorschau

Klicke auf **„Vorschau"** in der Toolbar, um deine Notiz formatiert anzuzeigen. Klicke erneut, um zum Editor zurückzukehren.

### Automatisches Speichern

NoteHub speichert deine Notiz automatisch nach 3 Sekunden Pause. Du siehst kurz „Gespeichert ✓". Bei Fehler wird bis zu 3-mal automatisch wiederholt.

Zusätzliche Sicherheitsfunktionen:

- Beim Wechsel zu einer anderen Notiz wird die aktuelle zuerst gespeichert
- Beim Schließen des Browser-Tabs erscheint eine Warnung bei ungespeicherten Änderungen
- Wenn die App in den Hintergrund geht, wird sofort gespeichert
- Ein lokales Backup wird im Browser als Fallback gehalten

---

## 4. Aufgaben und Fälligkeiten

### Aufgabe erstellen

Jede Notiz kann zur Aufgabe werden. Klicke auf **„Als Aufgabe markieren"** oder nutze den **„Neue Aufgabe"**-Button. Aufgaben haben:

- **Fälligkeitsdatum** – wann die Aufgabe erledigt sein soll
- **Priorität** – hoch, mittel oder niedrig
- **Status** – offen oder erledigt

### Farbige Ampelpunkte

Aufgaben zeigen in der Seitenleiste farbige Punkte je nach Fälligkeit:

| Farbe | Bedeutung | Verbleibende Zeit |
|-------|-----------|-------------------|
| 🟢 Grün | Viel Zeit | Mehr als 3 Tage |
| 🟡 Gelb | Wird knapp | 1–3 Tage verbleibend |
| 🔴 Rot | Überfällig | Fälligkeitsdatum überschritten |
| ✅ Haken | Erledigt | Aufgabe abgeschlossen |

### Aufgaben filtern

Im Aufgaben-Bereich der Seitenleiste kannst du filtern:

- **Alle** – alle Aufgaben anzeigen
- **Offen** – nur unerledigte Aufgaben
- **Erledigt** – nur abgeschlossene Aufgaben
- **Überfällig** – Aufgaben nach Fälligkeit

---

## 5. Tags und Organisation

### Tags hinzufügen

Tags sind eine flexible Möglichkeit, Notizen zu organisieren. Anders als Ordner kann eine Notiz mehrere Tags haben.

1. Öffne eine Notiz im Editor
2. Finde das Tag-Feld unterhalb des Titels
3. Tippe einen Tag-Namen und drücke Enter
4. Der Tag erscheint als farbiger Chip

Tags werden im YAML-Frontmatter gespeichert:

```yaml
tags: [projekt, meeting, dringend]
```

### Nach Tag filtern

Klicke auf einen Tag im Tags-Bereich der Seitenleiste, um nur Notizen mit diesem Tag anzuzeigen. Klicke erneut, um den Filter aufzuheben.

---

## 6. Kontakte verknüpfen

### So funktioniert es

NoteHub integriert sich mit deinem Nextcloud-Adressbuch. Du kannst jede Notiz mit einem oder mehreren Kontakten verknüpfen. Nützlich für:

- **Kunden-Notizen** – Meeting-Protokolle mit dem Kunden verknüpfen
- **Aufträge** – Aufgaben mit der zuständigen Person verbinden
- **Projekt-Notizen** – alle Beteiligten verlinken

### Kontakt hinzufügen

1. Öffne eine Notiz im Editor
2. Finde das Kontakt-Feld
3. Beginne einen Namen zu tippen – ein Autocomplete-Dropdown erscheint
4. Wähle einen Kontakt aus der Liste
5. Der Kontakt erscheint als klickbarer Chip

Das Autocomplete zeigt „Name · Firma" oder „Name · E-Mail" zur Unterscheidung.

> **Tipp:** Klicke auf einen Kontakt-Chip, um den Eintrag im Nextcloud-Adressbuch zu öffnen.

### Eindeutige Identifikation

Jeder Kontakt wird mit einer eindeutigen ID (UID) aus dem Adressbuch gespeichert. Auch zwei Kontakte mit gleichem Namen (z.B. zwei „Thomas Schmidt") werden korrekt unterschieden.

### Nach Kontakt filtern

Im Kontakte-Bereich der Seitenleiste siehst du alle verknüpften Kontakte mit der Anzahl zugehöriger Notizen. Klicke auf einen Kontakt, um die Notizliste zu filtern.

---

## 7. Vorlagen

### Mitgelieferte Vorlagen

NoteHub enthält sechs Standard-Vorlagen:

| Vorlage | Beschreibung |
|---------|-------------|
| Tagebuch | Täglicher Tagebucheintrag mit Datum-Platzhalter |
| Meeting-Protokoll | Strukturiertes Protokoll mit Agenda und Aufgaben |
| Auftrag | Auftrags-Dokumentation mit Checkliste und Zeiterfassung |
| Einkaufsliste | Einfache Checkbox-Liste für Einkäufe |
| Projekt-Notizen | Projektdokumentation mit Zielen und Meilensteinen |
| Schnellnotiz | Minimale Vorlage für schnelle Notizen |

### Vorlage verwenden

1. Klicke auf den Vorlagen-Namen im Vorlagen-Bereich der Seitenleiste
2. Eine neue Notiz wird mit dem Vorlagen-Inhalt erstellt
3. Platzhalter wie `{{date}}` und `{{time}}` werden automatisch ersetzt
4. Bearbeite den Inhalt nach Bedarf

### Eigene Vorlagen erstellen

1. Erstelle oder öffne eine Notiz mit dem gewünschten Inhalt
2. Klicke auf **„Als Vorlage speichern"** im Notiz-Menü
3. Die Notiz erscheint im Vorlagen-Bereich

---

## 8. Notizen teilen

### So teilst du eine Notiz

1. Öffne die Notiz, die du teilen möchtest
2. Klicke auf **„Teilen"** in der Toolbar
3. Gib den Benutzernamen eines Nextcloud-Benutzers ein
4. Wähle die Berechtigung: **Nur Lesen** oder **Lesen & Bearbeiten**
5. Der Empfänger sieht die Notiz in seiner NoteHub-Seitenleiste

### Berechtigungen

- **Nur Lesen** – der Empfänger kann die Notiz ansehen, aber nicht ändern
- **Lesen & Bearbeiten** – der Empfänger kann die Notiz bearbeiten

Geteilte Notizen zeigen „von [Benutzername]" in der Seitenleiste an.

---

## 9. Wikilinks und Backlinks

### Wikilinks erstellen

Verbinde deine Notizen, indem du `[[Notiz-Titel]]` in einer Notiz tippst. NoteHub erstellt einen klickbaren Link zur referenzierten Notiz. Wenn die Ziel-Notiz noch nicht existiert, wird sie beim Klick erstellt.

> **Tipp:** Nutze Wikilinks, um eine persönliche Wissensdatenbank aufzubauen. Verlinke verwandte Ideen, Projekte und Personen.

### Backlinks

Wenn Notiz A über einen Wikilink auf Notiz B verweist, zeigt Notiz B automatisch einen Backlink zu Notiz A. So entdeckst du Zusammenhänge, die dir sonst entgangen wären.

Backlinks erscheinen in einem eigenen Bereich unterhalb des Editors.

---

## 10. Suche und Sortierung

### Volltextsuche

Tippe im Suchfeld oben in der Seitenleiste, um alle Notizen zu durchsuchen. NoteHub durchsucht Titel und Inhalt mit einem Datenbank-Index für schnelle Ergebnisse.

### Sortieroptionen

| Sortierung | Beschreibung |
|-----------|-------------|
| Zuletzt bearbeitet | Zuletzt bearbeitete Notizen zuerst (Standard) |
| Titel A–Z | Alphabetische Reihenfolge |
| Titel Z–A | Umgekehrt alphabetisch |
| Neueste zuerst | Nach Erstelldatum, neueste oben |
| Älteste zuerst | Nach Erstelldatum, älteste oben |
| Fälligkeit | Aufgaben nach Fälligkeitsdatum |
| Priorität | Hohe Priorität zuerst |

---

## 11. Markdown-Formatierung

NoteHub verwendet Standard-Markdown-Syntax. Kurzreferenz:

| Format | Syntax | Ergebnis |
|--------|--------|----------|
| Fett | `**Text**` | **Fetter Text** |
| Kursiv | `*Text*` | *Kursiver Text* |
| Durchgestrichen | `~~Text~~` | ~~Durchgestrichener Text~~ |
| Überschrift 1 | `# Titel` | Große Überschrift |
| Überschrift 2 | `## Titel` | Mittlere Überschrift |
| Aufzählung | `- Punkt` | Aufzählungszeichen |
| Nummeriert | `1. Punkt` | Nummerierter Punkt |
| Checkbox | `- [ ] Aufgabe` | Leere Checkbox |
| Erledigt | `- [x] Aufgabe` | Abgehakte Checkbox |
| Link | `[Text](URL)` | Klickbarer Link |
| Bild | `![Alt](URL)` | Eingebettetes Bild |
| Code | `` `Code` `` | Inline-Code |
| Wikilink | `[[Notiz-Titel]]` | Link zu Notiz |
| Trennlinie | `---` | Horizontale Linie |

---

## 12. Bilder einfügen

### Aus der Zwischenablage

1. Kopiere ein Bild in die Zwischenablage (Screenshot, Bild aus dem Web, etc.)
2. Setze den Cursor an die gewünschte Stelle im Editor
3. Drücke **Strg+V**
4. Das Bild wird in deine Nextcloud hochgeladen und ein Markdown-Link eingefügt

### Datei hochladen

1. Klicke den Bild-Button in der Toolbar
2. Wähle eine Datei von deinem Computer
3. Das Bild wird hochgeladen und in der Notiz verlinkt

Bilder werden im Unterordner `NoteHub/images/` in deiner Nextcloud gespeichert.

---

## 13. Mobile Nutzung

NoteHub ist vollständig responsiv und funktioniert auf Smartphones und Tablets.

### Navigation

- Tippe auf das **Hamburger-Menü (☰)**, um die Seitenleiste zu öffnen
- Tippe auf eine Notiz, um sie im Editor zu öffnen (Seitenleiste schließt automatisch)
- Tippe erneut auf das Menü, um zur Notizliste zurückzukehren

### Tipps für Mobile

- Die Toolbar lässt sich horizontal scrollen
- Nutze den Vorschau-Modus zum komfortablen Lesen langer Notizen
- Füge NoteHub zum Startbildschirm hinzu für schnellen Zugriff

> **Tipp:** Um NoteHub zum Startbildschirm hinzuzufügen: Öffne NoteHub im mobilen Browser, tippe auf Teilen/Menü und wähle „Zum Startbildschirm hinzufügen".

---

## 14. Desktop-App

NoteHub Desktop ist eine portable App, die direkt mit deinen lokalen Markdown-Dateien arbeitet – kein Server erforderlich.

### Download und Start

1. Lade die `.exe` von den GitHub Releases herunter
2. Starte sie – keine Installation nötig
3. Wähle deinen NoteHub-Ordner
4. Leg los!

### Cloud-Synchronisation

1. Installiere den Nextcloud Desktop Client
2. Zeige die Desktop-App auf deinen Nextcloud-Sync-Ordner (z.B. `Nextcloud/NoteHub/`)
3. Änderungen synchronisieren sich automatisch in beide Richtungen

> **Tipp:** Die Desktop-App funktioniert offline. Änderungen werden synchronisiert, sobald die Internetverbindung wiederhergestellt ist.

### Einschränkungen

Die Desktop-App hat keinen Zugriff auf das Nextcloud-Adressbuch. Kontakte werden im Frontmatter gespeichert, können aber nicht durchsucht oder verlinkt werden.

---

## 15. Tastaturkürzel

| Tastaturkürzel | Aktion |
|----------------|--------|
| Strg + B | Fett |
| Strg + I | Kursiv |
| Strg + K | Link einfügen |
| Strg + V | Einfügen (inkl. Bild-Upload) |
| Strg + Umschalt + V | Als Text einfügen |
| Tab | Listenpunkt einrücken |
| Umschalt + Tab | Listenpunkt ausrücken |

---

## 16. Fehlerbehebung

### Notizen erscheinen nicht

- Stelle sicher, dass die Dateien im `NoteHub/`-Ordner in deiner Nextcloud liegen
- Dateien müssen die Endung `.md` haben
- Führe einen Dateiscan aus: `occ files:scan [Benutzername]`
- Aktualisiere die Browser-Seite

### Speicherfehler

- Prüfe deine Internetverbindung
- NoteHub wiederholt automatisch bis zu 3-mal
- Ein lokales Backup wird im Browser gehalten
- Bei anhaltenden Problemen: Nextcloud-Logs prüfen

### Bilder werden nicht angezeigt

- Stelle sicher, dass der Unterordner `images/` in `NoteHub/` existiert
- Prüfe die Dateiberechtigungen auf dem Server
- Große Bilder brauchen bei mobilen Verbindungen länger

---

## 17. Community und Support

NoteHub ist Open Source und wird entwickelt von IT-Dienstleistungen Ralf Völzke in Nastätten, Deutschland.

### Hilfe erhalten

- **GitHub Issues:** [github.com/Voelzke/notehub/issues](https://github.com/Voelzke/notehub/issues)
- **GitHub Discussions:** [github.com/Voelzke/notehub/discussions](https://github.com/Voelzke/notehub/discussions)
- **Telegram:** [t.me/NoteHub_RVIT](https://t.me/NoteHub_RVIT)
- **WhatsApp:** [NoteHub Community](https://chat.whatsapp.com/DPcI8mE7FHsBSEPvzBM5v3)

### Mitmachen

Beiträge sind willkommen! Du kannst:

- Fehler melden über GitHub Issues
- Features vorschlagen über GitHub Discussions
- Pull Requests einreichen
- Bei der Übersetzung der App helfen

Fragen und Diskussionen auf Deutsch sind ausdrücklich willkommen!

### Lizenz

NoteHub steht unter der AGPL-3.0-Lizenz. Du kannst die Software frei nutzen, ändern und verbreiten, solange Änderungen unter der gleichen Lizenz veröffentlicht werden.

---

*Vielen Dank, dass du NoteHub nutzt!*
