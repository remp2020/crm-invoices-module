<?php
declare(strict_types=1);

namespace Crm\InvoicesModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\AuditLogHistoryDataProviderItem;
use Crm\ApplicationModule\Models\DataProvider\AuditLogHistoryItemChangeIndicatorEnum;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\PaymentsModule\DataProviders\PaymentAuditLogHistoryDataProviderInterface;
use Nette\Application\LinkGenerator;
use Nette\Database\Table\ActiveRow;

class PaymentAuditLogHistoryDataProvider implements PaymentAuditLogHistoryDataProviderInterface
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
        private readonly LinkGenerator $linkGenerator,
    ) {
    }

    /**
     * @param ActiveRow $payment
     * @return array
     */

    public function provide(ActiveRow $payment): array
    {
        if ($payment->invoice_id === null) {
            return [];
        }

        $invoiceHistory = $this->auditLogRepository->getByTableAndSignature('invoices', strval($payment->invoice_id))
            ->order('created_at DESC, id DESC')
            ->fetchAll();

        $results = [];
        foreach ($invoiceHistory as $item) {
            if ($item->operation === AuditLogRepository::OPERATION_CREATE) {
                $auditLogHistoryDataProviderItem = new AuditLogHistoryDataProviderItem(
                    $item->created_at,
                    $item->operation,
                    $item->user,
                    AuditLogHistoryItemChangeIndicatorEnum::Success,
                );
            
                $auditLogHistoryDataProviderItem->addMessage(
                    'invoices.data_provider.payment_audit_log_history.invoice_created',
                    [
                        'invoiceLink' => $this->linkGenerator->link(
                            'Invoices:InvoicesAdmin:downloadInvoice',
                            ['id' => $payment->id],
                        ),
                        'variableSymbol' => $payment->variable_symbol,
                    ],
                );
                
                $results[] = $auditLogHistoryDataProviderItem;
            } elseif ($item->operation === AuditLogRepository::OPERATION_UPDATE) {
                $auditLogHistoryDataProviderItem = new AuditLogHistoryDataProviderItem(
                    $item->created_at,
                    $item->operation,
                    $item->user,
                    AuditLogHistoryItemChangeIndicatorEnum::Info,
                );

                $auditLogHistoryDataProviderItem->addMessage(
                    'invoices.data_provider.payment_audit_log_history.invoice_updated',
                    [
                        'invoiceLink' => $this->linkGenerator->link(
                            'Invoices:InvoicesAdmin:downloadInvoice',
                            ['id' => $payment->id],
                        ),
                        'variableSymbol' => $payment->variable_symbol,
                    ],
                );

                $results[] = $auditLogHistoryDataProviderItem;
            }
        }
        
        return $results;
    }
}
