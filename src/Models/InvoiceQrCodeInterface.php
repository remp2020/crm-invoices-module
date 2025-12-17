<?php

namespace Crm\InvoicesModule\Models;

use Nette\Database\Table\ActiveRow;

interface InvoiceQrCodeInterface
{
    /**
     * Generate QR code HTML tag for invoice payment
     *
     * @param ActiveRow $payment Payment row with amount, variable_symbol, etc.
     * @return string|null HTML img tag with QR code or null if not applicable
     */
    public function generate(ActiveRow $payment): ?string;
}
