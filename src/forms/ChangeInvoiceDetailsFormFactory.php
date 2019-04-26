<?php

namespace Crm\InvoicesModule\Forms;

use Crm\UsersModule\Repository\AddressChangeRequestsRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Nette\Security\User;
use Tomaj\Form\Renderer\BootstrapRenderer;

class ChangeInvoiceDetailsFormFactory
{
    /* callback function */
    public $onSuccess;

    /** @var  User */
    private $user;

    private $usersRepository;

    private $addressesRepository;

    private $countriesRepository;

    private $addressChangeRequestsRepository;

    private $emitter;

    private $translator;

    public function __construct(
        UsersRepository $usersRepository,
        AddressesRepository $addressesRepository,
        CountriesRepository $countriesRepository,
        AddressChangeRequestsRepository $addressChangeRequestsRepository,
        Emitter $emitter,
        ITranslator $translator
    ) {
        $this->usersRepository = $usersRepository;
        $this->addressesRepository = $addressesRepository;
        $this->countriesRepository = $countriesRepository;
        $this->addressChangeRequestsRepository = $addressChangeRequestsRepository;
        $this->emitter = $emitter;
        $this->translator = $translator;
    }

    /**
     * @param User $user
     * @return Form
     */
    public function create(User $user)
    {
        $form = new Form;
        $this->user = $user;

        $row = $this->loadUserRow();

        $invoiceAddress = $this->addressesRepository->address($row, 'invoice');
        $defaults = [];

        if ($invoiceAddress) {
            $defaults = [
                'invoice' => $row->invoice,
                'company_name' => $invoiceAddress->company_name ? $invoiceAddress->company_name : '',
                'address' => $invoiceAddress->address ? $invoiceAddress->address : '',
                'number' => $invoiceAddress->number,
                'city' => $invoiceAddress->city,
                'zip' => $invoiceAddress->zip,
                'company_id' => $invoiceAddress->company_id,
                'company_tax_id' => $invoiceAddress->company_tax_id,
                'company_vat_id' => $invoiceAddress->company_vat_id
            ];
        }

        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();

        $invoiceCheckbox = $form->addCheckbox('invoice', $this->translator->translate('invoices.frontend.change_invoice_details.invoice'));

        $form->addTextArea(
            'company_name',
            $this->translator->translate('invoices.frontend.change_invoice_details.company_name.label'),
            null,
            1
        )
            ->setMaxLength(150)
            ->setAttribute('placeholder', $this->translator->translate('invoices.frontend.change_invoice_details.company_name.placeholder'))
            ->addConditionOn($invoiceCheckbox, Form::EQUAL, true)
            ->setRequired($this->translator->translate('invoices.frontend.change_invoice_details.company_name.required'));
        $form->addText('address', $this->translator->translate('invoices.frontend.change_invoice_details.address.label'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.frontend.change_invoice_details.address.placeholder'))
            ->addConditionOn($invoiceCheckbox, Form::EQUAL, true)
            ->setRequired($this->translator->translate('invoices.frontend.change_invoice_details.address.required'));
        $form->addText('number', $this->translator->translate('invoices.frontend.change_invoice_details.number.label'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.frontend.change_invoice_details.number.placeholder'))
            ->addConditionOn($invoiceCheckbox, Form::EQUAL, true)
            ->setRequired($this->translator->translate('invoices.frontend.change_invoice_details.number.required'));
        $form->addText('city', $this->translator->translate('invoices.frontend.change_invoice_details.city.label'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.frontend.change_invoice_details.city.placeholder'))
            ->addConditionOn($invoiceCheckbox, Form::EQUAL, true)
            ->setRequired($this->translator->translate('invoices.frontend.change_invoice_details.city.required'));
        $form->addText('zip', $this->translator->translate('invoices.frontend.change_invoice_details.zip.label'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.frontend.change_invoice_details.zip.placeholder'))
            ->addConditionOn($invoiceCheckbox, Form::EQUAL, true)
            ->setRequired($this->translator->translate('invoices.frontend.change_invoice_details.zip.required'));
        $form->addText('company_id', $this->translator->translate('invoices.frontend.change_invoice_details.company_id.label'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.frontend.change_invoice_details.company_id.placeholder'))
            ->addConditionOn($invoiceCheckbox, Form::EQUAL, true)
            ->setRequired($this->translator->translate('invoices.frontend.change_invoice_details.company_id.required'));
        $form->addText('company_tax_id', $this->translator->translate('invoices.frontend.change_invoice_details.company_tax_id.label'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.frontend.change_invoice_details.company_tax_id.placeholder'))
            ->setRequired($this->translator->translate('invoices.frontend.change_invoice_details.company_tax_id.required'));
        $form->addText('company_vat_id', $this->translator->translate('invoices.frontend.change_invoice_details.company_vat_id.label'))
            ->setAttribute('placeholder', $this->translator->translate('invoices.frontend.change_invoice_details.company_vat_id.placeholder'))
            ->addConditionOn($invoiceCheckbox, Form::EQUAL, true)
            ->setRequired($this->translator->translate('invoices.frontend.change_invoice_details.company_vat_id.required'));

        $form->addSubmit('send', $this->translator->translate('invoices.frontend.change_invoice_details.submit'));

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    private function loadUserRow()
    {
        $row = $this->usersRepository->find($this->user->id);
        if (!$row) {
            throw new BadRequestException();
        }
        return $row;
    }

    public function formSucceeded($form, $values)
    {
        $userRow = $this->loadUserRow();

        $this->usersRepository->update($userRow, ['invoice' => $values['invoice']]);

        $invoiceAddress = $this->addressesRepository->address($userRow, 'invoice');
        $changeRequest = $this->addressChangeRequestsRepository->add(
            $userRow,
            $invoiceAddress,
            null,
            null,
            $values->company_name,
            $values->address,
            $values->number,
            $values->city,
            $values->zip,
            $this->countriesRepository->defaultCountry()->id,
            $values->company_id,
            $values->company_tax_id,
            $values->company_vat_id,
            null,
            'invoice'
        );
        if ($changeRequest) {
            $this->addressChangeRequestsRepository->acceptRequest($changeRequest);
        }

        $this->onSuccess->__invoke($form, $userRow);
    }
}
