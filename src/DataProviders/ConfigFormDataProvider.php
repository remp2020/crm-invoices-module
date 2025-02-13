<?php

namespace Crm\InvoicesModule\DataProviders;

use Contributte\Translation\Translator;
use Crm\AdminModule\DataProviders\ConfigFormDataProviderInterface;
use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\UI\Form;
use Crm\InvoicesModule\Repositories\InvoicesRepository;

class ConfigFormDataProvider implements ConfigFormDataProviderInterface
{
    public const GENERATE_INVOICE_NUMBER_LIMIT = 15;

    public function __construct(private Translator $translator)
    {
    }

    public function provide(array $params): Form
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('missing [form] within data provider params');
        }

        /** @var Form $form */
        $form = $params['form'];
        if ($form->getComponent('generate_invoice_number_for_paid_payment', false)) {
            $generateInvoiceLimitFromDays = $form->getComponent(InvoicesRepository::GENERATE_INVOICE_LIMIT_FROM_DAYS);

            $form->getComponent('generate_invoice_number_for_paid_payment')
                ->addConditionOn($generateInvoiceLimitFromDays, Form::Min, self::GENERATE_INVOICE_NUMBER_LIMIT + 1)
                ->setRequired($this->translator->translate(
                    'invoices.config.generate_invoice_number_for_paid_payment.required_because_of_invoice_limit_from_days',
                    [
                        'days' => self::GENERATE_INVOICE_NUMBER_LIMIT
                    ]
                ));
        }
        return $form;
    }
}
