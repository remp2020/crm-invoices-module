<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddTypeColumnIntoVatIdConsultations extends AbstractMigration
{
    public function up(): void
    {
        $this->table('vat_id_consultations')
            ->addColumn('type', 'string', ['after' => 'vat_id', 'null' => true])
            ->update();

        $this->query("UPDATE `vat_id_consultations` SET type = 'vies';");

        $this->table('vat_id_consultations')
            ->changeColumn('type', 'string', ['null' => false])
            ->update();
    }

    public function down(): void
    {
        $this->table('vat_id_consultations')
            ->removeColumn('type')
            ->update();
    }
}
