<?php

namespace Crm\InvoicesModule\Forms;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\UsersModule\DataProvider\AddressFormDataProviderInterface;
use Crm\UsersModule\Repository\AddressChangeRequestsRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Kdyby\Translation\Translator;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Tomaj\Form\Renderer\BootstrapRenderer;

class UserInvoiceFormFactory
{
    private $usersRepository;
    private $addressesRepository;
    private $countriesRepository;
    private $applicationConfig;
    private $addressChangeRequestsRepository;

    public $onSave;

    /** @var IRow */
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
        $form = new Form;

        $this->payment = $payment;
        $user = $this->payment->user;

        $invoiceAddress = $this->addressesRepository->address($user, 'invoice');

        $form->addProtection();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->getElementPrototype()->addClass('ajax');

        $form->addTextArea('company_name', 'invoices.form.invoice.label.company_name', null, 1)
            ->setMaxLength(150)
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.company_name')
            ->setRequired('invoices.form.invoice.required.company_name');
        $form->addText('address', 'invoices.form.invoice.label.address')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.address')
            ->setRequired('invoices.form.invoice.required.address');
        $form->addText('number', 'invoices.form.invoice.label.number')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.number')
            ->setRequired('invoices.form.invoice.required.number');
        $form->addText('city', 'invoices.form.invoice.label.city')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.city')
            ->setRequired('invoices.form.invoice.required.city');
        $form->addText('zip', 'invoices.form.invoice.label.zip')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.zip')
            ->setRequired('invoices.form.invoice.required.zip');
        $form->addText('company_id', 'invoices.form.invoice.label.company_id')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.company_id');
        $form->addText('company_tax_id', 'invoices.form.invoice.label.company_tax_id')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.company_tax_id');
        $form->addText('company_vat_id', 'invoices.form.invoice.label.company_vat_id')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.company_vat_id');

        $contactEmail = $this->applicationConfig->get('contact_email');
        $form->addSelect('country_id', 'invoices.form.invoice.label.country_id', $this->countriesRepository->getDefaultCountryPair())
            ->setOption('id', 'invoice-country')
            ->setOption(
                'description',
                $this->translator->translate('invoices.form.invoice.options.foreign_country', ['contactEmail' => $contactEmail])
            );

        $form->addHidden('VS', $payment->variable_symbol);

        $form->addHidden('done', $invoiceAddress ? 1 : 0);

        /** @var AddressFormDataProviderInterface $providers */
        $providers = $this->dataProviderManager->getProviders('invoices.dataprovider.invoice_address_form', AddressFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'addressType' => 'invoice']);
        }

        $form->addSubmit('send', 'invoices.form.invoice.label.save')
            ->getControlPrototype()
            ->setName('button')
            ->setAttribute('class', 'btn btn-success')
            ->setAttribute('style', 'float: right')
            ->setHtml($this->translator->translate('invoices.form.invoice.label.save'));

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
                'country_id' => $invoiceAddress->country_id ? $invoiceAddress->country_id : $this->countriesRepository->defaultCountry()->id,
            ]);
        }

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $user = $this->payment->user;

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

        // invoice address can be accepted automatically
        if ($changeRequest) {
            $this->addressChangeRequestsRepository->acceptRequest($changeRequest);
        }

        $updateArray = [
            'invoice' => 1,
        ];
        $this->usersRepository->update($user, $updateArray);

        $this->onSave->__invoke($form, $user);
    }
}
