<?php
declare(strict_types=1);

namespace Crm\InvoicesModule\Components\InvoiceDetailsWidget;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\InvoicesModule\Forms\ChangeInvoiceDetailsFormFactory;
use Nette\Localization\Translator;

class InvoiceDetailsWidget extends BaseLazyWidget
{
    private $templateName = 'invoice_details_widget.latte';

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private readonly Translator $translator,
        private readonly ChangeInvoiceDetailsFormFactory $changeInvoiceDetailsFormFactory,
        private readonly ApplicationConfig $applicationConfig,
    ) {
        parent::__construct($lazyWidgetManager);
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
        $form = $this->changeInvoiceDetailsFormFactory->create($this->getPresenter()->user);

        $this->changeInvoiceDetailsFormFactory->onSuccess = function ($form, $user) {
            $message = $this->translator->translate(
                'invoices.frontend.change_invoice_details.warning',
                [
                    'link' => $this->getPresenter()->link(':Payments:Payments:my'),
                    'contactEmail' => $this->applicationConfig->get('contact_email'),
                ],
            );

            $this->getPresenter()->flashMessage($this->translator->translate('invoices.frontend.change_invoice_details.success'));
            $this->getPresenter()->flashMessage($message, 'warning');
            $this->getPresenter()->redirect('this');
        };
        return $form;
    }
}
