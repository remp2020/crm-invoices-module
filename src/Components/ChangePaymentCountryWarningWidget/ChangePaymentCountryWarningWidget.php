<?php

namespace Crm\InvoicesModule\Components\ChangePaymentCountryWarningWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Exception;
use Nette\Database\Table\ActiveRow;

class ChangePaymentCountryWarningWidget extends BaseLazyWidget
{
    public function render(array $params): void
    {
        if (!isset($params['payment'])) {
            throw new Exception("Missing required param 'payment'.");
        }

        $payment = $params['payment'];
        if (!is_a($payment, ActiveRow::class)) {
            throw new Exception(sprintf(
                "Param 'payment' must be type of '%s'.",
                ActiveRow::class,
            ));
        }

        if ($payment->invoice === null) {
            return;
        }

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . 'change_payment_country_warning_widget.latte');
        $this->template->render();
    }
}
