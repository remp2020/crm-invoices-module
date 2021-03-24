<?php

use Phinx\Migration\AbstractMigration;

class PaymentGatewaysAddInvoiceConfig extends AbstractMigration
{
    public function change()
    {
        $this->table('payment_gateways')
            ->addColumn('invoice', 'boolean', ['default' => true])
            ->update();
    }
}
