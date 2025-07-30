<?php
declare(strict_types=1);

namespace Crm\InvoicesModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;

interface InvoiceFormDataProviderInterface extends DataProviderInterface
{
    public function provide(Form $form, ActiveRow $invoice): Form;

    public function formSucceeded(Form $form, ArrayHash $values): array;
}
