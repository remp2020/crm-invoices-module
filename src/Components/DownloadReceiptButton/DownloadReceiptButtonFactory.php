<?php

namespace Crm\InvoicesModule\Components\DownloadReceiptButton;

use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\ApplicationModule\Models\Widget\WidgetFactoryInterface;
use Crm\ApplicationModule\Models\Widget\WidgetInterface;

class DownloadReceiptButtonFactory implements WidgetFactoryInterface
{
    protected LazyWidgetManager $lazyWidgetManager;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
    ) {
        $this->lazyWidgetManager = $lazyWidgetManager;
    }

    public function create(): WidgetInterface
    {
        return new DownloadReceiptButton($this->lazyWidgetManager);
    }
}
