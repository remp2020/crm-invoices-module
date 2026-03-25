<?php

declare(strict_types=1);

namespace Crm\InvoicesModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\DataProviders\AdminFilterFormDataProviderInterface;
use Nette\Database\Table\Selection;

class PaymentsAdminFilterFormDataProvider implements AdminFilterFormDataProviderInterface
{
    public function provide(array $params): Form
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('missing [form] within data provider params');
        }
        if (!($params['form'] instanceof Form)) {
            throw new DataProviderException('invalid type of provided form: ' . get_class($params['form']));
        }

        if (!isset($params['formData'])) {
            throw new DataProviderException('missing [formData] within data provider params');
        }
        if (!is_array($params['formData'])) {
            throw new DataProviderException('invalid type of provided formData: ' . get_class($params['formData']));
        }

        $form = $params['form'];
        $formData = $params['formData'];

        $form->addText('invoice_number', 'invoices.data_provider.payments_admin_filter_form.invoice_number.label');

        $form->setDefaults([
            'invoice_number' => $formData['invoice_number'] ?? null,
        ]);

        return $form;
    }

    public function filter(Selection $selection, array $formData): Selection
    {
        $invoiceNumber = $formData['invoice_number'] ?? null;
        if ($invoiceNumber) {
            $selection->where('invoice_number.number', $invoiceNumber);
        }
        return $selection;
    }
}
