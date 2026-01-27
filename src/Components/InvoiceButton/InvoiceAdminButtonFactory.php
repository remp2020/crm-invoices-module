<?php

namespace Crm\InvoicesModule\Components\InvoiceButton;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\ApplicationModule\Models\Widget\WidgetFactoryInterface;
use Crm\ApplicationModule\Models\Widget\WidgetInterface;
use Crm\InvoicesModule\Repositories\InvoicesRepository;

class InvoiceAdminButtonFactory implements WidgetFactoryInterface
{
    public function __construct(
        private readonly InvoicesRepository $invoicesRepository,
        private readonly LazyWidgetManager $lazyWidgetManager,
        private readonly DataProviderManager $dataProviderManager,
    ) {
    }

    public function create(): WidgetInterface
    {
        $invoiceButton = new InvoiceButton(
            invoicesRepository: $this->invoicesRepository,
            lazyWidgetManager: $this->lazyWidgetManager,
            dataProviderManager: $this->dataProviderManager,
        );
        $invoiceButton->setAdmin();
        return $invoiceButton;
    }
}
