<?php

namespace Crm\InvoicesModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Context;

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
}
