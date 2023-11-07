<?php

namespace Crm\InvoicesModule\Forms;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\UsersModule\DataProvider\AddressFormDataProviderInterface;
use Crm\UsersModule\Repository\AddressChangeRequestsRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Tomaj\Form\Renderer\BootstrapRenderer;

class UserInvoiceFormFactory
{
    private $usersRepository;
    private $addressesRepository;
    private $countriesRepository;
    private $applicationConfig;
    private $addressChangeRequestsRepository;

    public $onSave;

    /** @var ActiveRow */
    private $payment;

    private $translator;

    private $dataProviderManager;

    public function __construct(
        Translator $translator,
        UsersRepository $usersRepository,
        ApplicationConfig $applicationConfig,
        CountriesRepository $countriesRepository,
        AddressesRepository $addressesRepository,
        AddressChangeRequestsRepository $addressChangeRequestsRepository,
        DataProviderManager $dataProviderManager
    ) {
        $this->translator = $translator;
        $this->usersRepository = $usersRepository;
        $this->applicationConfig = $applicationConfig;
        $this->countriesRepository = $countriesRepository;
        $this->addressesRepository = $addressesRepository;
        $this->addressChangeRequestsRepository = $addressChangeRequestsRepository;
        $this->dataProviderManager = $dataProviderManager;
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
        $form->addText('address', 'invoices.form.invoice.label.address')
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.address')
            ->setRequired('invoices.form.invoice.required.address');
        $form->addText('number', 'invoices.form.invoice.label.number')
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.number')
            ->setRequired('invoices.form.invoice.required.number');
        $form->addText('city', 'invoices.form.invoice.label.city')
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.city')
            ->setRequired('invoices.form.invoice.required.city');
        $form->addText('zip', 'invoices.form.invoice.label.zip')
            ->setHtmlAttribute('placeholder', 'invoices.form.invoice.placeholder.zip')
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

        $countrySelect = $form->addSelect('country_id', 'invoices.form.invoice.label.country_id', $this->countriesRepository->getDefaultCountryPair())
            ->setOption('id', 'invoice-country')
            ->setOption(
                'description',
                $this->translator->translate('invoices.form.invoice.options.foreign_country', ['contactEmail' => $this->applicationConfig->get('contact_email')])
            );

        // at the moment, only default country is allowed for invoicing (we are missing VATs for other countries)
        // but few legacy invoice addresses contain non-default country
        $defaultCountryId = $this->countriesRepository->defaultCountry()->id;
        if (isset($invoiceAddress->country_id) && $invoiceAddress->country_id !== $defaultCountryId) {
            $country = $this->countriesRepository->find($invoiceAddress->country_id);
            if ($country) {
                // add user's non-default country into select list; otherwise FORM returns error (out of allowed values)
                $countrySelect->setItems([$country->id => $country->name]);
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
                'address' => $invoiceAddress->address,
                'number' => $invoiceAddress->number,
                'zip' => $invoiceAddress->zip,
                'city' => $invoiceAddress->city,
                'country_id' => $invoiceAddress->country_id ?? $defaultCountryId,
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
        if ($values->country_id !== $this->countriesRepository->defaultCountry()->id) {
            // no error; error is displayed by form (defined for element country_id)
            return;
        }

        $invoiceAddress = $this->addressesRepository->address($user, 'invoice');
        $changeRequest = $this->addressChangeRequestsRepository->add(
            $user,
            $invoiceAddress,
            null,
            null,
            $values->company_name,
            $values->address,
            $values->number,
            $values->city,
            $values->zip,
            $values->country_id,
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
