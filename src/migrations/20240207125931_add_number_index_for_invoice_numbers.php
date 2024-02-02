<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddNumberIndexForInvoiceNumbers extends AbstractMigration
{
    public function change(): void
    {
        $this->table('invoice_numbers')
            ->addIndex('number', ['unique' => true])
            ->update();
    }
}
