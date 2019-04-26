<?php

namespace Crm\InvoicesModule\Components;

use Crm\ApplicationModule\Widget\WidgetFactoryInterface;
use Crm\ApplicationModule\Widget\WidgetManager;

class DownloadReceiptButtonFactory implements WidgetFactoryInterface
{
    /** @var WidgetManager */
    protected $widgetManager;

    public function __construct(WidgetManager $widgetManager)
    {
        $this->widgetManager = $widgetManager;
    }

    public function create()
    {
        $receiptButton = new DownloadReceiptButton($this->widgetManager);
        return $receiptButton;
    }
}
