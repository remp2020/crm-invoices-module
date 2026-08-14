<?php

namespace Crm\InvoicesModule\Components\PaymentInvoiceDetailWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\InvoicesModule\Models\Invoice\PaymentInvoiceStatusResolver;
use Nette\Database\Table\ActiveRow;

class PaymentInvoiceDetailWidget extends BaseLazyWidget
{
    private string $templateName = 'payment_invoice_detail_widget.latte';

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private readonly PaymentInvoiceStatusResolver $paymentInvoiceStatusResolver,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function render(ActiveRow $payment): void
    {
        $this->template->payment = $payment;
        $this->template->invoice = $payment->invoice;
        $this->template->status = $this->paymentInvoiceStatusResolver->resolve($payment);
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
