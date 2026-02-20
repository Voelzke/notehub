<?php

declare(strict_types=1);

namespace OCA\NoteHub\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001001Date20260220120000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('notehub_notes');

        if (!$table->hasColumn('shared')) {
            $table->addColumn('shared', 'boolean', [
                'notnull' => false,
                'default' => false,
            ]);
        }

        return $schema;
    }
}
