<?php

namespace Crm\InvoicesModule\Forms;

use Crm\ApplicationModule\Forms\Controls\CountriesSelectItemsBuilder;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\UI\Form;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\UsersModule\DataProviders\AddressFormDataProviderInterface;
use Crm\UsersModule\Repositories\CountriesRepository;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class ChangeInvoiceFormFactory
{
    public $onSuccess;

    public function __construct(
        private readonly Translator $translator,
        private readonly InvoicesRepository $invoicesRepository,
        private readonly DataProviderManager $dataProviderManager,
        private readonly CountriesSelectItemsBuilder $countriesSelectItemsBuilder,
        private readonly CountriesRepository $countriesRepository,
    ) {
    }

    /**
     * @param int $id
     * @return Form
     */
    public function create(int $id)
    {
        $form = new Form;
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addHidden('invoice_id', $id);

        $form->addText('buyer_name', 'invoices.form.invoice.label.company_name')
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.company_name');

        $form->addText('buyer_address', 'invoices.form.invoice.label.street_and_number')
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.street_and_number');

        $form->addText('buyer_city', 'invoices.form.invoice.label.city')
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.city');

        $form->addText('buyer_zip', 'invoices.form.invoice.label.zip')
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.zip');

        $form->addSelect('country', 'invoices.form.invoice.label.country', $this->countriesSelectItemsBuilder->getAllIsoPairs());

        $form->addText('company_id', 'invoices.form.invoice.label.company_id')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.company_id');

        $form->addText('company_tax_id', 'invoices.form.invoice.label.company_tax_id')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.company_tax_id');

        $form->addText('company_vat_id', 'invoices.form.invoice.label.company_vat_id')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.company_vat_id');

        /** @var AddressFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('invoices.dataprovider.invoice_form', AddressFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'addressType' => 'invoice']);
        }

        $form->addSubmit('send', 'invoices.form.invoice.label.send');

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $invoice = $this->invoicesRepository->find($values->invoice_id);
        $country = $this->countriesRepository->findByIsoCode($values->country);

        $this->invoicesRepository->update($invoice, [
            'buyer_name' => $values->buyer_name,
            'buyer_address' => $values->buyer_address,
            'buyer_city' => $values->buyer_city,
            'buyer_zip' => $values->buyer_zip,
            'buyer_country_id' => $country->id,
            'buyer_id' => $values->company_id,
            'buyer_tax_id' => $values->company_tax_id,
            'buyer_vat_id' => $values->company_vat_id,
        ]);

        $this->onSuccess->__invoke($form);
    }
}
