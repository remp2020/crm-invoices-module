<?php

use Phinx\Migration\AbstractMigration;

class UpdateInvoiceItemsText extends AbstractMigration
{
    public function up()
    {
        $this->table('invoice_items')
            ->changeColumn('text', 'text')
            ->update();
    }

    public function down()
    {
        $this->table('invoice_items')
            ->changeColumn('text', 'string', ['limit' => 255])
            ->update();
    }
}
