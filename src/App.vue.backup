<template>
    <NcContent app-name="notehub" :class="{ 'notehub-mobile': isMobile, 'notehub-mobile-editor': isMobile && mobileShowEditor }">
        <NcAppNavigation>
            <template #list>
                <div class="notehub-new-buttons">
                    <NcActions :menu-name="t('notehub', '+ Notiz')" type="secondary">
                        <NcActionButton close-after-click @click="onNewNoteBlank">
                            {{ t('notehub', 'Leere Notiz') }}
                        </NcActionButton>
                        <NcActionButton v-for="tpl in templates" :key="'n-'+tpl.id"
                            close-after-click @click="onNewFromTemplate(tpl.id, 'note')">
                            {{ tpl.template_name }}
                        </NcActionButton>
                    </NcActions>

                    <NcActions :menu-name="t('notehub', '+ Aufgabe')" type="secondary">
                        <NcActionButton close-after-click @click="onNewTaskBlank">
                            {{ t('notehub', 'Leere Aufgabe') }}
                        </NcActionButton>
                        <NcActionButton v-for="tpl in templates" :key="'t-'+tpl.id"
                            close-after-click @click="onNewFromTemplate(tpl.id, 'task')">
                            {{ tpl.template_name }}
                        </NcActionButton>
                    </NcActions>
                </div>

                <div class="notehub-search">
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('notehub', 'Suchen...')"
                        @input="debouncedSearch">
                    <button v-if="searchQuery"
                            class="notehub-search-clear"
                            @click="clearSearch">&times;</button>
                </div>

                <div class="notehub-tags-header" @click="toggleTagsNav">
                    <span class="notehub-tags-toggle">{{ tagsExpanded ? '&#9660;' : '&#9654;' }}</span>
                    <span class="notehub-tags-label">Tags ({{ allTags.length }})</span>
                </div>

                <template v-if="tagsExpanded">
                    <NcAppNavigationItem
                        :name="t('notehub', 'Alle Notizen')"
                        :class="{ active: activeTag === null }"
                        @click="clearTagFilter">
                        <template #counter>
                            <span class="notehub-tag-count">{{ notes.length }}</span>
                        </template>
                    </NcAppNavigationItem>

                    <NcAppNavigationItem
                        v-for="tag in allTags"
                        :key="'tag-' + tag.name"
                        :name="tag.name"
                        :class="{ active: activeTag === tag.name }"
                        @click="filterByTag(tag.name)">
                        <template #counter>
                            <button class="notehub-tag-share-btn"
                                    :title="t('notehub', 'Tag teilen')"
                                    @click.stop="shareTagNotes(tag.name)">&#128279;</button>
                            <span class="notehub-tag-count">{{ tag.count }}</span>
                        </template>
                    </NcAppNavigationItem>
                </template>

                <div v-if="activeTag" class="notehub-active-filter">
                    <span>Filter: <strong>{{ activeTag }}</strong></span>
                    <button class="notehub-active-filter-clear" @click="clearTagFilter">&times;</button>
                </div>

                <div class="notehub-tags-header" @click="toggleContactsNav">
                    <span class="notehub-tags-toggle">{{ contactsExpanded ? '&#9660;' : '&#9654;' }}</span>
                    <span class="notehub-tags-label">&#128101; Kontakte ({{ allContacts.length }})</span>
                </div>

                <template v-if="contactsExpanded">
                    <NcAppNavigationItem
                        v-for="contact in allContacts"
                        :key="'contact-' + contact.name"
                        :name="contact.company ? contact.name + ' \u00b7 ' + contact.company : contact.name"
                        :class="{ active: activeContact === contact.name }"
                        @click="filterByContact(contact.name)">
                        <template #counter>
                            <span class="notehub-tag-count">{{ contact.count }}</span>
                        </template>
                    </NcAppNavigationItem>
                </template>

                <div v-if="activeContact" class="notehub-active-filter">
                    <span>Kontakt: <strong>{{ activeContact }}</strong></span>
                    <button class="notehub-active-filter-clear" @click="clearContactFilter">&times;</button>
                </div>

                <div class="notehub-tags-header" @click="toggleTemplatesNav">
                    <span class="notehub-tags-toggle">{{ templatesExpanded ? '&#9660;' : '&#9654;' }}</span>
                    <span class="notehub-tags-label">Vorlagen ({{ templates.length }})</span>
                </div>

                <template v-if="templatesExpanded">
                    <NcAppNavigationItem
                        v-for="tpl in templates"
                        :key="'tpl-' + tpl.id"
                        :name="tpl.template_name"
                        :class="{ active: currentNote && currentNote.id === tpl.id }"
                        @click="openNote(tpl)">
                        <template #actions>
                            <NcActionButton close-after-click @click="deleteNote(tpl)">
                                {{ t('notehub', 'L&#246;schen') }}
                            </NcActionButton>
                        </template>
                    </NcAppNavigationItem>
                </template>

                <!-- Aufgaben (collapsible) -->
                <div class="notehub-tags-header" @click="toggleTasksNav">
                    <span class="notehub-tags-toggle">{{ tasksExpanded ? '&#9660;' : '&#9654;' }}</span>
                    <span class="notehub-tags-label">&#9745; Aufgaben ({{ taskNotes.length }})</span>
                </div>

                <template v-if="tasksExpanded">
                    <NcAppNavigationItem
                        v-for="note in sortedTaskNotes"
                        :key="'task-' + note.id"
                        :name="(note.shared ? '\uD83D\uDC65 ' : '') + note.title"
                        :class="{
                            active: currentNote && currentNote.id === note.id,
                            'task-done': note.status === 'done',
                            'task-overdue': note.status === 'open' && isOverdue(note),
                            'task-today': note.status === 'open' && isDueToday(note),
                        }"
                        @click="openNote(note)">
                        <template #icon>
                            <span class="notehub-task-dot"
                                  :style="{ color: taskDotColor(note) }">&#9679;</span>
                            <input
                                type="checkbox"
                                class="notehub-nav-checkbox"
                                :checked="note.status === 'done'"
                                @click.stop="toggleTaskInList(note)">
                        </template>
                        <template v-if="note.shared" #counter>
                            <span class="notehub-shared-by">von {{ note.sharedByDisplayName }}</span>
                        </template>
                        <template v-if="!note.shared" #actions>
                            <NcActionButton close-after-click @click="deleteNote(note)">
                                {{ t('notehub', 'L&#246;schen') }}
                            </NcActionButton>
                        </template>
                    </NcAppNavigationItem>
                </template>

                <!-- Sort (collapsible) -->
                <div class="notehub-tags-header" @click="toggleSortNav">
                    <span class="notehub-tags-toggle">{{ sortExpanded ? '&#9660;' : '&#9654;' }}</span>
                    <span class="notehub-tags-label">Sortieren: {{ sortModeLabel }}</span>
                </div>
                <div v-if="sortExpanded" class="notehub-sort-list">
                    <div
                        v-for="opt in sortOptions"
                        :key="'sort-' + opt.value"
                        class="notehub-sort-option"
                        :class="{ 'notehub-sort-option--active': sortMode === opt.value }"
                        @click="setSortMode(opt.value)">
                        {{ opt.label }}
                    </div>
                </div>

                <div v-if="syncing" class="notehub-sync-indicator">
                    {{ t('notehub', 'Synchronisiere Index...') }}
                </div>

                <div class="notehub-notes-header">
                    <span class="notehub-notes-caption">Notizen</span>
                    <button class="notehub-refresh-btn"
                            :class="{ 'notehub-refreshing': syncing }"
                            :disabled="syncing"
                            @click="refreshIndex"
                            title="Index synchronisieren">&#x1f504;</button>
                </div>

                <NcAppNavigationItem
                    v-for="note in sortedPlainNotes"
                    :key="note.id"
                    :name="(note.shared ? '\uD83D\uDC65 ' : '') + note.title"
                    :class="{
                        active: currentNote && currentNote.id === note.id,
                    }"
                    @click="openNote(note)">
                    <template v-if="note.shared" #counter>
                        <span class="notehub-shared-by">von {{ note.sharedByDisplayName }}</span>
                    </template>
                    <template v-if="!note.shared" #actions>
                        <NcActionButton close-after-click @click="deleteNote(note)">
                            {{ t('notehub', 'L&#246;schen') }}
                        </NcActionButton>
                    </template>
                </NcAppNavigationItem>
            </template>
            <div class="notehub-build-info">
                NoteHub v{{ buildVersion }} &middot; Build {{ buildDate }}
            </div>
        </NcAppNavigation>

        <NcAppContent>
            <div v-if="currentNote" class="notehub-editor">
                <div class="notehub-editor-header">
                    <button v-if="isMobile"
                            class="notehub-back-btn"
                            @click="goBackToList">&#9664;</button>
                    <input
                        v-model="currentNote.title"
                        class="notehub-title-input"
                        type="text"
                        :placeholder="t('notehub', 'Titel der Notiz')"
                        :readonly="currentNoteReadonly"
                        @change="saveNote">
                    <span v-if="currentNoteReadonly" class="notehub-readonly-badge">
                        &#128274; {{ t('notehub', 'Nur Lesen') }}
                    </span>
                    <div class="notehub-editor-actions">
                        <NcButton v-if="!currentNoteReadonly && !showPreview" variant="primary" :disabled="saving" @click="saveNote">
                            {{ t('notehub', 'Speichern') }}
                        </NcButton>
                        <button v-if="!currentNote.shared"
                                class="notehub-delete-btn"
                                :title="t('notehub', 'L&#246;schen')"
                                @click="confirmDelete">
                            &#128465;
                        </button>
                        <span v-if="saving" class="notehub-save-indicator">
                            {{ t('notehub', 'Speichert...') }}
                        </span>
                        <span v-else-if="lastSaved" class="notehub-save-indicator">
                            {{ t('notehub', 'Gespeichert') }}
                        </span>
                    </div>
                </div>

                <div v-if="currentNote.template" class="notehub-template-banner">
                    VORLAGE
                </div>

                <div class="notehub-meta-bar">
                    <button v-if="!currentNoteReadonly && currentNote.type !== 'task'"
                            class="notehub-task-toggle-btn"
                            @click="markAsTask">
                        {{ t('notehub', 'Als Aufgabe markieren') }}
                    </button>
                    <button v-if="!currentNoteReadonly && currentNote.type === 'task'"
                            class="notehub-task-toggle-btn notehub-task-toggle-btn--remove"
                            @click="unmarkTask">
                        {{ t('notehub', 'Aufgabe entfernen') }}
                    </button>
                    <button v-if="!currentNoteReadonly && !currentNote.template"
                            class="notehub-task-toggle-btn"
                            @click="markAsTemplate">
                        {{ t('notehub', 'Als Vorlage speichern') }}
                    </button>
                    <button v-if="!currentNoteReadonly && currentNote.template"
                            class="notehub-task-toggle-btn notehub-task-toggle-btn--remove"
                            @click="unmarkTemplate">
                        {{ t('notehub', 'Vorlage entfernen') }}
                    </button>
                    <button v-if="!currentNoteReadonly"
                            class="notehub-task-toggle-btn"
                            @click="openShareDialog">
                        &#128279; {{ t('notehub', 'Teilen') }}
                    </button>
                    <span v-for="tag in (currentNote.tags || [])" :key="tag" class="notehub-tag-chip">
                        {{ tag }}
                        <button v-if="!currentNoteReadonly" class="notehub-tag-remove" @click="removeTag(tag)">&times;</button>
                    </span>
                    <div v-if="!currentNoteReadonly" class="notehub-tag-input-wrapper">
                        <input
                            ref="tagInput"
                            v-model="tagInput"
                            class="notehub-tag-input"
                            type="text"
                            :placeholder="t('notehub', '+ Tag')"
                            @input="onTagInput"
                            @keydown.enter.prevent="addTag(tagInput)"
                            @focus="onTagInput"
                            @blur="hideTagSuggestionsDelayed">
                        <div v-if="showTagSuggestions && tagSuggestions.length > 0" class="notehub-tag-suggestions">
                            <div
                                v-for="suggestion in tagSuggestions"
                                :key="suggestion"
                                class="notehub-tag-suggestion"
                                @mousedown.prevent="addTag(suggestion)">
                                {{ suggestion }}
                            </div>
                        </div>
                    </div>
                    <span v-for="(c, ci) in (currentNote.contacts || [])"
                          :key="'c-' + (c.uid || ci)"
                          class="notehub-contact-chip"
                          :class="{ 'notehub-contact-clickable': !!c.uid }"
                          @click="openContactCard(c)">
                        &#128100; {{ c.name }}<span v-if="c.company" class="notehub-contact-company"> ({{ c.company }})</span>
                        <button v-if="!currentNoteReadonly" class="notehub-tag-remove" @click.stop="removeContact(c)">&times;</button>
                    </span>
                    <div v-if="!currentNoteReadonly" class="notehub-tag-input-wrapper">
                        <input
                            ref="contactInput"
                            v-model="contactInput"
                            class="notehub-tag-input notehub-contact-input"
                            type="text"
                            :placeholder="t('notehub', '+ Kontakt')"
                            @input="onContactInput"
                            @keydown.enter.prevent="addContactFromInput"
                            @focus="onContactInput"
                            @blur="hideContactSuggestionsDelayed">
                        <div v-if="showContactSuggestions && contactSuggestions.length > 0" class="notehub-tag-suggestions" :class="{ 'notehub-tag-suggestions--above': contactSuggestionsAbove }" ref="contactSuggestions">
                            <div
                                v-for="suggestion in contactSuggestions"
                                :key="'cs-' + (suggestion.uid || suggestion.name)"
                                class="notehub-tag-suggestion"
                                @mousedown.prevent="addContact(suggestion)">
                                {{ suggestion.name }}<span v-if="suggestion.company" class="notehub-contact-suggestion-detail"> &middot; {{ suggestion.company }}</span><span v-else-if="suggestion.email" class="notehub-contact-suggestion-detail"> &middot; {{ suggestion.email }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="currentNote.type === 'task' && currentNoteReadonly" class="notehub-task-bar notehub-task-bar--readonly">
                    <span class="notehub-task-bar-item">
                        {{ currentNote.status === 'done' ? '&#9745; ' + t('notehub', 'Erledigt') : '&#9744; ' + t('notehub', 'Offen') }}
                    </span>
                    <span v-if="currentNote.due" class="notehub-task-bar-item">
                        {{ t('notehub', 'Fällig') }}: {{ currentNote.due }}
                    </span>
                    <span v-if="currentNote.priority" class="notehub-task-bar-item">
                        {{ t('notehub', 'Priorität') }}: {{ currentNote.priority === 1 ? t('notehub', 'Hoch') : currentNote.priority === 2 ? t('notehub', 'Mittel') : t('notehub', 'Niedrig') }}
                    </span>
                    <span v-if="currentNote.person" class="notehub-task-bar-item">
                        {{ currentNote.person }}
                    </span>
                </div>
                <div v-if="currentNote.type === 'task' && !currentNoteReadonly" class="notehub-task-bar">
                    <label class="notehub-task-bar-item">
                        <input
                            type="checkbox"
                            :checked="currentNote.status === 'done'"
                            @change="toggleCurrentTask">
                        <span>{{ currentNote.status === 'done' ? t('notehub', 'Erledigt') : t('notehub', 'Offen') }}</span>
                    </label>

                    <label class="notehub-task-bar-item">
                        <span class="notehub-task-bar-label">{{ t('notehub', 'F&#228;llig') }}</span>
                        <input
                            type="date"
                            :value="currentNote.due"
                            @change="updateMeta('due', $event.target.value)">
                    </label>

                    <label class="notehub-task-bar-item">
                        <span class="notehub-task-bar-label">{{ t('notehub', 'Priorit&#228;t') }}</span>
                        <select
                            :value="currentNote.priority || 0"
                            @change="updateMeta('priority', parseInt($event.target.value))">
                            <option :value="0">{{ t('notehub', 'Keine') }}</option>
                            <option :value="1">{{ t('notehub', 'Hoch') }}</option>
                            <option :value="2">{{ t('notehub', 'Mittel') }}</option>
                            <option :value="3">{{ t('notehub', 'Niedrig') }}</option>
                        </select>
                    </label>

                    <label class="notehub-task-bar-item">
                        <span class="notehub-task-bar-label">{{ t('notehub', 'Person') }}</span>
                        <input
                            type="text"
                            :value="currentNote.person || ''"
                            @change="updateMeta('person', $event.target.value)">
                    </label>

                    <div class="notehub-task-bar-item notehub-reminder-field">
                        <span class="notehub-task-bar-label">
                            <span v-if="currentNote.remind" class="notehub-reminder-bell">&#128276;</span>
                            {{ t('notehub', 'Erinnerung') }}
                        </span>
                        <input
                            type="datetime-local"
                            :value="remindDatetimeLocal"
                            @change="updateRemind($event.target.value)">
                        <span v-if="currentNote.reminded" class="notehub-reminded-badge">
                            {{ t('notehub', 'gesendet') }}
                        </span>
                    </div>
                </div>

                <!-- Markdown Toolbar -->
                <div v-if="!currentNoteReadonly && !showPreview" class="notehub-toolbar">
                    <button class="notehub-toolbar-btn" title="Fett (Ctrl+B)" @click="toolbarWrap('**', '**', 'fett')"><b>B</b></button>
                    <button class="notehub-toolbar-btn" title="Kursiv (Ctrl+I)" @click="toolbarWrap('*', '*', 'kursiv')"><i>I</i></button>
                    <button class="notehub-toolbar-btn" title="Durchgestrichen" @click="toolbarWrap('~~', '~~', 'text')"><s>S</s></button>
                    <span class="notehub-toolbar-sep"></span>
                    <button class="notehub-toolbar-btn" title="&#220;berschrift 1" @click="toolbarLinePrefix('# ')">H1</button>
                    <button class="notehub-toolbar-btn" title="&#220;berschrift 2" @click="toolbarLinePrefix('## ')">H2</button>
                    <button class="notehub-toolbar-btn" title="&#220;berschrift 3" @click="toolbarLinePrefix('### ')">H3</button>
                    <span class="notehub-toolbar-sep"></span>
                    <button class="notehub-toolbar-btn" title="Horizontale Linie" @click="toolbarInsertLine('---')">&#9472;</button>
                    <button class="notehub-toolbar-btn" title="Aufz&#228;hlung" @click="toolbarLinePrefix('- ')">&#8226;</button>
                    <button class="notehub-toolbar-btn" title="Nummerierung" @click="toolbarLinePrefix('1. ')">1.</button>
                    <button class="notehub-toolbar-btn" title="Checkbox" @click="toolbarLinePrefix('- [ ] ')">&#9744;</button>
                    <span class="notehub-toolbar-sep"></span>
                    <button class="notehub-toolbar-btn" title="Datum einf&#252;gen" @click="toolbarInsertDate()">&#128197;</button>
                    <button class="notehub-toolbar-btn" title="Datum + Uhrzeit" @click="toolbarInsertDatetime()">&#128336;</button>
                    <button class="notehub-toolbar-btn" title="Link einf&#252;gen" @click="toolbarInsertLink()">&#128279;</button>
                    <span class="notehub-toolbar-sep"></span>
                    <button class="notehub-toolbar-btn"
                            title="Bild einfügen"
                            @click="triggerImageUpload">&#128206;</button>
                    <input ref="imageUpload"
                           type="file"
                           accept="image/*"
                           style="display:none"
                           @change="onImageFileSelected">
                </div>

                <div v-if="currentNote && !currentNoteReadonly" class="notehub-preview-bar">
                    <button class="notehub-toolbar-btn notehub-preview-toggle"
                            :class="{ active: showPreview }"
                            @click="showPreview = !showPreview">
                        {{ showPreview ? '&#9998; Bearbeiten' : '&#128065; Vorschau' }}
                    </button>
                </div>

                <div class="notehub-editor-body">
                    <textarea
                        v-if="!showPreview"
                        ref="editor"
                        v-model="currentNote.content"
                        class="notehub-content-input"
                        :class="{ 'notehub-readonly': currentNoteReadonly }"
                        :placeholder="t('notehub', 'Schreibe hier deine Notiz in Markdown...')"
                        :readonly="currentNoteReadonly"
                        @input="onEditorInput"
                        @click="onEditorClick"
                        @keydown="onEditorKeydown"
                        @paste="onEditorPaste">
                    </textarea>
                    <div v-else
                         class="notehub-preview"
                         v-html="renderedContent"
                         @click="onPreviewClick">
                    </div>
                    <div
                        v-if="showWikiDropdown && wikiMatches.length > 0"
                        class="notehub-wiki-dropdown"
                        :style="wikiDropdownStyle">
                        <div
                            v-for="match in wikiMatches"
                            :key="match.id"
                            class="notehub-wiki-match"
                            @mousedown.prevent="insertWikilink(match.title)">
                            {{ match.title }}
                        </div>
                    </div>
                </div>
                <div class="notehub-backlinks">
                    <div class="notehub-backlinks-header" @click="toggleBacklinks">
                        <span class="notehub-backlinks-toggle">{{ backlinksExpanded ? '&#9660;' : '&#9654;' }}</span>
                        <span>&#128279; {{ t('notehub', 'Backlinks') }} ({{ backlinks.length }})</span>
                    </div>
                    <div v-if="backlinksExpanded" class="notehub-backlinks-list">
                        <div v-if="backlinks.length === 0" class="notehub-backlinks-empty">
                            {{ t('notehub', 'Keine Backlinks') }}
                        </div>
                        <div v-for="bl in backlinks" :key="bl.noteId + '-' + bl.line"
                             class="notehub-backlink-item">
                            <div class="notehub-backlink-title">
                                <a href="#" @click.prevent="openBacklink(bl.noteId)">{{ bl.title }}</a>
                                <span class="notehub-backlink-line">{{ t('notehub', 'Zeile') }} {{ bl.line }}</span>
                            </div>
                            <div class="notehub-backlink-context" v-html="highlightWikilink(bl.context, currentNote.title)"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="notehub-empty">
                <div class="notehub-empty-content">
                    <h2>{{ t('notehub', 'Willkommen bei NoteHub!') }}</h2>
                    <p>{{ t('notehub', 'Erstelle eine neue Notiz oder w&#228;hle eine bestehende aus der Seitenleiste.') }}</p>
                </div>
            </div>
            <!-- Share Dialog -->
            <div v-if="showShareDialog" class="notehub-share-overlay" @click.self="closeShareDialog">
                <div class="notehub-share-dialog">
                    <div class="notehub-share-dialog-header">
                        <h3 v-if="shareTagMode">
                            {{ t('notehub', 'Tag teilen:') }} "{{ shareTagMode }}"
                        </h3>
                        <h3 v-else>
                            {{ t('notehub', 'Teilen:') }} {{ shareDialogNote ? shareDialogNote.title : '' }}
                        </h3>
                        <button class="notehub-share-close" @click="closeShareDialog">&times;</button>
                    </div>

                    <div v-if="!shareTagMode" class="notehub-share-list">
                        <div v-if="shareDialogShares.length === 0" class="notehub-share-empty">
                            {{ t('notehub', 'Noch nicht geteilt') }}
                        </div>
                        <div v-for="s in shareDialogShares" :key="s.id" class="notehub-share-item">
                            <span class="notehub-share-user">{{ s.sharedWithDisplayName }}</span>
                            <span class="notehub-share-perm">{{ (s.permissions & 2) === 0 ? t('notehub', 'Nur Lesen') : t('notehub', 'Lesen & Bearbeiten') }}</span>
                            <button class="notehub-share-remove" @click="removeShare(s.id)">&times;</button>
                        </div>
                    </div>

                    <div class="notehub-share-add">
                        <input
                            v-model="shareUserQuery"
                            type="text"
                            class="notehub-share-search"
                            :placeholder="t('notehub', 'Benutzer suchen...')"
                            @input="debouncedSearchUsers">
                        <div v-if="shareUserResults.length > 0" class="notehub-share-results">
                            <div v-for="u in shareUserResults" :key="u.id"
                                 class="notehub-share-result"
                                 @click="selectShareUser(u)">
                                {{ u.displayName }} ({{ u.id }})
                            </div>
                        </div>
                        <div class="notehub-share-options">
                            <select v-model="sharePermission" class="notehub-share-perm-select">
                                <option :value="1">{{ t('notehub', 'Nur Lesen') }}</option>
                                <option :value="3">{{ t('notehub', 'Lesen & Bearbeiten') }}</option>
                            </select>
                            <NcButton :disabled="shareLoading || !shareUserQuery"
                                      variant="primary"
                                      @click="addShare">
                                {{ shareTagMode ? t('notehub', 'Alle teilen') : t('notehub', 'Teilen') }}
                            </NcButton>
                        </div>
                        <div v-if="shareLoading" class="notehub-share-loading">
                            {{ t('notehub', 'Wird geteilt...') }}
                        </div>
                    </div>
                </div>
            </div>
        </NcAppContent>
    </NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppNavigationNewItem from '@nextcloud/vue/dist/Components/NcAppNavigationNewItem.js'
import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import MarkdownIt from 'markdown-it'

export default {
    name: 'App',

    components: {
        NcContent,
        NcAppNavigation,
        NcAppNavigationItem,
        NcAppNavigationNewItem,
        NcAppNavigationCaption,
        NcAppContent,
        NcActions,
        NcActionButton,
        NcButton,
    },

    data() {
        return {
            notes: [],
            currentNote: null,
            saveTimer: null,
            saving: false,
            savePending: false,
            lastSaved: false,
            searchQuery: '',
            searchResults: null,
            searchTimer: null,
            // Tag navigation
            allTags: [],
            activeTag: null,
            // Tag chips
            tagInput: '',
            tagSuggestions: [],
            showTagSuggestions: false,
            // Tags navigation
            tagsExpanded: false,
            // Tasks navigation
            tasksExpanded: true,
            // Sort mode
            sortMode: 'modified_desc',
            sortExpanded: false,
            // Wikilink autocomplete
            allTitles: [],
            showWikiDropdown: false,
            wikiQuery: '',
            wikiMatches: [],
            wikiDropdownPos: { top: 0, left: 0 },
            wikiCursorStart: -1,
            // Templates
            templates: [],
            templatesExpanded: false,
            // Index sync
            syncing: false,
            indexReady: false,
            // Backlinks
            backlinks: [],
            backlinksExpanded: false,
            // Sharing
            showShareDialog: false,
            shareDialogNote: null,
            shareDialogShares: [],
            shareUserQuery: '',
            shareUserResults: [],
            sharePermission: 1,
            shareLoading: false,
            shareTagMode: null,
            showPreview: false,
            // Contacts
            allContacts: [],
            contactsExpanded: false,
            activeContact: null,
            contactInput: '',
            contactSuggestions: [],
            showContactSuggestions: false,
            contactSearchTimer: null,
            contactSuggestionsAbove: false,
            // Mobile
            mobileShowEditor: false,
            windowWidth: typeof window !== 'undefined' ? window.innerWidth : 1024,
            buildVersion: typeof __BUILD_VERSION__ !== 'undefined' ? __BUILD_VERSION__ : '',
            buildDate: typeof __BUILD_DATE__ !== 'undefined' ? __BUILD_DATE__ : '',
        }
    },

    computed: {
        taskNotes() {
            const list = this.searchResults !== null ? this.searchResults : this.notes
            return list.filter(n => n.type === 'task')
        },

        plainNotes() {
            const list = this.searchResults !== null ? this.searchResults : this.notes
            return list.filter(n => n.type !== 'task')
        },

        sortedTaskNotes() {
            const today = new Date().toISOString().slice(0, 10)
            const tasks = [...this.taskNotes]
            const doneTasks = tasks.filter(n => n.status === 'done')
            const activeTasks = tasks.filter(n => n.status !== 'done')

            activeTasks.sort((a, b) => {
                if (this.sortMode !== 'due_asc' && this.sortMode !== 'priority_desc') {
                    const aOverdue = (a.status === 'open' && a.due && a.due < today) ? 0 : 1
                    const bOverdue = (b.status === 'open' && b.due && b.due < today) ? 0 : 1
                    if (aOverdue !== bOverdue) return aOverdue - bOverdue
                }
                return this.compareBySort(a, b)
            })
            doneTasks.sort((a, b) => this.compareBySort(a, b))

            return [...activeTasks, ...doneTasks]
        },

        sortedPlainNotes() {
            const notes = [...this.plainNotes]
            if (this.sortMode === 'due_asc' || this.sortMode === 'priority_desc') {
                notes.sort((a, b) =>
                    (a.title || '').localeCompare(b.title || '', 'de', { sensitivity: 'base' })
                )
            } else {
                notes.sort((a, b) => this.compareBySort(a, b))
            }
            return notes
        },

        sortModeLabel() {
            const labels = {
                modified_desc: 'Zuletzt bearbeitet',
                modified_asc: 'Älteste Bearbeitung',
                title_asc: 'Titel A\u2013Z',
                title_desc: 'Titel Z\u2013A',
                created_desc: 'Neueste zuerst',
                created_asc: 'Älteste zuerst',
                due_asc: 'Fälligkeit',
                priority_desc: 'Priorität',
            }
            return labels[this.sortMode] || this.sortMode
        },

        sortOptions() {
            return [
                { value: 'modified_desc', label: 'Zuletzt bearbeitet' },
                { value: 'modified_asc', label: 'Älteste Bearbeitung' },
                { value: 'title_asc', label: 'Titel A\u2013Z' },
                { value: 'title_desc', label: 'Titel Z\u2013A' },
                { value: 'created_desc', label: 'Neueste zuerst' },
                { value: 'created_asc', label: 'Älteste zuerst' },
                { value: 'due_asc', label: 'Fälligkeit' },
                { value: 'priority_desc', label: 'Priorität' },
            ]
        },

        remindDatetimeLocal() {
            if (!this.currentNote || !this.currentNote.remind) return ''
            const r = this.currentNote.remind
            if (r.length > 10) {
                return r.replace(' ', 'T')
            }
            return r + 'T00:00'
        },

        wikiDropdownStyle() {
            return {
                top: this.wikiDropdownPos.top + 'px',
                left: this.wikiDropdownPos.left + 'px',
            }
        },

        isMobile() {
            return this.windowWidth < 768
        },

        currentNoteReadonly() {
            if (!this.currentNote) return false
            if (this.currentNote.shared && (this.currentNote.permissions & 2) === 0) return true
            return false
        },

        renderedContent() {
            if (!this.currentNote) return ''
            const md = new MarkdownIt({ html: false, linkify: true, breaks: true })

            // Wikilinks: [[Titel]] → clickable link
            md.renderer.rules.text = function(tokens, idx) {
                const content = tokens[idx].content
                // Split on wikilinks and escape non-link parts
                const parts = content.split(/(\[\[[^\]]+\]\])/g)
                return parts.map(part => {
                    const m = part.match(/^\[\[([^\]]+)\]\]$/)
                    if (m) {
                        return '<a href="#" class="notehub-wikilink" data-title="' + md.utils.escapeHtml(m[1]) + '">' + md.utils.escapeHtml(m[1]) + '</a>'
                    }
                    return md.utils.escapeHtml(part)
                }).join('')
            }

            // Relative images: images/xxx.png → API URL
            const defaultImageRender = md.renderer.rules.image || function(tokens, idx, options, env, self) {
                return self.renderToken(tokens, idx, options)
            }
            md.renderer.rules.image = function(tokens, idx, options, env, self) {
                const token = tokens[idx]
                const src = token.attrGet('src')
                if (src && !src.startsWith('http') && !src.startsWith('/')) {
                    // Strip "images/" prefix — route is /api/images/{filename}
                    const filename = src.startsWith('images/') ? src.substring(7) : src
                    token.attrSet('src', generateUrl('/apps/notehub/api/images/{filename}', { filename }))
                }
                return defaultImageRender(tokens, idx, options, env, self)
            }

            return md.render(this.currentNote.content || '')
        },
    },

    async mounted() {
        this.tagsExpanded = localStorage.getItem('notehub-tags-expanded') === 'true'
        this.templatesExpanded = localStorage.getItem('notehub-templates-expanded') === 'true'
        this.tasksExpanded = localStorage.getItem('notehub-tasks-expanded') !== 'false'
        this.backlinksExpanded = localStorage.getItem('notehub-backlinks-expanded') === 'true'
        this.sortMode = localStorage.getItem('notehub-sort-mode') || 'modified_desc'
        const sortExpandedStored = localStorage.getItem('notehub-sort-expanded')
        if (sortExpandedStored !== null) {
            this.sortExpanded = sortExpandedStored === 'true'
        } else {
            this.sortExpanded = window.innerWidth > 767
        }

        // Mobile resize listener
        this._onResize = () => { this.windowWidth = window.innerWidth }
        window.addEventListener('resize', this._onResize)

        // Ensure DB index is ready before loading data
        try {
            const status = await axios.get(generateUrl('/apps/notehub/api/index/status'))
            if (!status.data.indexed) {
                this.syncing = true
                await axios.post(generateUrl('/apps/notehub/api/index/sync'))
                this.syncing = false
            }
        } catch (error) {
            console.error('Index sync check failed', error)
            this.syncing = false
        }
        this.indexReady = true

        this.contactsExpanded = localStorage.getItem('notehub-contacts-expanded') === 'true'

        await this.loadNotes()
        await this.loadTags()
        this.loadTitles()
        this.loadTemplates()
        this.loadContacts()
    },

    beforeDestroy() {
        if (this._onResize) {
            window.removeEventListener('resize', this._onResize)
        }
    },

    methods: {
        t,

        compareBySort(a, b) {
            switch (this.sortMode) {
                case 'title_asc':
                    return (a.title || '').localeCompare(b.title || '', 'de', { sensitivity: 'base' })
                case 'title_desc':
                    return (b.title || '').localeCompare(a.title || '', 'de', { sensitivity: 'base' })
                case 'modified_asc':
                    return a.modified - b.modified
                case 'created_desc':
                    return (b.start || '').localeCompare(a.start || '')
                case 'created_asc':
                    return (a.start || '').localeCompare(b.start || '')
                case 'due_asc': {
                    const aDue = a.due || '\uffff'
                    const bDue = b.due || '\uffff'
                    if (aDue !== bDue) return aDue.localeCompare(bDue)
                    return (a.title || '').localeCompare(b.title || '', 'de', { sensitivity: 'base' })
                }
                case 'priority_desc': {
                    const mappedA = a.priority === 0 ? 99 : a.priority
                    const mappedB = b.priority === 0 ? 99 : b.priority
                    if (mappedA !== mappedB) return mappedA - mappedB
                    return (a.title || '').localeCompare(b.title || '', 'de', { sensitivity: 'base' })
                }
                default:
                    return b.modified - a.modified
            }
        },

        onSortChange() {
            localStorage.setItem('notehub-sort-mode', this.sortMode)
        },

        toggleSortNav() {
            this.sortExpanded = !this.sortExpanded
            localStorage.setItem('notehub-sort-expanded', String(this.sortExpanded))
        },

        setSortMode(value) {
            this.sortMode = value
            localStorage.setItem('notehub-sort-mode', value)
        },

        async refreshIndex() {
            this.syncing = true
            try {
                await axios.post(generateUrl('/apps/notehub/api/index/sync'))
                await this.loadNotes()
                await this.loadTags()
            } catch (e) {
                console.error('Sync failed', e)
            }
            this.syncing = false
        },

        isOverdue(note) {
            if (!note.due) return false
            return note.due < new Date().toISOString().slice(0, 10)
        },

        isDueToday(note) {
            if (!note.due) return false
            return note.due === new Date().toISOString().slice(0, 10)
        },

        // ── Data loading ──────────────────────────────────

        async loadNotes() {
            try {
                const params = {}
                if (this.activeTag) {
                    params.tag = this.activeTag
                }
                const response = await axios.get(generateUrl('/apps/notehub/api/notes'), { params })
                this.notes = response.data
            } catch (error) {
                showError(t('notehub', 'Fehler beim Laden der Notizen'))
                console.error(error)
            }
        },

        async loadTags() {
            try {
                const response = await axios.get(generateUrl('/apps/notehub/api/tags'))
                this.allTags = response.data
            } catch (error) {
                console.error('Failed to load tags', error)
            }
        },

        async loadTitles() {
            try {
                const response = await axios.get(generateUrl('/apps/notehub/api/notes/titles'))
                this.allTitles = response.data
            } catch (error) {
                console.error('Failed to load titles', error)
            }
        },

        // ── Tag navigation ────────────────────────────────

        toggleTagsNav() {
            this.tagsExpanded = !this.tagsExpanded
            localStorage.setItem('notehub-tags-expanded', String(this.tagsExpanded))
        },

        toggleTemplatesNav() {
            this.templatesExpanded = !this.templatesExpanded
            localStorage.setItem('notehub-templates-expanded', String(this.templatesExpanded))
        },

        toggleTasksNav() {
            this.tasksExpanded = !this.tasksExpanded
            localStorage.setItem('notehub-tasks-expanded', String(this.tasksExpanded))
        },

        filterByTag(tag) {
            this.activeTag = tag
            this.activeContact = null
            this.searchQuery = ''
            this.searchResults = null
            if (this.isMobile) {
                this.mobileShowEditor = false
            }
            this.loadNotes()
        },

        clearTagFilter() {
            this.activeTag = null
            this.searchQuery = ''
            this.searchResults = null
            if (this.isMobile) {
                this.mobileShowEditor = false
            }
            this.loadNotes()
        },

        // ── Tag chips ─────────────────────────────────────

        addTag(tag) {
            tag = (tag || '').trim().toLowerCase()
            if (!tag || !this.currentNote) return
            const tags = this.currentNote.tags || []
            if (tags.includes(tag)) {
                this.tagInput = ''
                this.showTagSuggestions = false
                return
            }
            this.$set(this.currentNote, 'tags', [...tags, tag])
            this.tagInput = ''
            this.showTagSuggestions = false
            this.saveNote().then(() => {
                this.loadTags()
            })
        },

        removeTag(tag) {
            if (!this.currentNote) return
            const tags = (this.currentNote.tags || []).filter(t => t !== tag)
            this.$set(this.currentNote, 'tags', tags)
            this.saveNote().then(() => {
                this.loadTags()
            })
        },

        onTagInput() {
            const query = this.tagInput.trim().toLowerCase()
            if (!query) {
                this.tagSuggestions = this.allTags.map(t => t.name)
                this.showTagSuggestions = this.tagSuggestions.length > 0
                return
            }
            const currentTags = this.currentNote ? (this.currentNote.tags || []) : []
            this.tagSuggestions = this.allTags
                .map(t => t.name)
                .filter(name => name.includes(query) && !currentTags.includes(name))
            this.showTagSuggestions = this.tagSuggestions.length > 0
        },

        hideTagSuggestionsDelayed() {
            setTimeout(() => {
                this.showTagSuggestions = false
            }, 200)
        },

        // ── Contacts navigation ──────────────────────────

        toggleContactsNav() {
            this.contactsExpanded = !this.contactsExpanded
            localStorage.setItem('notehub-contacts-expanded', String(this.contactsExpanded))
        },

        async loadContacts() {
            try {
                const response = await axios.get(generateUrl('/apps/notehub/api/contacts'))
                this.allContacts = response.data
            } catch (error) {
                console.error('Failed to load contacts', error)
            }
        },

        async filterByContact(name) {
            this.activeContact = name
            this.activeTag = null
            this.searchQuery = ''
            this.searchResults = null
            if (this.isMobile) {
                this.mobileShowEditor = false
            }
            try {
                const response = await axios.get(
                    generateUrl('/apps/notehub/api/contacts/{name}/notes', { name })
                )
                this.notes = response.data
            } catch (error) {
                showError(t('notehub', 'Fehler beim Laden der Kontakt-Notizen'))
                console.error(error)
            }
        },

        clearContactFilter() {
            this.activeContact = null
            if (this.isMobile) {
                this.mobileShowEditor = false
            }
            this.loadNotes()
        },

        // ── Contact chips ────────────────────────────────

        onContactInput() {
            const query = this.contactInput.trim()
            if (!query || query.length < 1) {
                this.contactSuggestions = []
                this.showContactSuggestions = false
                return
            }
            if (this.contactSearchTimer) {
                clearTimeout(this.contactSearchTimer)
            }
            this.contactSearchTimer = setTimeout(() => {
                this.searchAddressBook(query)
            }, 300)
        },

        async searchAddressBook(query) {
            try {
                const response = await axios.get(
                    generateUrl('/apps/notehub/api/contacts/search'),
                    { params: { q: query } }
                )
                const currentContacts = this.currentNote ? (this.currentNote.contacts || []) : []
                const currentNames = currentContacts.map(c => c.name)
                this.contactSuggestions = response.data.filter(c => !currentNames.includes(c.name))
                this.showContactSuggestions = this.contactSuggestions.length > 0
                if (this.showContactSuggestions) {
                    this.$nextTick(() => {
                        this.positionContactSuggestions()
                    })
                }
            } catch (error) {
                console.error('Contact search failed', error)
                this.contactSuggestions = []
                this.showContactSuggestions = false
            }
        },

        addContact(contact) {
            if (!this.currentNote) return
            const contacts = this.currentNote.contacts || []
            // Deduplicate by UID if available, otherwise by name+company
            const isDuplicate = contact.uid
                ? contacts.some(c => c.uid === contact.uid)
                : contacts.some(c => c.name === contact.name && (c.company || '') === (contact.company || ''))
            if (isDuplicate) {
                this.contactInput = ''
                this.showContactSuggestions = false
                showError(t('notehub', 'Kontakt bereits verknüpft'))
                return
            }
            const newContact = { name: contact.name, company: contact.company || '' }
            if (contact.uid) {
                newContact.uid = contact.uid
            }
            this.$set(this.currentNote, 'contacts', [...contacts, newContact])
            this.contactInput = ''
            this.showContactSuggestions = false
            this.saveNote().then(() => {
                this.loadContacts()
            })
        },

        addContactFromInput() {
            const name = this.contactInput.trim()
            if (!name) return
            if (this.contactSuggestions.length > 0) {
                this.addContact(this.contactSuggestions[0])
            } else {
                this.addContact({ name, company: '', uid: '' })
            }
        },

        removeContact(contact) {
            if (!this.currentNote) return
            const contacts = (this.currentNote.contacts || []).filter(c => {
                if (contact.uid && c.uid) return c.uid !== contact.uid
                return c.name !== contact.name || (c.company || '') !== (contact.company || '')
            })
            this.$set(this.currentNote, 'contacts', contacts)
            this.saveNote().then(() => {
                this.loadContacts()
            })
        },

        openContactCard(contact) {
            if (!contact.uid) return
            const url = generateUrl('/apps/contacts/All%20contacts/{uid}~contacts', { uid: contact.uid })
            window.open(url, '_blank')
        },

        hideContactSuggestionsDelayed() {
            setTimeout(() => {
                this.showContactSuggestions = false
                this.contactSuggestionsAbove = false
            }, 200)
        },

        positionContactSuggestions() {
            const dropdown = this.$refs.contactSuggestions
            const input = this.$refs.contactInput
            if (!dropdown || !input) return
            const inputRect = input.getBoundingClientRect()
            const viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight
            const spaceBelow = viewportHeight - inputRect.bottom
            const spaceAbove = inputRect.top
            // Flip above if less than 120px below and more space above
            this.contactSuggestionsAbove = spaceBelow < 120 && spaceAbove > spaceBelow
        },

        // ── Wikilink autocomplete ─────────────────────────

        onEditorInput() {
            this.debouncedSave()
            this.checkWikilink()
        },

        checkWikilink() {
            const textarea = this.$refs.editor
            if (!textarea) return

            const pos = textarea.selectionStart
            const text = textarea.value.substring(0, pos)

            const lastOpen = text.lastIndexOf('[[')
            if (lastOpen === -1) {
                this.closeWikiDropdown()
                return
            }

            const afterOpen = text.substring(lastOpen + 2)
            if (afterOpen.includes(']]')) {
                this.closeWikiDropdown()
                return
            }

            this.wikiQuery = afterOpen
            this.wikiCursorStart = lastOpen

            if (this.wikiQuery.length === 0) {
                this.wikiMatches = this.allTitles.slice(0, 10)
            } else {
                const q = this.wikiQuery.toLowerCase()
                this.wikiMatches = this.allTitles
                    .filter(t => t.title.toLowerCase().includes(q))
                    .slice(0, 10)
            }

            if (this.wikiMatches.length > 0) {
                this.positionWikiDropdown(textarea, pos)
                this.showWikiDropdown = true
            } else {
                this.showWikiDropdown = false
            }
        },

        positionWikiDropdown(textarea, cursorPos) {
            const rect = textarea.getBoundingClientRect()
            const textBeforeCursor = textarea.value.substring(0, cursorPos)
            const lines = textBeforeCursor.split('\n')
            const lineHeight = 22.4
            const lineNum = lines.length
            const scrollTop = textarea.scrollTop

            this.wikiDropdownPos = {
                top: Math.min((lineNum * lineHeight) - scrollTop + 8, rect.height - 40),
                left: Math.min(lines[lines.length - 1].length * 8.4, rect.width - 200),
            }
        },

        insertWikilink(title) {
            const textarea = this.$refs.editor
            if (!textarea) return

            const content = this.currentNote.content
            const before = content.substring(0, this.wikiCursorStart)
            const after = content.substring(textarea.selectionStart)

            this.currentNote.content = before + '[[' + title + ']]' + after
            this.closeWikiDropdown()
            this.debouncedSave()

            this.$nextTick(() => {
                const newPos = before.length + title.length + 4
                textarea.setSelectionRange(newPos, newPos)
                textarea.focus()
            })
        },

        closeWikiDropdown() {
            this.showWikiDropdown = false
            this.wikiMatches = []
            this.wikiQuery = ''
            this.wikiCursorStart = -1
        },

        onEditorKeydown(event) {
            if (event.key === 'Escape') {
                this.closeWikiDropdown()
            }
        },

        onPreviewClick(event) {
            const link = event.target.closest('.notehub-wikilink')
            if (link) {
                event.preventDefault()
                const title = link.dataset.title
                this.openNoteByTitle(title)
            }
        },

        async onEditorPaste(event) {
            const items = event.clipboardData?.items
            if (!items) return

            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    event.preventDefault()
                    const blob = item.getAsFile()
                    if (!blob) return
                    await this.uploadAndInsertImage(blob)
                    return
                }
            }
        },

        triggerImageUpload() {
            this.$refs.imageUpload?.click()
        },

        async onImageFileSelected(event) {
            const file = event.target.files?.[0]
            if (!file) return
            await this.uploadAndInsertImage(file)
            event.target.value = ''
        },

        async uploadAndInsertImage(blob) {
            if (!this.currentNote) return
            const formData = new FormData()
            formData.append('image', blob, blob.name || 'paste.png')

            try {
                const response = await axios.post(
                    generateUrl('/apps/notehub/api/notes/{id}/upload-image', { id: this.currentNote.id }),
                    formData,
                    { headers: { 'Content-Type': 'multipart/form-data' } }
                )
                const path = response.data.path
                const markdown = '![Bild](' + path + ')'

                // Insert at cursor position
                const textarea = this.$refs.editor
                if (textarea) {
                    const start = textarea.selectionStart
                    const before = this.currentNote.content.substring(0, start)
                    const after = this.currentNote.content.substring(start)
                    this.currentNote.content = before + markdown + '\n' + after
                } else {
                    this.currentNote.content += '\n' + markdown
                }
                this.debouncedSave()
                showSuccess(t('notehub', 'Bild eingefügt'))
            } catch (error) {
                const msg = error?.response?.data?.error || error.message
                showError(t('notehub', 'Bild-Upload fehlgeschlagen: ') + msg)
            }
        },

        onEditorClick(event) {
            if (!event.ctrlKey && !event.metaKey) return

            const textarea = this.$refs.editor
            if (!textarea) return

            const pos = textarea.selectionStart
            const content = textarea.value

            const before = content.substring(0, pos)
            const after = content.substring(pos)

            const openBefore = before.lastIndexOf('[[')
            const closeBefore = before.lastIndexOf(']]')

            if (openBefore === -1 || (closeBefore !== -1 && closeBefore > openBefore)) return

            const closeAfter = after.indexOf(']]')
            if (closeAfter === -1) return

            const title = content.substring(openBefore + 2, pos + closeAfter)
            if (title) {
                this.openNoteByTitle(title)
            }
        },

        openNoteByTitle(title) {
            const match = this.notes.find(n => n.title === title)
                || this.notes.find(n => n.title.toLowerCase() === title.toLowerCase())
            if (match) {
                this.openNote(match)
            } else {
                showError(t('notehub', 'Notiz nicht gefunden: ') + title)
            }
        },

        // ── Markdown Toolbar ──────────────────────────────

        toolbarWrap(prefix, suffix, placeholder) {
            const textarea = this.$refs.editor
            if (!textarea) return

            const start = textarea.selectionStart
            const end = textarea.selectionEnd
            const content = this.currentNote.content
            const selected = content.substring(start, end)

            if (selected) {
                this.currentNote.content = content.substring(0, start) + prefix + selected + suffix + content.substring(end)
                this.$nextTick(() => {
                    textarea.setSelectionRange(start + prefix.length, end + prefix.length)
                    textarea.focus()
                })
            } else {
                const insert = prefix + placeholder + suffix
                this.currentNote.content = content.substring(0, start) + insert + content.substring(end)
                this.$nextTick(() => {
                    textarea.setSelectionRange(start + prefix.length, start + prefix.length + placeholder.length)
                    textarea.focus()
                })
            }
            this.debouncedSave()
        },

        toolbarLinePrefix(prefix) {
            const textarea = this.$refs.editor
            if (!textarea) return

            const start = textarea.selectionStart
            const content = this.currentNote.content
            const lineStart = content.lastIndexOf('\n', start - 1) + 1

            this.currentNote.content = content.substring(0, lineStart) + prefix + content.substring(lineStart)
            this.$nextTick(() => {
                textarea.setSelectionRange(start + prefix.length, start + prefix.length)
                textarea.focus()
            })
            this.debouncedSave()
        },

        toolbarInsertLine(text) {
            const textarea = this.$refs.editor
            if (!textarea) return

            const start = textarea.selectionStart
            const content = this.currentNote.content
            const before = content.substring(0, start)
            const needNewlineBefore = before.length > 0 && !before.endsWith('\n') ? '\n' : ''
            const insert = needNewlineBefore + text + '\n'

            this.currentNote.content = before + insert + content.substring(start)
            this.$nextTick(() => {
                const pos = start + insert.length
                textarea.setSelectionRange(pos, pos)
                textarea.focus()
            })
            this.debouncedSave()
        },

        toolbarInsertDate() {
            const textarea = this.$refs.editor
            if (!textarea) return

            const now = new Date()
            const date = now.toISOString().slice(0, 10)
            const start = textarea.selectionStart
            const content = this.currentNote.content

            this.currentNote.content = content.substring(0, start) + date + content.substring(start)
            this.$nextTick(() => {
                const pos = start + date.length
                textarea.setSelectionRange(pos, pos)
                textarea.focus()
            })
            this.debouncedSave()
        },

        toolbarInsertDatetime() {
            const textarea = this.$refs.editor
            if (!textarea) return

            const now = new Date()
            const dt = now.toISOString().slice(0, 10) + ' ' + now.toTimeString().slice(0, 5)
            const start = textarea.selectionStart
            const content = this.currentNote.content

            this.currentNote.content = content.substring(0, start) + dt + content.substring(start)
            this.$nextTick(() => {
                const pos = start + dt.length
                textarea.setSelectionRange(pos, pos)
                textarea.focus()
            })
            this.debouncedSave()
        },

        toolbarInsertLink() {
            const textarea = this.$refs.editor
            if (!textarea) return

            const url = prompt(t('notehub', 'URL eingeben:'), 'https://')
            if (!url) return

            const start = textarea.selectionStart
            const end = textarea.selectionEnd
            const content = this.currentNote.content
            const selected = content.substring(start, end)
            const linkText = selected || 'Link'
            const insert = '[' + linkText + '](' + url + ')'

            this.currentNote.content = content.substring(0, start) + insert + content.substring(end)
            this.$nextTick(() => {
                const pos = start + insert.length
                textarea.setSelectionRange(pos, pos)
                textarea.focus()
            })
            this.debouncedSave()
        },

        // ── Note CRUD ─────────────────────────────────────

        async openNote(note) {
            try {
                const response = await axios.get(
                    generateUrl('/apps/notehub/api/notes/{id}', { id: note.id })
                )
                this.currentNote = response.data
                this.lastSaved = false
                this.tagInput = ''
                this.showTagSuggestions = false
                this.showPreview = false
                this.closeWikiDropdown()
                this.loadBacklinks(note.id)
                if (this.isMobile) {
                    this.mobileShowEditor = true
                }
            } catch (error) {
                showError(t('notehub', 'Fehler beim &#214;ffnen der Notiz'))
                console.error(error)
            }
        },

        goBackToList() {
            this.mobileShowEditor = false
        },

        onNewNoteBlank() {
            const title = prompt(t('notehub', 'Name der neuen Notiz:'), 'Neue Notiz')
            if (title !== null) {
                this.onNewNote(title)
            }
        },

        onNewTaskBlank() {
            const title = prompt(t('notehub', 'Name der neuen Aufgabe:'), 'Neue Aufgabe')
            if (title !== null) {
                this.onNewTask(title)
            }
        },

        async onNewNote(title) {
            if (!title || title.trim() === '') {
                title = 'Neue Notiz'
            }

            try {
                const response = await axios.post(
                    generateUrl('/apps/notehub/api/notes'),
                    { title, content: '', folder: '', type: 'note' }
                )
                this.notes.push(response.data)
                this.currentNote = response.data
                showSuccess(t('notehub', 'Notiz erstellt'))
            } catch (error) {
                showError(t('notehub', 'Fehler beim Erstellen der Notiz'))
                console.error(error)
            }
        },

        async onNewTask(title) {
            if (!title || title.trim() === '') {
                title = 'Neue Aufgabe'
            }

            try {
                const response = await axios.post(
                    generateUrl('/apps/notehub/api/notes'),
                    { title, content: '', folder: '', type: 'task' }
                )
                this.notes.push(response.data)
                this.currentNote = response.data
                showSuccess(t('notehub', 'Aufgabe erstellt'))
            } catch (error) {
                showError(t('notehub', 'Fehler beim Erstellen der Aufgabe'))
                console.error(error)
            }
        },

        async onNewFromTemplate(templateId, type) {
            try {
                const response = await axios.post(
                    generateUrl('/apps/notehub/api/notes/from-template'),
                    { templateId, type }
                )
                this.notes.push(response.data)
                this.currentNote = response.data
                showSuccess(t('notehub', type === 'task' ? 'Aufgabe aus Vorlage erstellt' : 'Notiz aus Vorlage erstellt'))
            } catch (error) {
                showError(t('notehub', 'Fehler beim Erstellen aus Vorlage'))
                console.error(error)
            }
        },

        async loadTemplates() {
            try {
                const response = await axios.get(generateUrl('/apps/notehub/api/templates'))
                this.templates = response.data
            } catch (error) {
                console.error('Failed to load templates', error)
            }
        },

        async markAsTemplate() {
            if (!this.currentNote) return
            const name = prompt(t('notehub', 'Name der Vorlage:'), this.currentNote.title)
            if (name === null) return
            try {
                const response = await axios.put(
                    generateUrl('/apps/notehub/api/notes/{id}/set-template', { id: this.currentNote.id }),
                    { templateName: name }
                )
                this.currentNote = { ...this.currentNote, ...response.data }
                this.notes = this.notes.filter(n => n.id !== this.currentNote.id)
                this.loadTemplates()
            } catch (error) {
                showError(t('notehub', 'Fehler beim Speichern als Vorlage'))
                console.error(error)
            }
        },

        async unmarkTemplate() {
            if (!this.currentNote) return
            try {
                const response = await axios.put(
                    generateUrl('/apps/notehub/api/notes/{id}/unset-template', { id: this.currentNote.id })
                )
                this.currentNote = { ...this.currentNote, ...response.data }
                this.notes.push({
                    id: this.currentNote.id,
                    title: this.currentNote.title,
                    folder: this.currentNote.folder,
                    modified: this.currentNote.modified,
                    type: this.currentNote.type,
                    status: this.currentNote.status,
                    due: this.currentNote.due,
                    priority: this.currentNote.priority,
                    tags: this.currentNote.tags,
                    remind: this.currentNote.remind,
                    person: this.currentNote.person,
                    template: false,
                    template_name: '',
                })
                this.loadTemplates()
            } catch (error) {
                showError(t('notehub', 'Fehler beim Entfernen der Vorlage'))
                console.error(error)
            }
        },

        async toggleTaskInList(note) {
            try {
                const response = await axios.put(
                    generateUrl('/apps/notehub/api/notes/{id}/toggle-task', { id: note.id })
                )
                const idx = this.notes.findIndex(n => n.id === note.id)
                if (idx !== -1) {
                    this.$set(this.notes, idx, { ...this.notes[idx], ...response.data })
                }
                if (this.currentNote && this.currentNote.id === note.id) {
                    this.currentNote = { ...this.currentNote, ...response.data }
                }
            } catch (error) {
                showError(t('notehub', 'Fehler beim Umschalten der Aufgabe'))
                console.error(error)
            }
        },

        async toggleCurrentTask() {
            if (!this.currentNote) return
            try {
                const response = await axios.put(
                    generateUrl('/apps/notehub/api/notes/{id}/toggle-task', { id: this.currentNote.id })
                )
                this.currentNote = { ...this.currentNote, ...response.data }
                const idx = this.notes.findIndex(n => n.id === this.currentNote.id)
                if (idx !== -1) {
                    this.$set(this.notes, idx, { ...this.notes[idx], status: response.data.status })
                }
            } catch (error) {
                showError(t('notehub', 'Fehler beim Umschalten der Aufgabe'))
                console.error(error)
            }
        },

        taskDotColor(note) {
            if (note.type !== 'task') return null
            if (note.status === 'done') return 'rgb(52, 152, 219)'
            if (!note.due) return 'rgb(180, 180, 180)'

            const now = Date.now()
            const due = new Date(note.due + 'T23:59:59').getTime()
            const start = note.start ? new Date(note.start + 'T00:00:00').getTime() : note.modified * 1000

            if (due <= start) return 'rgb(255, 0, 0)'
            const progress = Math.max(0, (now - start) / (due - start))
            if (progress > 1) return 'rgb(255, 0, 0)'

            let r, g
            if (progress <= 0.5) {
                r = Math.round(progress * 2 * 255)
                g = 255
            } else {
                r = 255
                g = Math.round((1 - progress) * 2 * 255)
            }
            return `rgb(${r}, ${g}, 0)`
        },

        async markAsTask() {
            if (!this.currentNote) return
            try {
                const response = await axios.put(
                    generateUrl('/apps/notehub/api/notes/{id}/set-task', { id: this.currentNote.id })
                )
                this.currentNote = { ...this.currentNote, ...response.data }
                const idx = this.notes.findIndex(n => n.id === this.currentNote.id)
                if (idx !== -1) {
                    this.$set(this.notes, idx, { ...this.notes[idx], ...response.data })
                }
            } catch (error) {
                showError(t('notehub', 'Fehler beim Markieren als Aufgabe'))
                console.error(error)
            }
        },

        async unmarkTask() {
            if (!this.currentNote) return
            try {
                const response = await axios.put(
                    generateUrl('/apps/notehub/api/notes/{id}/unset-task', { id: this.currentNote.id })
                )
                this.currentNote = { ...this.currentNote, ...response.data }
                const idx = this.notes.findIndex(n => n.id === this.currentNote.id)
                if (idx !== -1) {
                    this.$set(this.notes, idx, { ...this.notes[idx], ...response.data })
                }
            } catch (error) {
                showError(t('notehub', 'Fehler beim Entfernen der Aufgabe'))
                console.error(error)
            }
        },

        updateMeta(field, value) {
            if (!this.currentNote) return
            this.$set(this.currentNote, field, value)
            this.saveNote()
        },

        updateRemind(value) {
            if (!this.currentNote) return
            const remind = value ? value.replace('T', ' ') : ''
            this.$set(this.currentNote, 'remind', remind)
            this.$set(this.currentNote, 'reminded', false)
            this.saveNote()
        },

        // ── Save with lock + queue ────────────────────────

        async saveNote() {
            if (!this.currentNote) return
            if (this.currentNoteReadonly) return

            // If a save is already in progress, mark pending and return
            if (this.saving) {
                this.savePending = true
                return
            }

            this.saving = true
            this.lastSaved = false

            try {
                const payload = {
                    title: this.currentNote.title,
                    content: this.currentNote.content,
                    folder: this.currentNote.folder || '',
                    tags: this.currentNote.tags || [],
                    contacts: this.currentNote.contacts || [],
                }

                if (this.currentNote.type === 'task') {
                    payload.type = this.currentNote.type
                    payload.status = this.currentNote.status
                    payload.due = this.currentNote.due || ''
                    payload.priority = this.currentNote.priority || 0
                    payload.remind = this.currentNote.remind || ''
                    payload.person = this.currentNote.person || ''
                }

                const response = await axios.put(
                    generateUrl('/apps/notehub/api/notes/{id}', { id: this.currentNote.id }),
                    payload
                )
                const idx = this.notes.findIndex(n => n.id === this.currentNote.id)
                if (idx !== -1) {
                    this.$set(this.notes, idx, {
                        ...this.notes[idx],
                        title: response.data.title,
                        modified: response.data.modified,
                        type: response.data.type,
                        status: response.data.status,
                        due: response.data.due,
                        priority: response.data.priority,
                        tags: response.data.tags,
                        remind: response.data.remind,
                        person: response.data.person,
                        contacts: response.data.contacts,
                    })
                }
                this.lastSaved = true
                this.loadBacklinks(this.currentNote.id)
            } catch (error) {
                showError(t('notehub', 'Fehler beim Speichern'))
                console.error(error)
            } finally {
                this.saving = false
                // If changes came in during save, save again
                if (this.savePending) {
                    this.savePending = false
                    this.$nextTick(() => this.saveNote())
                }
            }
        },

        async loadBacklinks(noteId) {
            try {
                const response = await axios.get(
                    generateUrl('/apps/notehub/api/notes/{id}/backlinks', { id: noteId })
                )
                this.backlinks = response.data
            } catch (error) {
                console.error('Failed to load backlinks', error)
                this.backlinks = []
            }
        },

        toggleBacklinks() {
            this.backlinksExpanded = !this.backlinksExpanded
            localStorage.setItem('notehub-backlinks-expanded', String(this.backlinksExpanded))
        },

        openBacklink(noteId) {
            const note = this.notes.find(n => n.id === noteId)
            if (note) {
                this.openNote(note)
            }
        },

        highlightWikilink(text, title) {
            const escaped = title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
            const regex = new RegExp('\\[\\[' + escaped + '\\]\\]', 'gi')
            return text.replace(regex, '<strong>$&</strong>')
        },

        debouncedSave() {
            if (this.saveTimer) {
                clearTimeout(this.saveTimer)
            }
            this.saveTimer = setTimeout(() => {
                this.saveNote()
            }, 2000)
        },

        clearSearch() {
            this.searchQuery = ''
            this.searchResults = null
        },

        debouncedSearch() {
            if (this.searchTimer) {
                clearTimeout(this.searchTimer)
            }
            this.searchTimer = setTimeout(() => {
                this.searchNotes()
            }, 500)
        },

        async searchNotes() {
            const query = this.searchQuery.trim()
            if (!query) {
                this.searchResults = null
                return
            }
            try {
                const response = await axios.get(
                    generateUrl('/apps/notehub/api/notes/search'),
                    { params: { q: query } }
                )
                this.searchResults = response.data
            } catch (error) {
                showError(t('notehub', 'Fehler bei der Suche'))
                console.error(error)
            }
        },

        confirmDelete() {
            if (!this.currentNote) return
            if (!confirm(t('notehub', 'Notiz wirklich l\u00f6schen?'))) return
            this.deleteNote(this.currentNote)
        },

        async deleteNote(note) {
            try {
                await axios.delete(
                    generateUrl('/apps/notehub/api/notes/{id}', { id: note.id })
                )
                this.notes = this.notes.filter(n => n.id !== note.id)
                if (this.currentNote && this.currentNote.id === note.id) {
                    this.currentNote = null
                }
                showSuccess(t('notehub', 'Notiz gel\u00f6scht'))
                this.loadTags()
                this.loadTemplates()
            } catch (error) {
                showError(t('notehub', 'Fehler beim L\u00f6schen'))
                console.error(error)
            }
        },

        // ── Sharing ─────────────────────────────────────────

        async openShareDialog() {
            if (!this.currentNote) return
            this.shareDialogNote = this.currentNote
            this.shareTagMode = null
            this.shareDialogShares = []
            this.shareUserQuery = ''
            this.shareUserResults = []
            this.sharePermission = 1
            this.showShareDialog = true
            await this.loadSharesForNote(this.currentNote.id)
        },

        closeShareDialog() {
            this.showShareDialog = false
            this.shareDialogNote = null
            this.shareTagMode = null
            this.shareDialogShares = []
            this.shareUserQuery = ''
            this.shareUserResults = []
        },

        async loadSharesForNote(id) {
            try {
                const response = await axios.get(
                    generateUrl('/apps/notehub/api/notes/{id}/shares', { id })
                )
                this.shareDialogShares = response.data
            } catch (error) {
                const msg = error?.response?.data?.error || error?.response?.data?.message || error.message || 'Unbekannter Fehler'
                showError(t('notehub', 'Fehler beim Laden der Freigaben: ') + msg)
                console.error('Share error:', error.response?.status, error.response?.data, error)
            }
        },

        debouncedSearchUsers() {
            if (this._shareSearchTimer) {
                clearTimeout(this._shareSearchTimer)
            }
            this._shareSearchTimer = setTimeout(() => {
                this.searchShareUsers(this.shareUserQuery)
            }, 300)
        },

        async searchShareUsers(query) {
            if (!query || query.length < 1) {
                this.shareUserResults = []
                return
            }
            try {
                const response = await axios.get(
                    generateUrl('/apps/notehub/api/users/search'),
                    { params: { q: query } }
                )
                this.shareUserResults = response.data
            } catch (error) {
                const msg = error?.response?.data?.error || error?.response?.data?.message || error.message || 'Unbekannter Fehler'
                showError(t('notehub', 'Fehler bei der Benutzersuche: ') + msg)
                console.error('Share error:', error.response?.status, error.response?.data, error)
            }
        },

        selectShareUser(user) {
            this.shareUserQuery = user.id
            this.shareUserResults = []
        },

        async addShare() {
            if (!this.shareUserQuery) return

            if (this.shareTagMode) {
                await this.doShareTagNotes()
                return
            }

            if (!this.shareDialogNote) return
            this.shareLoading = true
            try {
                const response = await axios.post(
                    generateUrl('/apps/notehub/api/notes/{id}/share', { id: this.shareDialogNote.id }),
                    { shareWith: this.shareUserQuery, permissions: this.sharePermission }
                )
                this.shareDialogShares.push(response.data)
                this.shareUserQuery = ''
                this.shareUserResults = []
                showSuccess(t('notehub', 'Erfolgreich geteilt'))
                // Update shared flag in local notes
                const idx = this.notes.findIndex(n => n.id === this.shareDialogNote.id)
                if (idx !== -1) {
                    this.$set(this.notes[idx], 'shared', true)
                }
                if (this.currentNote && this.currentNote.id === this.shareDialogNote.id) {
                    this.$set(this.currentNote, 'shared', true)
                }
            } catch (error) {
                const msg = error?.response?.data?.error || error?.response?.data?.message || error.message || 'Unbekannter Fehler'
                showError(t('notehub', 'Fehler beim Teilen: ') + msg)
                console.error('Share error:', error.response?.status, error.response?.data, error)
            }
            this.shareLoading = false
        },

        async removeShare(shareId) {
            try {
                await axios.delete(
                    generateUrl('/apps/notehub/api/shares/{shareId}', { shareId })
                )
                this.shareDialogShares = this.shareDialogShares.filter(s => s.id !== String(shareId) && s.id !== shareId)
                showSuccess(t('notehub', 'Freigabe entfernt'))
                // Update shared flag if no more shares
                if (this.shareDialogShares.length === 0 && this.shareDialogNote) {
                    const idx = this.notes.findIndex(n => n.id === this.shareDialogNote.id)
                    if (idx !== -1) {
                        this.$set(this.notes[idx], 'shared', false)
                    }
                    if (this.currentNote && this.currentNote.id === this.shareDialogNote.id) {
                        this.$set(this.currentNote, 'shared', false)
                    }
                }
            } catch (error) {
                const msg = error?.response?.data?.error || error?.response?.data?.message || error.message || 'Unbekannter Fehler'
                showError(t('notehub', 'Fehler beim Entfernen der Freigabe: ') + msg)
                console.error('Share error:', error.response?.status, error.response?.data, error)
            }
        },

        shareTagNotes(tagName) {
            this.shareTagMode = tagName
            this.shareDialogNote = null
            this.shareDialogShares = []
            this.shareUserQuery = ''
            this.shareUserResults = []
            this.sharePermission = 1
            this.showShareDialog = true
        },

        async doShareTagNotes() {
            if (!this.shareTagMode || !this.shareUserQuery) return
            const tagNotes = this.notes.filter(n => (n.tags || []).includes(this.shareTagMode))
            if (tagNotes.length === 0) {
                showError(t('notehub', 'Keine Notizen mit diesem Tag'))
                return
            }
            this.shareLoading = true
            let success = 0
            for (const note of tagNotes) {
                try {
                    await axios.post(
                        generateUrl('/apps/notehub/api/notes/{id}/share', { id: note.id }),
                        { shareWith: this.shareUserQuery, permissions: this.sharePermission }
                    )
                    const idx = this.notes.findIndex(n => n.id === note.id)
                    if (idx !== -1) {
                        this.$set(this.notes[idx], 'shared', true)
                    }
                    success++
                } catch (error) {
                    const msg = error?.response?.data?.error || error?.response?.data?.message || error.message || 'Unbekannter Fehler'
                    showError(t('notehub', 'Fehler beim Teilen von Notiz ') + note.id + ': ' + msg)
                    console.error('Share error:', error.response?.status, error.response?.data, error)
                }
            }
            this.shareLoading = false
            showSuccess(t('notehub', '{count} Notizen geteilt').replace('{count}', success))
            this.closeShareDialog()
        },

    },
}
</script>

<style scoped>
.notehub-editor {
    display: flex;
    flex-direction: column;
    height: calc(100vh - var(--header-height, 50px));
    padding: 20px 20px 20px 64px;
}

.notehub-editor-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.notehub-editor-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.notehub-title-input {
    flex: 1;
    font-size: 1.5em;
    font-weight: bold;
    border: none;
    border-bottom: 2px solid var(--color-border);
    padding: 8px 4px;
    background: transparent;
    color: var(--color-main-text);
}

.notehub-title-input:focus {
    border-bottom-color: var(--color-primary);
    outline: none;
}

/* Task bar */
.notehub-task-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 8px 12px;
    margin-bottom: 8px;
    background: var(--color-background-dark);
    border-radius: var(--border-radius-large);
    flex-wrap: wrap;
    font-size: 13px;
}

.notehub-task-bar-item {
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.notehub-task-bar-label {
    color: var(--color-text-maxcontrast);
    font-size: 12px;
}

.notehub-task-bar-item input[type="date"],
.notehub-task-bar-item input[type="text"] {
    padding: 2px 6px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 13px;
    max-width: 140px;
}

.notehub-task-bar-item select {
    padding: 2px 6px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 13px;
}

/* Reminder field in task bar */
.notehub-reminder-bell {
    font-size: 14px;
}
.notehub-reminder-field {
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}
.notehub-reminder-field input[type="datetime-local"] {
    padding: 2px 6px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 13px;
    max-width: 200px;
}
.notehub-reminded-badge {
    font-size: 11px;
    color: var(--color-success);
    background: var(--color-success-light, rgba(40, 167, 69, 0.1));
    padding: 1px 6px;
    border-radius: 8px;
    white-space: nowrap;
}

/* Template banner */
.notehub-template-banner {
    display: inline-block;
    padding: 3px 14px;
    margin-bottom: 6px;
    background: var(--color-warning-light, rgba(255, 193, 7, 0.15));
    color: var(--color-warning-text, #856404);
    border: 1px solid var(--color-warning, #ffc107);
    border-radius: var(--border-radius);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1px;
}

/* Meta bar: task toggle + tag chips in one row */
.notehub-meta-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
    min-height: 28px;
    flex-wrap: wrap;
    overflow: visible;
    position: relative;
}
.notehub-task-toggle-btn {
    font-size: 12px;
    padding: 3px 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    background: transparent;
    color: var(--color-primary-element);
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
}
.notehub-task-toggle-btn:hover {
    background: var(--color-background-hover);
}
.notehub-task-toggle-btn--remove {
    color: var(--color-text-maxcontrast);
}

.notehub-tag-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    background: var(--color-primary-element-light);
    color: var(--color-primary-element-light-text);
    border-radius: 12px;
    font-size: 12px;
    white-space: nowrap;
}

.notehub-tag-remove {
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 0 2px;
    font-size: 14px;
    line-height: 1;
    opacity: 0.7;
}

.notehub-tag-remove:hover {
    opacity: 1;
}

.notehub-tag-input-wrapper {
    position: relative;
    flex: 1;
    min-width: 80px;
}

.notehub-tag-input {
    width: 100%;
    padding: 2px 6px;
    border: 1px solid transparent;
    border-radius: var(--border-radius);
    background: transparent;
    color: var(--color-main-text);
    font-size: 12px;
}

.notehub-tag-input:focus {
    border-color: var(--color-border);
    background: var(--color-main-background);
    outline: none;
}

.notehub-tag-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    z-index: 1000;
    max-height: 200px;
    overflow-y: auto;
}

.notehub-tag-suggestion {
    padding: 6px 10px;
    cursor: pointer;
    font-size: 12px;
    color: var(--color-main-text);
}

.notehub-tag-suggestion:hover {
    background: var(--color-background-hover);
}

.notehub-tag-suggestions--above {
    top: auto !important;
    bottom: 100%;
    box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.2);
}

/* Contact chips */
.notehub-contact-chip {
    display: inline-flex;
    align-items: center;
    background: var(--color-primary-element-light);
    color: var(--color-main-text);
    border-radius: 12px;
    padding: 2px 10px;
    font-size: 12px;
    white-space: nowrap;
}

.notehub-contact-clickable {
    cursor: pointer;
}

.notehub-contact-clickable:hover {
    background: var(--color-primary-element);
    color: white;
}

.notehub-contact-company {
    opacity: 0.7;
    font-size: 11px;
}

.notehub-contact-suggestion-detail {
    opacity: 0.6;
    font-size: 11px;
}

/* Markdown Toolbar */
.notehub-toolbar {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 4px 8px;
    background: var(--color-background-dark);
    border: 1px solid var(--color-border);
    border-bottom: none;
    border-radius: 8px 8px 0 0;
    flex-wrap: wrap;
}
.notehub-toolbar-btn {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid transparent;
    border-radius: var(--border-radius);
    background: transparent;
    color: var(--color-main-text);
    cursor: pointer;
    font-size: 13px;
    padding: 0;
    line-height: 1;
}
.notehub-toolbar-btn:hover {
    background: var(--color-background-hover);
    border-color: var(--color-border);
}
.notehub-toolbar-sep {
    width: 1px;
    height: 18px;
    background: var(--color-border);
    margin: 0 4px;
}

/* Preview bar + toggle */
.notehub-preview-bar {
    padding: 4px 8px;
    border-bottom: 1px solid var(--color-border);
}
.notehub-preview-toggle {
    width: auto !important;
    padding: 2px 10px !important;
    font-size: 13px;
}
.notehub-preview-toggle.active {
    background: var(--color-primary-element);
    color: white;
}

/* Markdown Preview */
.notehub-preview {
    padding: 16px 20px;
    overflow-y: auto;
    height: 100%;
    line-height: 1.6;
    border: 1px solid var(--color-border);
    border-radius: 0 0 8px 8px;
}
.notehub-preview h1, .notehub-preview h2, .notehub-preview h3 {
    margin: 1em 0 0.5em;
    border-bottom: 1px solid var(--color-border);
    padding-bottom: 4px;
}
.notehub-preview h1 { font-size: 1.8em; }
.notehub-preview h2 { font-size: 1.4em; }
.notehub-preview h3 { font-size: 1.2em; }
.notehub-preview code {
    background: var(--color-background-dark);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.9em;
}
.notehub-preview pre code {
    display: block;
    padding: 12px;
    overflow-x: auto;
}
.notehub-preview blockquote {
    border-left: 3px solid var(--color-primary-element);
    margin: 0.5em 0;
    padding: 4px 16px;
    color: var(--color-text-maxcontrast);
}
.notehub-preview table {
    border-collapse: collapse;
    margin: 0.5em 0;
}
.notehub-preview th, .notehub-preview td {
    border: 1px solid var(--color-border);
    padding: 6px 12px;
}
.notehub-preview img {
    max-width: 100%;
    border-radius: 4px;
}
.notehub-preview a.notehub-wikilink {
    color: var(--color-primary-element);
    text-decoration: underline;
    cursor: pointer;
}
.notehub-preview ul, .notehub-preview ol {
    padding-left: 2em;
}
.notehub-preview hr {
    border: none;
    border-top: 1px solid var(--color-border);
    margin: 1em 0;
}

/* Editor body + Wikilink dropdown */
.notehub-editor-body {
    flex: 1 1 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    position: relative;
}

.notehub-content-input {
    width: 100%;
    flex: 1 1 0;
    min-height: 0;
    overflow-y: auto;
    border: 1px solid var(--color-border);
    border-radius: 0 0 8px 8px;
    padding: 16px;
    font-family: 'JetBrains Mono', 'Fira Code', 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.6;
    resize: none;
    background: var(--color-main-background);
    color: var(--color-main-text);
}

.notehub-content-input:focus {
    border-color: var(--color-primary);
    outline: none;
}

.notehub-wiki-dropdown {
    position: absolute;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    z-index: 100;
    max-height: 200px;
    overflow-y: auto;
    min-width: 200px;
    max-width: 350px;
}

.notehub-wiki-match {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notehub-wiki-match:hover {
    background: var(--color-background-hover);
}

/* Empty state */
.notehub-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
}

.notehub-empty-content {
    text-align: center;
    color: var(--color-text-maxcontrast);
}

.notehub-empty-content h2 {
    margin-bottom: 8px;
}

.notehub-save-indicator {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    margin-left: 12px;
    white-space: nowrap;
}

.notehub-search {
    padding: 8px;
    position: relative;
}

.notehub-search input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    background: var(--color-main-background);
    color: var(--color-main-text);
}

.notehub-search input:focus {
    border-color: var(--color-primary);
    outline: none;
}

.notehub-search-clear {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 18px;
    color: var(--color-text-maxcontrast);
    cursor: pointer;
    padding: 0 4px;
    line-height: 1;
}
.notehub-search-clear:hover {
    color: var(--color-main-text);
}

/* Sidebar tag count */
.notehub-tag-count {
    font-size: 11px;
    color: var(--color-text-maxcontrast);
}

/* Active tag highlight in sidebar */
.active :deep(.app-navigation-entry) {
    background: var(--color-primary-element-light) !important;
}
.active :deep(.app-navigation-entry__name) {
    font-weight: bold;
}

/* Filter indicator */
.notehub-active-filter {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 4px 12px;
    margin: 4px 8px;
    background: var(--color-primary-element-light);
    border-radius: var(--border-radius);
    font-size: 12px;
    color: var(--color-primary-element-light-text);
}
.notehub-active-filter-clear {
    background: none;
    border: none;
    font-size: 16px;
    cursor: pointer;
    color: inherit;
    padding: 0 4px;
    line-height: 1;
    opacity: 0.7;
}
.notehub-active-filter-clear:hover {
    opacity: 1;
}

/* Task dot in sidebar */
.notehub-task-dot {
    font-size: 12px;
    line-height: 1;
    margin-right: 2px;
    flex-shrink: 0;
}


/* Sidebar task styling */
.notehub-nav-checkbox {
    cursor: pointer;
    margin: 0;
    width: 16px;
    height: 16px;
}

.task-done :deep(.app-navigation-entry__name) {
    text-decoration: line-through;
    color: var(--color-text-maxcontrast);
}

.task-overdue :deep(.app-navigation-entry) {
    border-left: 3px solid var(--color-error);
}

.task-today :deep(.app-navigation-entry) {
    border-left: 3px solid var(--color-warning);
}

/* Collapsible tags header */
.notehub-tags-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    cursor: pointer;
    font-size: 13px;
    color: var(--color-text-maxcontrast);
    user-select: none;
}
.notehub-tags-header:hover {
    background: var(--color-background-hover);
}
.notehub-tags-toggle {
    font-size: 10px;
    width: 12px;
}
.notehub-tags-label {
    font-weight: 500;
}

/* Sort list (collapsible) */
.notehub-sort-list {
    padding: 0 4px 4px;
}
.notehub-sort-option {
    padding: 4px 12px 4px 30px;
    font-size: 12px;
    color: var(--color-main-text);
    cursor: pointer;
    border-radius: var(--border-radius);
}
.notehub-sort-option:hover {
    background: var(--color-background-hover);
}
.notehub-sort-option--active {
    font-weight: bold;
    color: var(--color-primary-element);
    background: var(--color-primary-element-light);
}

/* Backlinks */
.notehub-backlinks {
    flex-shrink: 0;
    border-top: 1px solid #ddd;
    margin-top: 4px;
}
.notehub-backlinks-header {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    color: var(--color-main-text);
    user-select: none;
    padding: 8px 12px;
    background: var(--color-background-dark);
    border-radius: var(--border-radius);
    min-height: 40px;
}
.notehub-backlinks-header:hover { background: var(--color-background-hover); }
.notehub-backlinks-toggle { font-size: 10px; width: 12px; }
.notehub-backlinks-list {
    max-height: 35vh;
    overflow-y: auto;
    padding: 4px 0;
}
.notehub-backlinks-empty {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    padding: 4px 0;
}
.notehub-backlink-item {
    padding: 6px 0;
    border-bottom: 1px solid var(--color-border-dark);
}
.notehub-backlink-item:last-child { border-bottom: none; }
.notehub-backlink-title {
    display: flex;
    align-items: center;
    gap: 8px;
}
.notehub-backlink-title a {
    color: var(--color-primary-element);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
}
.notehub-backlink-title a:hover { text-decoration: underline; }
.notehub-backlink-line {
    font-size: 11px;
    color: var(--color-text-maxcontrast);
}
.notehub-backlink-context {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    margin-top: 2px;
    line-height: 1.4;
}

/* Sync indicator */
.notehub-sync-indicator {
    padding: 8px 12px;
    margin: 4px 8px;
    background: var(--color-primary-element-light);
    color: var(--color-primary-element-light-text);
    border-radius: var(--border-radius);
    font-size: 12px;
    text-align: center;
    animation: notehub-pulse 1.5s ease-in-out infinite;
}
@keyframes notehub-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Notes header with refresh button */
.notehub-notes-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px 4px;
    margin-top: 4px;
}
.notehub-notes-caption {
    font-weight: bold;
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.notehub-refresh-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    padding: 2px 4px;
    border-radius: var(--border-radius);
    line-height: 1;
    opacity: 0.6;
    transition: opacity 0.2s;
}
.notehub-refresh-btn:hover {
    opacity: 1;
    background: var(--color-background-hover);
}
.notehub-refresh-btn:disabled {
    cursor: default;
}
.notehub-refreshing {
    animation: notehub-spin 1s linear infinite;
}
@keyframes notehub-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* New buttons side by side */
.notehub-new-buttons {
    display: flex;
    gap: 4px;
}
.notehub-new-buttons > * {
    flex: 1;
    min-width: 0;
}
.notehub-new-buttons :deep(.action-item) {
    flex: 1;
    min-width: 0;
}

/* Delete button in editor */
.notehub-delete-btn {
    background: none;
    border: 1px solid transparent;
    border-radius: var(--border-radius);
    cursor: pointer;
    font-size: 16px;
    padding: 4px 8px;
    color: var(--color-text-maxcontrast);
    line-height: 1;
}
.notehub-delete-btn:hover {
    color: var(--color-error);
    border-color: var(--color-error);
    background: var(--color-error-light, rgba(220, 53, 69, 0.1));
}

/* ── Back button (mobile) ────────────────────────── */
.notehub-back-btn {
    display: none;
}

/* ── MOBILE RESPONSIVE ──────────────────────────── */
@media (max-width: 767px) {
    /* Hide Nextcloud sidebar on mobile when editor is shown */
    .notehub-mobile-editor :deep(.app-navigation) {
        display: none !important;
    }
    /* Show sidebar full width when no editor */
    .notehub-mobile :deep(.app-navigation) {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        flex: 1 1 100% !important;
    }
    /* Hide content area when sidebar is shown */
    .notehub-mobile :deep(.app-content) {
        display: none !important;
    }
    .notehub-mobile-editor :deep(.app-content) {
        display: flex !important;
        width: 100% !important;
        margin-left: 0 !important;
    }

    /* Back button visible on mobile */
    .notehub-back-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        min-width: 44px;
        border: none;
        background: none;
        color: var(--color-main-text);
        font-size: 20px;
        cursor: pointer;
        border-radius: var(--border-radius);
        flex-shrink: 0;
    }
    .notehub-back-btn:hover,
    .notehub-back-btn:active {
        background: var(--color-background-hover);
    }

    /* Editor layout */
    .notehub-editor {
        padding: 10px 10px 10px 10px !important;
        height: calc(100vh - var(--header-height, 50px));
    }

    /* Editor header: compact */
    .notehub-editor-header {
        gap: 6px !important;
        margin-bottom: 8px !important;
    }
    .notehub-title-input {
        font-size: 1.2em !important;
        min-width: 0;
        padding: 6px 4px !important;
    }
    .notehub-editor-actions {
        gap: 6px !important;
        flex-shrink: 0;
    }

    /* Meta bar: wrap on mobile */
    .notehub-meta-bar {
        flex-wrap: wrap !important;
        overflow: visible !important;
        gap: 4px !important;
    }

    /* Task/Template buttons: bigger touch targets */
    .notehub-task-toggle-btn {
        min-height: 40px;
        padding: 8px 14px !important;
        font-size: 13px !important;
    }

    /* Tag chips: bigger for touch */
    .notehub-tag-chip {
        padding: 6px 12px !important;
        font-size: 13px !important;
        min-height: 36px;
        display: inline-flex;
        align-items: center;
    }
    .notehub-tag-remove {
        padding: 0 6px !important;
        font-size: 18px !important;
        min-width: 28px;
        min-height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .notehub-tag-input {
        min-height: 40px;
        font-size: 15px !important;
        padding: 6px 10px !important;
    }

    /* Task bar: vertical layout */
    .notehub-task-bar {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 8px !important;
        padding: 10px !important;
    }
    .notehub-task-bar-item {
        min-height: 40px;
    }
    .notehub-task-bar-item input[type="date"],
    .notehub-task-bar-item input[type="text"] {
        max-width: none !important;
        flex: 1;
        min-height: 40px;
    }
    .notehub-task-bar-item select {
        min-height: 40px;
        flex: 1;
    }
    .notehub-reminder-field {
        flex-wrap: wrap;
    }
    .notehub-reminder-field input[type="datetime-local"] {
        max-width: none !important;
        flex: 1;
        min-height: 40px;
    }

    /* Toolbar: horizontal scroll, no wrap */
    .notehub-toolbar {
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        border-radius: 8px 8px 0 0;
    }
    .notehub-toolbar::-webkit-scrollbar {
        display: none;
    }
    .notehub-toolbar-btn {
        min-width: 36px !important;
        min-height: 36px !important;
        width: 36px !important;
        height: 36px !important;
        font-size: 15px !important;
        flex-shrink: 0;
    }
    .notehub-toolbar-sep {
        height: 24px;
        flex-shrink: 0;
    }

    /* Preview toggle: bigger */
    .notehub-preview-bar {
        padding: 6px 8px !important;
    }
    .notehub-preview-toggle {
        min-height: 36px !important;
        padding: 4px 14px !important;
        font-size: 14px !important;
    }

    /* Editor body */
    .notehub-editor-body {
        min-height: 0;
        flex: 1 1 0;
    }
    .notehub-content-input {
        font-size: 15px !important;
        padding: 12px !important;
        min-height: 50vh;
    }
    .notehub-preview {
        padding: 12px 14px !important;
        font-size: 15px;
    }

    /* Backlinks */
    .notehub-backlinks {
        flex-shrink: 0;
    }
    .notehub-backlinks-header {
        min-height: 44px;
    }
    .notehub-backlinks-list {
        max-height: 30vh;
    }

    /* Delete button: bigger touch target */
    .notehub-delete-btn {
        min-width: 44px;
        min-height: 44px;
        font-size: 18px !important;
    }

    /* Save indicator */
    .notehub-save-indicator {
        font-size: 11px;
        margin-left: 4px !important;
    }

    /* Share overlay: below Nextcloud header */
    .notehub-share-overlay {
        top: var(--header-height, 50px) !important;
        height: calc(100vh - var(--header-height, 50px)) !important;
    }
    /* Share dialog: full width */
    .notehub-share-dialog {
        width: 100% !important;
        max-width: 100vw !important;
        max-height: calc(100vh - var(--header-height, 50px)) !important;
        border-radius: 0 !important;
        height: calc(100vh - var(--header-height, 50px));
        padding: 16px !important;
    }
    .notehub-share-close {
        min-width: 44px;
        min-height: 44px;
        font-size: 26px !important;
    }
    .notehub-share-search {
        min-height: 44px;
        font-size: 16px !important;
    }
    .notehub-share-result {
        min-height: 44px;
        display: flex;
        align-items: center;
        font-size: 15px !important;
    }
    .notehub-share-options {
        flex-direction: column !important;
    }
    .notehub-share-perm-select {
        width: 100%;
        min-height: 44px;
        font-size: 15px !important;
    }
    .notehub-share-item {
        min-height: 44px;
    }
    .notehub-share-remove {
        min-width: 44px;
        min-height: 44px;
        font-size: 22px !important;
    }

    /* Sidebar list entries: compact on mobile */
    :deep(.app-navigation-entry) {
        min-height: 36px !important;
        height: auto !important;
    }
    :deep(.app-navigation-entry__name) {
        font-size: 13px !important;
    }
    :deep(.app-navigation-entry a),
    :deep(.app-navigation-entry .app-navigation-entry-link) {
        padding-top: 4px !important;
        padding-bottom: 4px !important;
        min-height: 36px !important;
    }
    :deep(.app-navigation-entry .app-navigation-entry__icon) {
        width: 28px !important;
        height: 28px !important;
        min-width: 28px !important;
    }
    :deep(.app-navigation-entry .app-navigation-entry__utils) {
        font-size: 12px !important;
    }

    /* Sidebar section headers: compact on mobile */
    .notehub-tags-header {
        min-height: 32px;
        padding: 4px 12px !important;
        font-size: 13px !important;
    }
    .notehub-tags-toggle {
        font-size: 8px !important;
        width: 10px !important;
    }
    /* Sort list compact on mobile */
    .notehub-sort-option {
        min-height: 32px;
        display: flex;
        align-items: center;
        padding: 2px 12px 2px 24px !important;
        font-size: 12px !important;
    }
    .notehub-notes-header {
        min-height: 32px;
        padding: 4px 12px 2px !important;
    }
    .notehub-refresh-btn {
        min-width: 36px;
        min-height: 36px;
        font-size: 16px !important;
    }
    .notehub-search input {
        min-height: 44px;
        font-size: 16px !important;
        padding: 8px 12px !important;
    }
    .notehub-search-clear {
        min-width: 44px;
        min-height: 44px;
        font-size: 22px !important;
    }
    .notehub-new-buttons {
        gap: 6px !important;
    }
    .notehub-active-filter {
        min-height: 36px;
        font-size: 14px !important;
    }
    .notehub-active-filter-clear {
        min-width: 36px;
        min-height: 36px;
        font-size: 20px !important;
    }
    .notehub-tag-share-btn {
        min-width: 36px;
        min-height: 36px;
        font-size: 16px !important;
        opacity: 0.6;
    }

    /* Wikilink dropdown */
    .notehub-wiki-dropdown {
        max-width: calc(100vw - 40px);
        min-width: 150px;
    }
    .notehub-wiki-match {
        min-height: 40px;
        display: flex;
        align-items: center;
    }

    /* Tag & Contact suggestions */
    .notehub-tag-suggestions {
        max-height: 200px;
        left: 0 !important;
        right: 0 !important;
        min-width: 0 !important;
        width: calc(100vw - 40px) !important;
        max-width: 100% !important;
    }
    .notehub-tag-suggestion {
        min-height: 44px;
        display: flex;
        align-items: center;
        padding: 8px 12px !important;
        font-size: 14px !important;
    }

    /* Tag/Contact input wrappers: full width on mobile */
    .notehub-tag-input-wrapper {
        flex: 1 1 100% !important;
        min-width: 0 !important;
    }

    /* Contact chips: touch-friendly */
    .notehub-contact-chip {
        padding: 6px 12px !important;
        font-size: 13px !important;
        min-height: 36px;
        display: inline-flex;
        align-items: center;
    }
    .notehub-contact-chip .notehub-tag-remove {
        min-width: 28px;
        min-height: 28px;
        font-size: 18px !important;
        padding: 0 4px !important;
        margin-left: 4px;
    }
    .notehub-contact-company {
        font-size: 11px !important;
    }
    .notehub-contact-input {
        min-height: 40px !important;
        font-size: 15px !important;
    }
    .notehub-contact-suggestion-detail {
        font-size: 12px !important;
    }

    /* Version info */
    .notehub-build-info {
        font-size: 9px !important;
        padding: 6px 12px !important;
    }

    /* Readonly badge */
    .notehub-readonly-badge {
        font-size: 11px !important;
        padding: 2px 8px !important;
    }
}

/* Share Dialog Overlay */
.notehub-share-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}
.notehub-share-dialog {
    background: var(--color-main-background);
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.25);
    width: 480px;
    max-width: 90vw;
    max-height: 80vh;
    overflow-y: auto;
    padding: 20px;
}
.notehub-share-dialog-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.notehub-share-dialog-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}
.notehub-share-close {
    background: none;
    border: none;
    font-size: 22px;
    cursor: pointer;
    color: var(--color-text-maxcontrast);
    padding: 0 4px;
    line-height: 1;
}
.notehub-share-close:hover {
    color: var(--color-main-text);
}
.notehub-share-list {
    margin-bottom: 16px;
}
.notehub-share-empty {
    font-size: 13px;
    color: var(--color-text-maxcontrast);
    padding: 8px 0;
}
.notehub-share-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    border-bottom: 1px solid var(--color-border);
}
.notehub-share-item:last-child {
    border-bottom: none;
}
.notehub-share-user {
    flex: 1;
    font-size: 14px;
    font-weight: 500;
}
.notehub-share-perm {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
}
.notehub-share-remove {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: var(--color-error);
    padding: 0 4px;
    line-height: 1;
    opacity: 0.7;
}
.notehub-share-remove:hover {
    opacity: 1;
}
.notehub-share-add {
    position: relative;
}
.notehub-share-search {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 14px;
    margin-bottom: 8px;
}
.notehub-share-search:focus {
    border-color: var(--color-primary);
    outline: none;
}
.notehub-share-results {
    position: absolute;
    top: 38px;
    left: 0;
    right: 0;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    z-index: 100;
    max-height: 150px;
    overflow-y: auto;
}
.notehub-share-result {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 13px;
}
.notehub-share-result:hover {
    background: var(--color-background-hover);
}
.notehub-share-options {
    display: flex;
    gap: 8px;
    align-items: center;
}
.notehub-share-perm-select {
    flex: 1;
    padding: 6px 8px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 13px;
}
.notehub-share-loading {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    padding: 8px 0;
    text-align: center;
    animation: notehub-pulse 1.5s ease-in-out infinite;
}

/* Tag share button */
.notehub-tag-share-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 12px;
    padding: 0 2px;
    opacity: 0.4;
    line-height: 1;
}
.notehub-tag-share-btn:hover {
    opacity: 1;
}

/* Shared note indicator */
.notehub-shared-by {
    font-size: 11px;
    color: var(--color-text-maxcontrast);
}

.notehub-build-info {
    font-size: 10px;
    color: #999;
    padding: 8px 12px;
    text-align: left;
}

/* Readonly badge + editor */
.notehub-readonly-badge {
    font-size: 12px;
    padding: 3px 10px;
    background: var(--color-warning-light, rgba(255, 193, 7, 0.15));
    color: var(--color-warning-text, #856404);
    border: 1px solid var(--color-warning, #ffc107);
    border-radius: var(--border-radius);
    white-space: nowrap;
}
.notehub-readonly {
    opacity: 0.75;
    cursor: default;
}
</style>
