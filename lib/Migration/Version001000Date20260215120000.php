<?php

declare(strict_types=1);

namespace OCA\NoteHub\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001000Date20260215120000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('notehub_notes')) {
            $table = $schema->createTable('notehub_notes');

            $table->addColumn('id', 'bigint', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('file_id', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('title', 'string', [
                'notnull' => false,
                'length' => 255,
            ]);
            $table->addColumn('path', 'string', [
                'notnull' => false,
                'length' => 1024,
            ]);
            $table->addColumn('type', 'string', [
                'notnull' => false,
                'length' => 10,
                'default' => 'note',
            ]);
            $table->addColumn('status', 'string', [
                'notnull' => false,
                'length' => 10,
                'default' => '',
            ]);
            $table->addColumn('due', 'string', [
                'notnull' => false,
                'length' => 20,
                'default' => '',
            ]);
            $table->addColumn('priority', 'integer', [
                'notnull' => false,
                'default' => 0,
            ]);
            $table->addColumn('tags', 'text', [
                'notnull' => false,
            ]);
            $table->addColumn('remind', 'string', [
                'notnull' => false,
                'length' => 20,
                'default' => '',
            ]);
            $table->addColumn('reminded', 'boolean', [
                'notnull' => false,
                'default' => false,
            ]);
            $table->addColumn('person', 'string', [
                'notnull' => false,
                'length' => 100,
                'default' => '',
            ]);
            $table->addColumn('start', 'string', [
                'notnull' => false,
                'length' => 20,
                'default' => '',
            ]);
            $table->addColumn('template', 'boolean', [
                'notnull' => false,
                'default' => false,
            ]);
            $table->addColumn('template_name', 'string', [
                'notnull' => false,
                'length' => 255,
                'default' => '',
            ]);
            $table->addColumn('modified', 'bigint', [
                'notnull' => false,
                'unsigned' => true,
                'default' => 0,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['file_id'], 'notehub_notes_file_id');
            $table->addIndex(['user_id'], 'notehub_notes_user_id');
            $table->addIndex(['user_id', 'type'], 'notehub_notes_user_type');
            $table->addIndex(['user_id', 'template'], 'notehub_notes_user_tpl');
        }

        return $schema;
    }
}
