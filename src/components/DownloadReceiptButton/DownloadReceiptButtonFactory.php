<?php

namespace Crm\InvoicesModule\Components;

use Crm\ApplicationModule\Widget\WidgetFactoryInterface;
use Crm\ApplicationModule\Widget\WidgetInterface;
use Crm\ApplicationModule\Widget\WidgetManager;

class DownloadReceiptButtonFactory implements WidgetFactoryInterface
{
    protected $widgetManager;

    public function __construct(
        WidgetManager $widgetManager
    ) {
        $this->widgetManager = $widgetManager;
    }

    public function create(): WidgetInterface
    {
        return new DownloadReceiptButton($this->widgetManager);
    }
}
