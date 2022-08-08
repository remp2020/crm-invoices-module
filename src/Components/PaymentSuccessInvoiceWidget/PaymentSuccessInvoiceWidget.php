<?php

namespace Crm\InvoicesModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\InvoicesModule\Forms\UserInvoiceFormFactory;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\PaymentsModule\Gateways\BankTransfer;
use Crm\PaymentsModule\PaymentAwareInterface;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;

/**
 * PaymentSuccessInvoiceWidget is directly targeted to be used in \Crm\SalesFunnelModule\Presenters\SalesFunnelPresenter
 * and extends the success page with invoice form.
 * Any other usage ends up with Exception.
 *
 * @package Crm\InvoicesModule\Components
 */
class PaymentSuccessInvoiceWidget extends BaseWidget
{
    protected $templatePath = __DIR__ . DIRECTORY_SEPARATOR . 'payment_success_invoice_widget.latte';

    private $invoicesRepository;

    public function __construct(
        WidgetManager $widgetManager,
        InvoicesRepository $invoicesRepository
    ) {
        parent::__construct($widgetManager);
        $this->invoicesRepository = $invoicesRepository;
    }

    public function identifier()
    {
        return 'paymentsuccessinvoicewidget';
    }

    public function render()
    {
        $payment = $this->getPayment();

        if ($payment->status !== PaymentsRepository::STATUS_PAID && $payment->payment_gateway->code !== BankTransfer::GATEWAY_CODE) {
            return;
        }
        if (!$this->invoicesRepository->isPaymentInvoiceable($payment, true) && $payment->payment_gateway->code !== BankTransfer::GATEWAY_CODE) {
            return;
        }

        $this->template->payment = $payment;
        $this->template->setFile($this->templatePath);
        $this->template->render();
    }

    public function createComponentUserInvoiceForm(UserInvoiceFormFactory $factory)
    {
        $form = $factory->create($this->getPayment());
        $factory->onSave = function ($form, $user) {
            $form['done']->setValue(1);
            $this->redrawControl('invoiceFormSnippet');
        };
        $form->onError[] = function (Form $form) {
            $this->redrawControl('invoiceFormSnippet');
        };

        return $form;
    }

    public function getPayment(): ActiveRow
    {
        $presenter = $this->getPresenter();
        if ($presenter instanceof PaymentAwareInterface) {
            return $presenter->getPayment();
        }

        throw new \Exception('PaymentSuccessInvoiceWidget used within not allowed presenter: ' . get_class($presenter));
    }
}
