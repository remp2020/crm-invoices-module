<?php

namespace Crm\InvoicesModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\InvoicesModule\Events\ProformaInvoiceCreatedEvent;
use Crm\InvoicesModule\Forms\UserInvoiceFormFactory;
use Crm\PaymentsModule\Repositories\PaymentLogsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;

class SalesFunnelPresenter extends FrontendPresenter
{
    #[Persistent]
    public ?string $variableSymbol;

    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
        private readonly PaymentLogsRepository $paymentLogsRepository,
        private readonly UserInvoiceFormFactory $userInvoiceFormFactory,
    ) {
        parent::__construct();
    }

    public function renderProforma(): void
    {
        $this->getPayment($this->variableSymbol); // just check existence of payment
    }

    public function renderProformaSuccess(): void
    {
        $this->template->payment = $this->getPayment($this->variableSymbol);
    }

    public function createComponentProformaInvoiceAddressForm(): Form
    {
        $payment = $this->getPayment($this->variableSymbol);
        if ($payment->status != PaymentsRepository::STATUS_FORM) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Payment is not in FORM state when finishing proforma invoice payment - '{$this->variableSymbol}'",
                $this->request->getUrl(),
                $payment->id
            );
            $this->redirect(':SalesFunnel:SalesFunnel:Error');
        }

        $form = $this->userInvoiceFormFactory->create($payment);

        $this->userInvoiceFormFactory->onSave = function (Form $form, ActiveRow $user) use ($payment) {
            $this->emitter->emit(new ProformaInvoiceCreatedEvent($payment));
            $this->redirect('proformaSuccess');
        };

        $form->onError[] = function (Form $form) {
            $this->redrawControl('invoiceFormSnippet');
        };

        return $form;
    }

    private function getPayment(?string $variableSymbol): ActiveRow
    {
        if ($variableSymbol !== null) {
            $payment = $this->paymentsRepository->findByVs($variableSymbol);

            if ($payment) {
                return $payment;
            }
        }

        $this->paymentLogsRepository->add(
            'ERROR',
            "Cannot load payment with VS '{$variableSymbol}'",
            $this->request->getUrl()
        );
        $this->redirect(':SalesFunnel:SalesFunnel:Error');
    }
}
