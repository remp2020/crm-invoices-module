<?php

use Phinx\Migration\AbstractMigration;

class PaymentsAddInvoiceNumberIdColumn extends AbstractMigration
{
    public function up()
    {
        $this->table('payments')
            ->addColumn('invoice_number_id', 'integer', [
                'null' => true, // only paid payments have invoices (or invoice numbers)
                'after' => 'invoice_id',
            ])
            ->addForeignKey('invoice_number_id','invoice_numbers')
            ->addIndex('invoice_number_id', ['unique' => true]) // one payment === one invoice number
            ->update();

        // add invoice_number_id to payments with invoices
        $this->execute(<<<SQL
            UPDATE `payments`
            INNER JOIN `invoices`
              ON `invoices`.`id` = `payments`.`invoice_id`
            SET `payments`.`invoice_number_id` = `invoices`.`invoice_number_id`
            WHERE
              `payments`.`invoice_id` IS NOT NULL
            ;
SQL
        );
    }

    public function down()
    {
        $this->table('payments')
            ->dropForeignKey('invoice_number_id')
            ->removeColumn('invoice_number_id')
            ->update();
    }
}
