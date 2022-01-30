<?php

namespace Crm\InvoicesModule\Components;

use Crm\ApplicationModule\Widget\WidgetFactoryInterface;
use Crm\ApplicationModule\Widget\WidgetInterface;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\InvoicesModule\Repository\InvoicesRepository;

class InvoiceAdminButtonFactory implements WidgetFactoryInterface
{
    /** @var WidgetManager */
    protected $widgetManager;

    private $invoicesRepository;

    public function __construct(
        InvoicesRepository $invoicesRepository,
        WidgetManager $widgetManager
    ) {
        $this->widgetManager = $widgetManager;
        $this->invoicesRepository = $invoicesRepository;
    }

    public function create(): WidgetInterface
    {
        $invoiceButton = new InvoiceButton($this->invoicesRepository, $this->widgetManager);
        $invoiceButton->setAdmin();
        return $invoiceButton;
    }
}
