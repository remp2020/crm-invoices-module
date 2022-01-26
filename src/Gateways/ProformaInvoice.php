<?php
namespace Crm\InvoicesModule\Gateways;

use Crm\PaymentsModule\Gateways\GatewayAbstract;

class ProformaInvoice extends GatewayAbstract
{
    public const GATEWAY_CODE = 'proforma_invoice';

    public function isSuccessful(): bool
    {
        return true;
    }

    public function process($allowRedirect = true)
    {
    }

    protected function initialize()
    {
    }

    public function begin($payment)
    {
        $url = $this->linkGenerator->link('Invoices:SalesFunnel:ReturnPaymentProformaInvoice', ['VS' => $payment->variable_symbol]);
        $this->httpResponse->redirect($url);
        exit();
    }

    public function complete($payment): ?bool
    {
        return true;
    }
}
