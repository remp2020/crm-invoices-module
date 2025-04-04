<?php

namespace Crm\InvoicesModule\Forms;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Forms\Controls\CountriesSelectItemsBuilder;
use Crm\ApplicationModule\Forms\FormPatterns;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\UI\Form;
use Crm\UsersModule\DataProviders\AddressFormDataProviderInterface;
use Crm\UsersModule\Repositories\AddressChangeRequestsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Tomaj\Form\Renderer\BootstrapRenderer;

class UserInvoiceFormFactory
{
    public $onSave;

    private ?ActiveRow $payment;

    public function __construct(
        private readonly Translator $translator,
        private readonly UsersRepository $usersRepository,
        private readonly ApplicationConfig $applicationConfig,
        private readonly CountriesRepository $countriesRepository,
        private readonly AddressesRepository $addressesRepository,
        private readonly AddressChangeRequestsRepository $addressChangeRequestsRepository,
        private readonly DataProviderManager $dataProviderManager,
        private readonly CountriesSelectItemsBuilder $countriesSelectItemsBuilder,
    ) {
    }

    public function create(ActiveRow $payment): Form
    {
        $form = new Form();

        $this->payment = $payment;
        $user = $this->payment->user;

        $invoiceAddress = $this->addressesRepository->address($user, 'invoice');

        $form->addProtection();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->getElementPrototype()->addClass('ajax');

        $form->addTextArea('company_name', 'invoices.form.invoice.label.company_name', null, 1)
            ->setMaxLength(150)
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.company_name')
            ->setRequired('invoices.form.invoice.required.company_name');
        $form->addText('street', 'invoices.form.invoice.label.street')
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.street')
            ->addRule($form::Pattern, 'invoices.frontend.change_invoice_details.street.pattern', FormPatterns::STREET_NAME)
            ->setRequired('invoices.form.invoice.required.street');
        $form->addText('number', 'invoices.form.invoice.label.number')
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.number')
            ->addRule($form::Pattern, 'invoices.frontend.change_invoice_details.number.pattern', FormPatterns::STREET_NUMBER)
            ->setRequired('invoices.form.invoice.required.number');
        $form->addText('city', 'invoices.form.invoice.label.city')
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.city')
            ->setRequired('invoices.form.invoice.required.city');
        $form->addText('zip', 'invoices.form.invoice.label.zip')
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.zip')
            ->addRule($form::Pattern, 'invoices.frontend.change_invoice_details.zip.pattern', FormPatterns::ZIP_CODE)
            ->setRequired('invoices.form.invoice.required.zip');
        $form->addText('company_id', 'invoices.form.invoice.label.company_id')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.company_id');
        $form->addText('company_tax_id', 'invoices.form.invoice.label.company_tax_id')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.company_tax_id');
        $form->addText('company_vat_id', 'invoices.form.invoice.label.company_vat_id')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.company_vat_id');

        $countrySelect = $form->addSelect('country', 'invoices.form.invoice.label.country_id', $this->countriesSelectItemsBuilder->getDefaultCountryIsoPair())
            ->setOption('id', 'invoice-country')
            ->setOption(
                'description',
                $this->translator->translate('invoices.form.invoice.options.foreign_country', ['contactEmail' => $this->applicationConfig->get('contact_email')])
            );

        // at the moment, only default country is allowed for invoicing (we are missing VATs for other countries)
        // but few legacy invoice addresses contain non-default country
        $defaultCountryIsoCode = $this->countriesRepository->defaultCountry()->iso_code;
        if (isset($invoiceAddress->country_id) && $invoiceAddress->country->iso_code !== $defaultCountryIsoCode) {
            $country = $this->countriesRepository->find($invoiceAddress->country_id);
            if ($country) {
                // add user's non-default country into select list; otherwise FORM returns error (out of allowed values)
                $countrySelect->setItems([$country->iso_code => $country->name]);
                // element & element description are highlighted as error when addError is triggered; no need to duplicate message
                $countrySelect->addError('');
                // and add bigger error above form
                $form->addError($this->translator->translate(
                    'invoices.form.invoice.options.foreign_country',
                    ['contactEmail' => $this->applicationConfig->get('contact_email')]
                ));
            }
        }

        $form->addHidden('VS', $payment->variable_symbol);

        $form->addHidden('done', $invoiceAddress ? 1 : 0);

        $form->onSuccess[] = [$this, 'formSucceeded'];

        /** @var AddressFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('invoices.dataprovider.invoice_address_form', AddressFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'addressType' => 'invoice', 'payment' => $payment]);
        }

        $form->addSubmit('send', 'invoices.form.invoice.label.save')
            ->getControlPrototype()
            ->setName('button')
            ->setAttribute('class', 'btn btn-success')
            ->setAttribute('style', 'float: right');

        $defaults = [];

        if ($invoiceAddress) {
            $defaults = array_merge($defaults, [
                'company_name' => $invoiceAddress->company_name,
                'company_id' => $invoiceAddress->company_id,
                'company_tax_id' => $invoiceAddress->company_tax_id,
                'company_vat_id' => $invoiceAddress->company_vat_id,
                'street' => $invoiceAddress->street,
                'number' => $invoiceAddress->number,
                'zip' => $invoiceAddress->zip,
                'city' => $invoiceAddress->city,
                'country' => $invoiceAddress->country->iso_code ?? $defaultCountryIsoCode,
            ]);
        }

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceededAfterProviders'];

        return $form;
    }

    public function formSucceeded(Form $form, $values)
    {
        $user = $this->payment->user;

        // do not allow saving address with non-default country
        // - at the moment we do not have correct VAT for foreign countries
        if ($values->country !== $this->countriesRepository->defaultCountry()->iso_code) {
            // no error; error is displayed by form (defined for element country_id)
            return;
        }

        $country = $this->countriesRepository->findByIsoCode($values->country);

        $invoiceAddress = $this->addressesRepository->address($user, 'invoice');
        $changeRequest = $this->addressChangeRequestsRepository->add(
            $user,
            $invoiceAddress,
            null,
            null,
            $values->company_name,
            $values->street,
            $values->number,
            $values->city,
            $values->zip,
            $country->id,
            $values->company_id,
            $values->company_tax_id,
            $values->company_vat_id,
            null,
            'invoice'
        );

        $updateArray = [
            'invoice' => 1,
        ];
        $this->usersRepository->update($user, $updateArray);

        // invoice address can be accepted automatically
        if ($changeRequest) {
            $this->addressChangeRequestsRepository->acceptRequest($changeRequest);
        }
    }

    public function formSucceededAfterProviders(Form $form, $values): void
    {
        $this->onSave->__invoke($form, $this->payment->user);
    }
}
