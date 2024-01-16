<?php

namespace Crm\InvoicesModule\Components\InvoiceButton;

use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\ApplicationModule\Widget\WidgetFactoryInterface;
use Crm\ApplicationModule\Widget\WidgetInterface;
use Crm\InvoicesModule\Repositories\InvoicesRepository;

class InvoiceAdminButtonFactory implements WidgetFactoryInterface
{
    protected LazyWidgetManager $lazyWidgetManager;

    private InvoicesRepository $invoicesRepository;

    public function __construct(
        InvoicesRepository $invoicesRepository,
        LazyWidgetManager $lazyWidgetManager
    ) {
        $this->lazyWidgetManager = $lazyWidgetManager;
        $this->invoicesRepository = $invoicesRepository;
    }

    public function create(): WidgetInterface
    {
        $invoiceButton = new InvoiceButton($this->invoicesRepository, $this->lazyWidgetManager);
        $invoiceButton->setAdmin();
        return $invoiceButton;
    }
}
