<?php

namespace Crm\InvoicesModule;

use Exception;

class PaymentNotInvoiceableException extends Exception
{
    public function __construct($paymentId)
    {
        parent::__construct("Trying to generate invoice for payment [{$paymentId}] which is not invoiceable.");
    }
}
