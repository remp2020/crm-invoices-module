<?php

namespace Crm\InvoicesModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;

class InvoiceLabel extends BaseWidget
{
    private $templateName = 'invoice_label.latte';

    public function identifier()
    {
        return 'invoicelabel';
    }

    public function render($user)
    {
        if (!$user->invoice) {
            return;
        }

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
