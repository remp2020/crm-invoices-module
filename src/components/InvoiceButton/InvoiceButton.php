<?php

namespace Crm\InvoicesModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\InvoicesModule\Repository\InvoicesRepository;

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
        $this->template->admin = $this->admin;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
