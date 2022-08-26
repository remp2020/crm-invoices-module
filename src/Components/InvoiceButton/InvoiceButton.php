<?php

namespace Crm\InvoicesModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Nette\Utils\DateTime;

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
    private $templateName = 'invoice_button.latte';

    private $admin = false;

    private $invoicesRepository;

    public function __construct(
        InvoicesRepository $invoicesRepository,
        LazyWidgetManager $lazyWidgetManager
    ) {
        parent::__construct($lazyWidgetManager);
        $this->invoicesRepository = $invoicesRepository;
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
        $this->template->paymentInvoicable = $this->invoicesRepository->isPaymentInvoiceable($payment);
        $this->template->paidButNotInvoiceableAnymore = $payment->paid_at !== null && !InvoicesRepository::paymentInInvoiceablePeriod($payment, new DateTime());
        $this->template->admin = $this->admin;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
