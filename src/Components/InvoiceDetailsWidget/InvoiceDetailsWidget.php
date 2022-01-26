<?php

namespace Crm\InvoicesModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\InvoicesModule\Forms\ChangeInvoiceDetailsFormFactory;
use Nette\Localization\ITranslator;

class InvoiceDetailsWidget extends BaseWidget
{
    private $templateName = 'invoice_details_widget.latte';

    private $translator;

    private $changeInvoiceDetailsFormFactory;

    public function __construct(
        WidgetManager $widgetManager,
        ITranslator $translator,
        ChangeInvoiceDetailsFormFactory $changeInvoiceDetailsFormFactory
    ) {
        parent::__construct($widgetManager);

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
                    'link' => $this->presenter->link(':Payments:Payments:my')
                ]
            );

            $this->presenter->flashMessage($this->translator->translate('invoices.frontend.change_invoice_details.success'));
            $this->presenter->flashMessage($message, 'warning');
            $this->presenter->redirect('this');
        };
        return $form;
    }
}
