<?php

namespace Crm\InvoicesModule\Components\InvoiceButton;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\InvoicesModule\Models\Invoice\PaymentInvoiceStatusResolver;

/**
 * Download invoice button widget.
 *
 * This components renders simple button to download invoice.
 * Used in user frontend payments listing and admin payments listing.
 *
 * @package Crm\InvoicesModule\Components
 */
class InvoiceButton extends BaseLazyWidget
{
    private string $templateName = 'invoice_button.latte';

    private bool $admin = false;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        protected readonly PaymentInvoiceStatusResolver $paymentInvoiceStatusResolver,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function header()
    {
        return 'Faktúra';
    }

    public function identifier()
    {
        return 'userinvoice';
    }

    public function setAdmin()
    {
        $this->admin = true;
    }

    public function render($payment)
    {
        $this->template->payment = $payment;
        $this->template->status = $this->paymentInvoiceStatusResolver->resolve($payment);
        $this->template->admin = $this->admin;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
