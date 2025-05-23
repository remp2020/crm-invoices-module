<?php

namespace Crm\InvoicesModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Nette\Database\Explorer;

class InvoiceNumbersRepository extends Repository
{
    protected $tableName = 'invoice_numbers';

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
    }
}
