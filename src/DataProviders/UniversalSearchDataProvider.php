<?php
declare(strict_types=1);

namespace Crm\InvoicesModule\DataProviders;

use Contributte\Translation\Translator;
use Crm\AdminModule\Models\UniversalSearchDataProviderInterface;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Nette\Application\LinkGenerator;

class UniversalSearchDataProvider implements UniversalSearchDataProviderInterface
{
    public function __construct(
        private InvoicesRepository $invoicesRepository,
        private LinkGenerator $linkGenerator,
        private Translator $translator,
    ) {
    }

    public function provide(array $params): array
    {
        $result = [];
        $term = $params['term'];

        if (strlen($term) >= 3) {
            $invoice = $this->invoicesRepository->getTable()
                ->where('invoice_number.number', $term)
                ->fetch();
            if ($invoice) {
                $payment = $invoice->related('payments')->fetch();
                $result[$this->translator->translate('invoices.data_provider.universal_search.invoice_group')][] = [
                    'id' => 'invoice_id_' . $invoice->id,
                    'text' => $invoice->invoice_number->number,
                    'url' => $this->linkGenerator->link('Invoices:InvoicesAdmin:edit', ['id' => $invoice->id]),
                ];
                $result[$this->translator->translate('invoices.data_provider.universal_search.payment_group')][] = [
                    'id' => 'payment_id_' . $payment->id,
                    'text' => $payment->variable_symbol,
                    'url' => $this->linkGenerator->link('Payments:PaymentsAdmin:show', ['id' => $payment->id]),
                ];
                $result[$this->translator->translate('invoices.data_provider.universal_search.user_group')][] = [
                    'id' => 'user_id' . $payment->user->id,
                    'text' => $payment->user->email,
                    'url' => $this->linkGenerator->link('Users:UsersAdmin:show', ['id' => $payment->user->id]),
                ];
            }
        }

        return $result;
    }
}
