<?php

namespace Crm\InvoicesModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;

class InvoiceNumbersRepository extends Repository
{
    protected $tableName = 'invoice_numbers';

    public function __construct(
        Context $database,
        Repository\AuditLogRepository $auditLogRepository
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function getUniqueInvoiceNumber(\DateTime $deliveredAt)
    {
        /** @var ActiveRow $number */
        $number = $this->insert(['delivered_at' => $deliveredAt]);

        $count = $this->getTable()
            ->where('MONTH(delivered_at) = ?', $deliveredAt->format('m'))
            ->where('YEAR(delivered_at) = ?', $deliveredAt->format('Y'))
            ->where('id < ?', $number->id)
            ->count('*');

        $invoiceNumber = $deliveredAt->format('y\mm') . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        parent::update($number, ['number' => $invoiceNumber]);
        return $number;
    }
}
