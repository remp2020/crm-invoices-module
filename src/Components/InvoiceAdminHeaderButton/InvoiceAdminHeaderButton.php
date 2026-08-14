<?php

namespace Crm\InvoicesModule\Components\InvoiceAdminHeaderButton;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\InvoicesModule\Models\Invoice\PaymentInvoiceStatusResolver;
use Nette\Database\Table\ActiveRow;

class InvoiceAdminHeaderButton extends BaseLazyWidget
{
    private string $templateName = 'invoice_admin_header_button.latte';

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private readonly PaymentInvoiceStatusResolver $paymentInvoiceStatusResolver,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function render(ActiveRow $payment): void
    {
        $status = $this->paymentInvoiceStatusResolver->resolve($payment);
        if (!$status->isDownloadable()) {
            return;
        }

        $this->template->payment = $payment;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
