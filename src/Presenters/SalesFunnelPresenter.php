<?php

namespace Crm\InvoicesModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\InvoicesModule\Events\ProformaInvoiceCreatedEvent;
use Crm\InvoicesModule\Forms\UserInvoiceFormFactory;
use Crm\PaymentsModule\Repositories\PaymentLogsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;
use Nette\DI\Attributes\Inject;

class SalesFunnelPresenter extends FrontendPresenter
{
    #[Inject]
    public PaymentsRepository $paymentsRepository;

    #[Inject]
    public PaymentLogsRepository $paymentLogsRepository;

    #[Inject]
    public UserInvoiceFormFactory $userInvoiceFormFactory;

    #[Persistent]
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
        $this->userInvoiceFormFactory->onSave = function (Form $form, $user) use ($presenter, $payment) {
            $form['done']->setValue(1);
            $presenter->redrawControl('invoiceFormSnippet');
            $this->emitter->emit(new ProformaInvoiceCreatedEvent($payment));
        };

        $form->onError[] = function (Form $form) use ($presenter) {
            $presenter->redrawControl('invoiceFormSnippet');
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
