<?php

namespace Crm\InvoicesModule\Components\InvoiceDetailsWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\InvoicesModule\Forms\ChangeInvoiceDetailsFormFactory;
use Nette\Localization\Translator;

class InvoiceDetailsWidget extends BaseLazyWidget
{
    private $templateName = 'invoice_details_widget.latte';

    private $translator;

    private $changeInvoiceDetailsFormFactory;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        Translator $translator,
        ChangeInvoiceDetailsFormFactory $changeInvoiceDetailsFormFactory,
    ) {
        parent::__construct($lazyWidgetManager);

        $this->translator = $translator;
        $this->changeInvoiceDetailsFormFactory = $changeInvoiceDetailsFormFactory;
    }

    public function identifier()
    {
        return 'invoicedetailswidget';
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function createComponentChangeInvoiceDetailsForm()
    {
        $form = $this->changeInvoiceDetailsFormFactory->create($this->presenter->user);

        $this->changeInvoiceDetailsFormFactory->onSuccess = function ($form, $user) {
            $message = $this->translator->translate(
                'invoices.frontend.change_invoice_details.warning',
                null,
                [
                    'link' => $this->presenter->link(':Payments:Payments:my'),
                ],
            );

            $this->presenter->flashMessage($this->translator->translate('invoices.frontend.change_invoice_details.success'));
            $this->presenter->flashMessage($message, 'warning');
            $this->presenter->redirect('this');
        };
        return $form;
    }
}
