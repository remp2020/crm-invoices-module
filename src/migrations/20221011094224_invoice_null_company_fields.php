<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InvoiceNullCompanyFields extends AbstractMigration
{
    public function up(): void
    {
        $sql = <<<SQL
        UPDATE invoices SET `buyer_id` = NULL WHERE `buyer_id` = '';
        UPDATE invoices SET `buyer_tax_id` = NULL WHERE `buyer_tax_id` = '';
        UPDATE invoices SET `buyer_vat_id` = NULL WHERE `buyer_vat_id` = '';
SQL;
        $this->execute($sql);
    }

    public function down()
    {
        $this->output->writeln('This is data migration. Down migration is not available.');
    }
}
