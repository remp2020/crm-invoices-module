<?php

namespace Crm\InvoicesModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;

/**
 * Download receipt button widget.
 *
 * This components renders simple button to download receipt.
 * Used in user frontend payments listing and admin payments listing.
 *
 * @package Crm\InvoicesModule\Components
 */
class DownloadReceiptButton extends BaseWidget
{
    private $templateName = 'download_receipt_button.latte';

    private bool $admin = false;

    public function __construct(
        WidgetManager $widgetManager
    ) {
        parent::__construct($widgetManager);
    }

    public function identifier()
    {
        return 'downloadreceiptbutton';
    }

    public function setAdmin()
    {
        $this->admin = true;
    }

    public function render($payment)
    {
        $isReceiptable = $payment->related('payment_meta')
            ->where('key', 'receiptable')
            ->fetch();

        $this->template->paymentId = $payment->id;
        $this->template->showButton = $isReceiptable;
        $this->template->admin = $this->admin;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
