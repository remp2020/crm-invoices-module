<?php

namespace Crm\InvoicesModule\Components\DownloadReceiptButton;

use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\ApplicationModule\Widget\WidgetFactoryInterface;
use Crm\ApplicationModule\Widget\WidgetInterface;

class DownloadReceiptButtonFactory implements WidgetFactoryInterface
{
    protected LazyWidgetManager $lazyWidgetManager;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager
    ) {
        $this->lazyWidgetManager = $lazyWidgetManager;
    }

    public function create(): WidgetInterface
    {
        return new DownloadReceiptButton($this->lazyWidgetManager);
    }
}
