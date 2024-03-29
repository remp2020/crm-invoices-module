<?php

namespace Crm\InvoicesModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class HasInvoiceCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'has-invoice';

    private $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function params(): array
    {
        return [
            new BooleanParam(self::KEY, $this->label()),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];

        if ($values->selection) {
            $selection->where('payments.invoice_id IS NOT NULL');
        } else {
            $selection->where('payments.invoice_id IS NULL');
        }

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('invoices.admin.scenarios.has_invoice.label');
    }
}
