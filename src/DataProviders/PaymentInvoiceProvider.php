<?php

namespace Crm\InvoicesModule\DataProviders;

use Crm\InvoicesModule\Models\Generator\InvoiceGenerator;
use Crm\PaymentsModule\DataProvider\PaymentInvoiceProviderInterface;
use Nette\Database\Table\ActiveRow;

class PaymentInvoiceProvider implements PaymentInvoiceProviderInterface
{
    private $invoiceGenerator;

    public function __construct(
        InvoiceGenerator $invoiceGenerator
    ) {
        $this->invoiceGenerator = $invoiceGenerator;
    }

    public function provide(ActiveRow $payment)
    {
        return $this->invoiceGenerator->renderInvoiceMailAttachment($payment);
    }
}
