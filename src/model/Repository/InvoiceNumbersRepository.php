<?php

namespace Crm\InvoicesModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Explorer;

class InvoiceNumbersRepository extends Repository
{
    protected $tableName = 'invoice_numbers';

    public function __construct(
        Explorer $database,
        Repository\AuditLogRepository $auditLogRepository
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
    }
}
