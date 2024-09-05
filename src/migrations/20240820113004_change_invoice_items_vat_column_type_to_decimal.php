<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ChangeInvoiceItemsVatColumnTypeToDecimal extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
ALTER TABLE invoice_items MODIFY COLUMN vat DECIMAL(10,2), LOCK=SHARED;
        ");
    }

    public function down(): void
    {
        $this->execute("
ALTER TABLE invoice_items MODIFY vat INTEGER, LOCK=SHARED;
        ");
    }
}
