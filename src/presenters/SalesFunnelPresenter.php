<?php

namespace Crm\InvoicesModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\InvoicesModule\Events\ProformaInvoiceCreatedEvent;
use Crm\InvoicesModule\Forms\UserInvoiceFormFactory;
use Crm\PaymentsModule\Repository\PaymentLogsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;

class SalesFunnelPresenter extends FrontendPresenter
{
    /** @var PaymentsRepository @inject */
    public $paymentsRepository;

    /** @var PaymentLogsRepository  @inject */
    public $paymentLogsRepository;

    /** @var UserInvoiceFormFactory @inject */
    public $userInvoiceFormFactory;

    /** @persistent */
    public $VS;

    public function renderReturnPaymentProformaInvoice()
    {
        $payment = $this->getPayment();
        $this->template->payment = $payment;
        $this->template->note = 'VS' . $payment->variable_symbol;
    }

    public function createComponentProformaInvoiceForm()
    {
        $payment = $this->getPayment();
        if ($payment->status != PaymentsRepository::STATUS_FORM) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Payment is not in FORM state when finishing proforma invoice payment - '{$this->VS}'",
                $this->request->getUrl(),
                $payment->id
            );
            $this->redirect(':SalesFunnel:SalesFunnel:Error');
        }

        $form = $this->userInvoiceFormFactory->create($payment);

        $form['done']->setValue(0);

        $presenter = $this;
        $this->userInvoiceFormFactory->onSave = function ($form, $user) use ($presenter, $payment) {
            $form['done']->setValue(1);
            $presenter->redrawControl('invoiceFormSnippet');
            $this->emitter->emit(new ProformaInvoiceCreatedEvent($payment));
        };
        return $form;
    }

    public function getPayment()
    {
        if (isset($this->VS)) {
            $payment = $this->paymentsRepository->findByVs($this->VS);
            if ($payment) {
                return $payment;
            }
        }

        $this->paymentLogsRepository->add(
            'ERROR',
            "Cannot load payment with VS '{$this->VS}'",
            $this->request->getUrl()
        );
        $this->redirect(':SalesFunnel:SalesFunnel:Error');
    }
}
