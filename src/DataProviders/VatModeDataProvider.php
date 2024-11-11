<?php
declare(strict_types=1);

namespace Crm\InvoicesModule\DataProviders;

use Crm\InvoicesModule\Models\Vat\VatModeDetector;
use Crm\PaymentsModule\DataProviders\VatModeDataProviderInterface;
use Crm\PaymentsModule\Models\VatRate\VatMode;
use Nette\Database\Table\ActiveRow;

final class VatModeDataProvider implements VatModeDataProviderInterface
{
    public function __construct(
        private readonly VatModeDetector $vatModeDetector,
    ) {
    }

    public function getVatMode(ActiveRow $user): ?VatMode
    {
        return $this->vatModeDetector->userVatMode($user);
    }
}
