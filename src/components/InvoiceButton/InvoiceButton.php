<?php

namespace Crm\InvoicesModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\InvoicesModule\InvoiceGenerator;
use Crm\InvoicesModule\Repository\InvoicesRepository;

/**
 * Download invoice button widget.
 *
 * This components renders simple button to download invoice.
 * Used in user frontend payments listing and admin payments listing.
 *
 * @package Crm\InvoicesModule\Components
 */
class InvoiceButton extends BaseWidget
{
    private $templateName = 'invoice_button.latte';

    private $admin = false;

    private $invoicesRepository;

    public function __construct(
        InvoicesRepository $invoicesRepository,
        WidgetManager $widgetManager
    ) {
        parent::__construct($widgetManager);
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
        $this->template->canGenerateDaysLimit = InvoiceGenerator::CAN_GENERATE_DAYS_LIMIT;
        $this->template->admin = $this->admin;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
