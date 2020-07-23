<?php

namespace Crm\InvoicesModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\InvoicesModule\Forms\UserInvoiceFormFactory;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SalesFunnelModule\Presenters\SalesFunnelPresenter;

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

    private $payment;

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
        $payment = $this->presenter()->getPayment();
        if ($payment->status != PaymentsRepository::STATUS_PAID) {
            return;
        }
        if (!$this->invoicesRepository->isPaymentInvoiceable($payment, true)) {
            return;
        }

        $this->template->payment = $payment;
        $this->template->setFile($this->templatePath);
        $this->template->render();
    }

    public function createComponentUserInvoiceForm(UserInvoiceFormFactory $factory)
    {
        $payment = $this->presenter()->getPayment();

        $form = $factory->create($payment);
        $factory->onSave = function ($form, $user) {
            $form['done']->setValue(1);
            $this->redrawControl('invoiceFormSnippet');
        };

        return $form;
    }

    public function presenter(): SalesFunnelPresenter
    {
        $presenter = $this->getPresenter();
        if (!$presenter instanceof SalesFunnelPresenter) {
            throw new \Exception('PaymentSuccessInvoiceWidget used within not allowed presenter: ' . get_class($presenter));
        }
        return $presenter;
    }
}
