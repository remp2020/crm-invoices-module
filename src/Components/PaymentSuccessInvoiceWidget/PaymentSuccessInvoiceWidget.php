<?php

namespace Crm\InvoicesModule\Components\PaymentSuccessInvoiceWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\ApplicationModule\UI\Form;
use Crm\InvoicesModule\Forms\UserInvoiceFormFactory;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\PaymentsModule\Models\Gateways\BankTransfer;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentAwareInterface;
use Nette\Database\Table\ActiveRow;

/**
 * PaymentSuccessInvoiceWidget is directly targeted to be used in \Crm\SalesFunnelModule\Presenters\SalesFunnelPresenter
 * and extends the success page with invoice form.
 * Any other usage ends up with Exception.
 *
 * @package Crm\InvoicesModule\Components
 */
class PaymentSuccessInvoiceWidget extends BaseLazyWidget
{
    protected $templatePath = __DIR__ . DIRECTORY_SEPARATOR . 'payment_success_invoice_widget.latte';

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        readonly private InvoicesRepository $invoicesRepository,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function identifier()
    {
        return 'paymentsuccessinvoicewidget';
    }

    public function render()
    {
        $payment = $this->getPayment();

        if ($payment->status !== PaymentStatusEnum::Paid->value && $payment->payment_gateway->code !== BankTransfer::GATEWAY_CODE) {
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
