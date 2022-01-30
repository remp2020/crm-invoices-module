<?php

namespace Crm\InvoicesModule\Forms;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\UsersModule\DataProvider\AddressFormDataProviderInterface;
use Crm\UsersModule\Repository\CountriesRepository;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class ChangeInvoiceFormFactory
{
    public $onSuccess;

    private $translator;

    private $invoicesRepository;

    private $dataProviderManager;

    private $countriesRepository;

    public function __construct(
        ITranslator $translator,
        InvoicesRepository $invoicesRepository,
        DataProviderManager $dataProviderManager,
        CountriesRepository $countriesRepository
    ) {
        $this->translator = $translator;
        $this->invoicesRepository = $invoicesRepository;
        $this->dataProviderManager = $dataProviderManager;
        $this->countriesRepository = $countriesRepository;
    }

    /**
     * @param int $id
     * @return Form
     */
    public function create(int $id)
    {
        $form = new Form;
        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();

        $form->addHidden('invoice_id', $id);

        $form->addText('buyer_name', $this->translator->translate('invoices.form.invoice.label.company_name'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.form.invoice.placeholder.company_name'));

        $form->addText('buyer_address', $this->translator->translate('invoices.form.invoice.label.address'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.form.invoice.placeholder.address'));

        $form->addText('buyer_city', $this->translator->translate('invoices.form.invoice.label.city'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.form.invoice.placeholder.city'));

        $form->addText('buyer_zip', $this->translator->translate('invoices.form.invoice.label.zip'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.form.invoice.placeholder.zip'));

        $form->addSelect('country_id', $this->translator->translate('invoices.form.invoice.label.country_id'), $this->countriesRepository->getAllPairs());

        $form->addText('company_id', $this->translator->translate('invoices.form.invoice.label.company_id'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.form.invoice.placeholder.company_id'));

        $form->addText('company_tax_id', $this->translator->translate('invoices.form.invoice.label.company_tax_id'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.form.invoice.placeholder.company_tax_id'));

        $form->addText('company_vat_id', $this->translator->translate('invoices.form.invoice.label.company_vat_id'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.form.invoice.placeholder.company_vat_id'));

        /** @var AddressFormDataProviderInterface $providers */
        $providers = $this->dataProviderManager->getProviders('invoices.dataprovider.invoice_form', AddressFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'addressType' => 'invoice']);
        }

        $form->addSubmit('send', $this->translator->translate('invoices.form.invoice.label.send'));

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $invoice = $this->invoicesRepository->find($values->invoice_id);

        $this->invoicesRepository->update($invoice, [
            'buyer_name' => $values->buyer_name,
            'buyer_address' => $values->buyer_address,
            'buyer_city' => $values->buyer_city,
            'buyer_zip' => $values->buyer_zip,
            'buyer_country_id' => $values->country_id,
            'buyer_id' => $values->company_id,
            'buyer_tax_id' => $values->company_tax_id,
            'buyer_vat_id' => $values->company_vat_id
        ]);

        $this->onSuccess->__invoke($form);
    }
}
