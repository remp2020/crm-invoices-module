<?php
declare(strict_types=1);

namespace Crm\InvoicesModule\DataProviders;

use Crm\ApplicationModule\UI\Form;
use Crm\InternalModule\DataProviders\InvoiceAddressValidatorsDataProvider;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;

class CompanyIdsValidatorDataProvider implements InvoiceFormDataProviderInterface
{
    public function __construct(
        private InvoiceAddressValidatorsDataProvider $invoiceAddressValidatorsDataProvider,
    ) {
    }

    public function provide(Form $form, ActiveRow $invoice): Form
    {
        return $this->invoiceAddressValidatorsDataProvider->provide([
            'form' => $form,
            'addressType' => 'invoice',
        ]);
    }

    public function formSucceeded(Form $form, ArrayHash $values): array
    {
        return [$form, $values];
    }
}
