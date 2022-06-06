<?php

use Phinx\Migration\AbstractMigration;

class InvoicesAddUpdatedDateColumn extends AbstractMigration
{
    public function up()
    {
        $this->table('invoices')
            ->addColumn('updated_date', 'datetime', ['null' => true])
            ->update();

        $this->query('UPDATE `invoices` SET updated_date = created_date;');

        $this->table('invoices')
            ->changeColumn('updated_date', 'datetime', ['null' => false])
            ->update();
    }

    public function down()
    {
        $this->table('invoices')
            ->removeColumn('updated_date')
            ->update();
    }
}
