<?php

namespace Crm\InvoicesModule\Forms;

use Crm\ApplicationModule\Config\ApplicationConfig;
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

    public function __construct(
        Translator $translator,
        UsersRepository $usersRepository,
        ApplicationConfig $applicationConfig,
        CountriesRepository $countriesRepository,
        AddressesRepository $addressesRepository,
        AddressChangeRequestsRepository $addressChangeRequestsRepository
    ) {
        $this->translator = $translator;
        $this->usersRepository = $usersRepository;
        $this->applicationConfig = $applicationConfig;
        $this->countriesRepository = $countriesRepository;
        $this->addressesRepository = $addressesRepository;
        $this->addressChangeRequestsRepository = $addressChangeRequestsRepository;
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
        $form->addText('invoice_address', 'invoices.form.invoice.label.address')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.address')
            ->setRequired('invoices.form.invoice.required.address');
        $form->addText('invoice_number', 'invoices.form.invoice.label.number')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.number')
            ->setRequired('invoices.form.invoice.required.number');
        $form->addText('invoice_city', 'invoices.form.invoice.label.city')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.city')
            ->setRequired('invoices.form.invoice.required.city');
        $form->addText('invoice_zip', 'invoices.form.invoice.label.zip')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.zip')
            ->setRequired('invoices.form.invoice.required.zip');
        $form->addText('invoice_company_id', 'invoices.form.invoice.label.ico')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.ico')
            ->setRequired('invoices.form.invoice.required.ico');
        $form->addText('invoice_company_tax_id', 'invoices.form.invoice.label.dic')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.dic')
            ->setRequired('invoices.form.invoice.required.dic');
        $form->addText('invoice_company_vat_id', 'invoices.form.invoice.label.icdph')
            ->setAttribute('placeholder', 'invoices.form.invoice.placeholder.icdph')
            ->setRequired('invoices.form.invoice.required.icdph');


        $contactEmail = $this->applicationConfig->get('contact_email');
        $form->addSelect('invoice_country_id', 'invoices.form.invoice.label.invoice_country_id', $this->countriesRepository->getDefaultCountryPair())
            ->setOption('id', 'invoice-country')
            ->setOption(
                'description',
                $this->translator->translate('invoices.form.invoice.options.foreign_country', ['contactEmail' => $contactEmail])
            );

        $form->addHidden('VS', $payment->variable_symbol);

        $form->addHidden('done', $invoiceAddress ? 1 : 0);

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
                'invoice_company_id' => $invoiceAddress->company_id,
                'invoice_company_tax_id' => $invoiceAddress->company_tax_id,
                'invoice_company_vat_id' => $invoiceAddress->company_vat_id,
                'invoice_address' => $invoiceAddress->address,
                'invoice_number' => $invoiceAddress->number,
                'invoice_zip' => $invoiceAddress->zip,
                'invoice_city' => $invoiceAddress->city,
                'invoice_country_id' => $invoiceAddress->country_id ? $invoiceAddress->country_id : $this->countriesRepository->defaultCountry()->id,
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
            $values->invoice_address,
            $values->invoice_number,
            $values->invoice_city,
            $values->invoice_zip,
            $values->invoice_country_id,
            $values->invoice_company_id,
            $values->invoice_company_tax_id,
            $values->invoice_company_vat_id,
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
