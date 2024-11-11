<?php

namespace Crm\InvoicesModule\Models\Api;

use DragonBe\Vies\Vies;

interface EuVatValidatorFactoryInterface
{
    public function create(): Vies;
}
