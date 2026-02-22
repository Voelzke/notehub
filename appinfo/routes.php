<?php

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'note#index',   'url' => '/api/notes',        'verb' => 'GET'],
        ['name' => 'note#search', 'url' => '/api/notes/search', 'verb' => 'GET'],
        ['name' => 'note#titles', 'url' => '/api/notes/titles', 'verb' => 'GET'],
        ['name' => 'note#tags',   'url' => '/api/tags',         'verb' => 'GET'],
        ['name' => 'note#backlinks', 'url' => '/api/notes/{id}/backlinks', 'verb' => 'GET'],
        ['name' => 'note#show',    'url' => '/api/notes/{id}',  'verb' => 'GET'],
        ['name' => 'note#create',  'url' => '/api/notes',       'verb' => 'POST'],
        ['name' => 'note#createFromTemplate', 'url' => '/api/notes/from-template', 'verb' => 'POST'],
        ['name' => 'note#update',  'url' => '/api/notes/{id}',  'verb' => 'PUT'],
        ['name' => 'note#destroy', 'url' => '/api/notes/{id}',  'verb' => 'DELETE'],

        ['name' => 'note#folders',   'url' => '/api/folders',     'verb' => 'GET'],
        ['name' => 'note#templates', 'url' => '/api/templates',   'verb' => 'GET'],

        ['name' => 'note#toggleTask',    'url' => '/api/notes/{id}/toggle-task',    'verb' => 'PUT'],
        ['name' => 'note#setTask',       'url' => '/api/notes/{id}/set-task',       'verb' => 'PUT'],
        ['name' => 'note#unsetTask',     'url' => '/api/notes/{id}/unset-task',     'verb' => 'PUT'],
        ['name' => 'note#setTemplate',   'url' => '/api/notes/{id}/set-template',   'verb' => 'PUT'],
        ['name' => 'note#unsetTemplate', 'url' => '/api/notes/{id}/unset-template', 'verb' => 'PUT'],

        ['name' => 'note#syncStatus', 'url' => '/api/index/status', 'verb' => 'GET'],
        ['name' => 'note#syncIndex',  'url' => '/api/index/sync',   'verb' => 'POST'],

        ['name' => 'note#shares',       'url' => '/api/notes/{id}/shares',  'verb' => 'GET'],
        ['name' => 'note#share',        'url' => '/api/notes/{id}/share',   'verb' => 'POST'],
        ['name' => 'note#unshare',      'url' => '/api/shares/{shareId}',   'verb' => 'DELETE'],
        ['name' => 'note#searchUsers',  'url' => '/api/users/search',       'verb' => 'GET'],
        ['name' => 'note#sharedWithMe', 'url' => '/api/shared-with-me',     'verb' => 'GET'],

        ['name' => 'note#showSharedNote',   'url' => '/api/shared-notes/{id}', 'verb' => 'GET'],
        ['name' => 'note#updateSharedNote', 'url' => '/api/shared-notes/{id}', 'verb' => 'PUT'],

        ['name' => 'note#uploadImage', 'url' => '/api/notes/{id}/upload-image', 'verb' => 'POST'],
        ['name' => 'note#getImage',    'url' => '/api/images/{filename}',       'verb' => 'GET'],

        ['name' => 'note#searchContacts', 'url' => '/api/contacts/search',        'verb' => 'GET'],
        ['name' => 'note#contacts',       'url' => '/api/contacts',               'verb' => 'GET'],
        ['name' => 'note#contactNotes',   'url' => '/api/contacts/{name}/notes',  'verb' => 'GET'],
    ],
];
