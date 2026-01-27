<?php

namespace Crm\InvoicesModule\Models;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Nette\Application\Response;
use Nette\Database\Table\ActiveRow;

interface BillingDataProviderInterface extends DataProviderInterface
{
    public function isAvailable(ActiveRow $payment): bool;

    public function isInvoiceable(ActiveRow $payment): bool;

    public function generate(ActiveRow $payment): Response;
}
