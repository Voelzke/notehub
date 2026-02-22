<?php

declare(strict_types=1);

namespace OCA\NoteHub\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001002Date20260221120000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('notehub_notes');

        if (!$table->hasColumn('contacts')) {
            $table->addColumn('contacts', 'text', [
                'notnull' => false,
                'default' => null,
            ]);
        }

        return $schema;
    }
}
