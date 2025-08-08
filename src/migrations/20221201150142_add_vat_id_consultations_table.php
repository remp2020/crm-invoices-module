<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddVatIdConsultationsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('vat_id_consultations')
            ->addColumn('vat_id', 'string', ['null' => false])
            ->addColumn('consultation_number', 'string', ['null' => false])
            ->addColumn('date_of_validation', 'datetime', ['null' => false])
            ->addColumn('response', 'json', ['null' => false])
            ->addIndex('vat_id') // not unique; there could be multiple consultations in time with different results
            ->addIndex('consultation_number', ['unique' => true])
            ->create();
    }
}
