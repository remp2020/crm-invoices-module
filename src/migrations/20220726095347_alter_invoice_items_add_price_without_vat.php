<?php

use Phinx\Migration\AbstractMigration;

class AlterInvoiceItemsAddPriceWithoutVat extends AbstractMigration
{
    public function up()
    {
        $this->table('invoice_items')
            ->addColumn('price_without_vat', 'decimal', ['after'=> 'price', 'scale' => 2, 'precision' => 10, 'null' => true])
            ->update();

        // formula for amount without vat is incorrect; this was fixed by following migration FixPaymentItemsAmountWithoutVat
        $sql = <<<SQL
UPDATE `invoice_items` 
SET `price_without_vat` = ROUND(`price` / (1 + (`vat`/100)), 2); 
SQL;
        $this->execute($sql);

        $this->table('payment_items')
            ->changeColumn('amount_without_vat', 'decimal', ['scale' => 2, 'precision' => 10, 'null' => false])
            ->update();
    }

    public function down()
    {
        $this->table('payment_items')
            ->removeColumn('price_without_vat')
            ->update();
    }
}
