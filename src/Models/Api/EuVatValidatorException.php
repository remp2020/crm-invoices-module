<?php

namespace Crm\InvoicesModule\Models\Api;

class EuVatValidatorException extends \Exception
{
    public const BAD_REQUEST = 400;
    public const SERVICE_UNAVAILABLE = 503;
}
