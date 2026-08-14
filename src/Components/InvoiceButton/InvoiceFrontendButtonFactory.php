<?php

namespace Crm\InvoicesModule\Components\InvoiceButton;

use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\ApplicationModule\Models\Widget\WidgetFactoryInterface;
use Crm\ApplicationModule\Models\Widget\WidgetInterface;
use Crm\InvoicesModule\Models\Invoice\PaymentInvoiceStatusResolver;

class InvoiceFrontendButtonFactory implements WidgetFactoryInterface
{
    public function __construct(
        private readonly LazyWidgetManager $lazyWidgetManager,
        private readonly PaymentInvoiceStatusResolver $paymentInvoiceStatusResolver,
    ) {
    }

    public function create(): WidgetInterface
    {
        return new InvoiceButton(
            lazyWidgetManager: $this->lazyWidgetManager,
            paymentInvoiceStatusResolver: $this->paymentInvoiceStatusResolver,
        );
    }
}
