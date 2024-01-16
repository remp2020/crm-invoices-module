<?php

namespace Crm\InvoicesModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Nette\Database\Explorer;

class InvoiceNumbersRepository extends Repository
{
    protected $tableName = 'invoice_numbers';

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
    }
}
