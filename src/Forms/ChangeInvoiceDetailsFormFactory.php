<?php

namespace Crm\InvoicesModule\Forms;

use Crm\ApplicationModule\Forms\Controls\CountriesSelectItemsBuilder;
use Crm\ApplicationModule\Forms\FormPatterns;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\DataProviders\AddressFormDataProviderInterface;
use Crm\UsersModule\Repositories\AddressChangeRequestsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Application\BadRequestException;
use Nette\Localization\Translator;
use Nette\Security\User;
use Tomaj\Form\Renderer\BootstrapRenderer;
use Tomaj\Hermes\Emitter;

class ChangeInvoiceDetailsFormFactory
{
    /* callback function */
    public $onSuccess;

    private User $user;

    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly AddressesRepository $addressesRepository,
        private readonly CountriesRepository $countriesRepository,
        private readonly AddressChangeRequestsRepository $addressChangeRequestsRepository,
        private readonly Translator $translator,
        private readonly DataProviderManager $dataProviderManager,
        private readonly ApplicationConfig $applicationConfig,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly Emitter $hermesEmitter,
        private readonly CountriesSelectItemsBuilder $countriesSelectItemsBuilder,
    ) {
    }

    /**
     * @param User $user
     * @return Form
     * @throws DataProviderException
     */
    public function create(User $user): Form
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
                'street' => $invoiceAddress->street ? $invoiceAddress->street : '',
                'number' => $invoiceAddress->number,
                'city' => $invoiceAddress->city,
                'zip' => $invoiceAddress->zip,
                'company_id' => $invoiceAddress->company_id,
                'company_tax_id' => $invoiceAddress->company_tax_id,
                'company_vat_id' => $invoiceAddress->company_vat_id
            ];
        }

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $invoiceCheckbox = $form->addCheckbox('invoice', $this->translator->translate('invoices.frontend.change_invoice_details.invoice'));

        $form->addTextArea(
            'company_name',
            $this->translator->translate('invoices.frontend.change_invoice_details.company_name.label'),
            null,
            1
        )
            ->setMaxLength(150)
            ->setHtmlAttribute('placeholder', 'invoices.frontend.change_invoice_details.company_name.placeholder')
            ->setHtmlAttribute('autocomplete', 'organization')
            ->setNullable()
            ->addConditionOn($invoiceCheckbox, $form::Equal, true)
            ->setRequired('invoices.frontend.change_invoice_details.company_name.required');
        $form->addText('street', 'invoices.frontend.change_invoice_details.street.label')
            ->setHtmlAttribute('placeholder', 'invoices.frontend.change_invoice_details.street.placeholder')
            ->setNullable()
            ->addConditionOn($invoiceCheckbox, $form::Equal, true)
            ->addRule($form::Pattern, 'invoices.frontend.change_invoice_details.street.pattern', FormPatterns::STREET_NAME)
            ->setRequired('invoices.frontend.change_invoice_details.street.required');
        $form->addText('number', 'invoices.frontend.change_invoice_details.number.label')
            ->setHtmlAttribute('placeholder', 'invoices.frontend.change_invoice_details.number.placeholder')
            ->setNullable()
            ->addConditionOn($invoiceCheckbox, $form::Equal, true)
            ->addRule($form::Pattern, 'invoices.frontend.change_invoice_details.number.pattern', FormPatterns::STREET_NUMBER)
            ->setRequired('invoices.frontend.change_invoice_details.number.required');
        $form->addText('city', 'invoices.frontend.change_invoice_details.city.label')
            ->setHtmlAttribute('placeholder', 'invoices.frontend.change_invoice_details.city.placeholder')
            ->setNullable()
            ->addConditionOn($invoiceCheckbox, $form::Equal, true)
            ->setRequired('invoices.frontend.change_invoice_details.city.required');
        $form->addText('zip', $this->translator->translate('invoices.frontend.change_invoice_details.zip.label'))
            ->setHtmlAttribute('placeholder', 'invoices.frontend.change_invoice_details.zip.placeholder')
            ->setHtmlAttribute('autocomplete', 'postal-code')
            ->setNullable()
            ->addConditionOn($invoiceCheckbox, $form::Equal, true)
            ->addRule($form::Pattern, 'invoices.frontend.change_invoice_details.zip.pattern', FormPatterns::ZIP_CODE)
            ->setRequired($this->translator->translate('invoices.frontend.change_invoice_details.zip.required'));
        $form->addText('company_id', 'invoices.frontend.change_invoice_details.company_id.label')
            ->setHtmlAttribute('placeholder', 'invoices.frontend.change_invoice_details.company_id.placeholder')
            ->setNullable();
        $form->addText('company_tax_id', 'invoices.frontend.change_invoice_details.company_tax_id.label')
            ->setHtmlAttribute('placeholder', 'invoices.frontend.change_invoice_details.company_tax_id.placeholder')
            ->setNullable();
        $form->addText('company_vat_id', 'invoices.frontend.change_invoice_details.company_vat_id.label')
            ->setHtmlAttribute('placeholder', 'invoices.frontend.change_invoice_details.company_vat_id.placeholder')
            ->setNullable();

        $contactEmail = $this->applicationConfig->get('contact_email');
        $form->addSelect('country', 'invoices.frontend.change_invoice_details.country_id.label', $this->countriesSelectItemsBuilder->getDefaultCountryIsoPair())
            ->setOption('id', 'invoice-country')
            ->setOption(
                'description',
                $this->translator->translate('invoices.frontend.change_invoice_details.country_id.foreign_country', ['contactEmail' => $contactEmail])
            );

        $form->onSuccess[] = [$this, 'formSucceeded'];

        /** @var AddressFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('invoices.dataprovider.invoice_address_form', AddressFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'addressType' => 'invoice', 'user' => $row]);
        }

        $form->addSubmit('send', $this->translator->translate('invoices.frontend.change_invoice_details.submit'));

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceededAfterProviders'];

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

    public function formSucceeded($form, $values): void
    {
        $userRow = $this->loadUserRow();

        $changedInvoicing = false;
        if ($userRow['invoice'] !== $values['invoice']) {
            $this->usersRepository->update($userRow, ['invoice' => $values['invoice']]);
            $changedInvoicing = true; // saving for later because $userRow is updated
        }

        $invoiceAddress = $this->addressesRepository->address($userRow, 'invoice');
        $changeRequest = $this->addressChangeRequestsRepository->add(
            $userRow,
            $invoiceAddress,
            null,
            null,
            $values->company_name,
            $values->street,
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
        } elseif ($changedInvoicing) {
            // generate invoice for all invoiceable payments
            // if address didn't change but invoicing setting did
            // (if address changed, auto invoicing is handled by event handler AddressChangedHandler)

            if ($userRow->invoice && !$userRow->disable_auto_invoice) {
                $payments = $this->paymentsRepository->userPayments($userRow->id)
                    ->where('invoice_id', null)
                    ->fetchAll();

                foreach ($payments as $payment) {
                    $this->hermesEmitter->emit(new HermesMessage('generate_invoice', [
                        'payment_id' => $payment->id
                    ]), HermesMessage::PRIORITY_LOW);
                }
            }
        }
    }

    public function formSucceededAfterProviders(Form $form, $values): void
    {
        $this->onSuccess->__invoke($form, $this->loadUserRow());
    }
}
