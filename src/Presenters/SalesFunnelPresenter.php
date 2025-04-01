<?php

namespace Crm\InvoicesModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\ApplicationModule\UI\Form;
use Crm\InvoicesModule\Events\ProformaInvoiceCreatedEvent;
use Crm\InvoicesModule\Forms\UserInvoiceFormFactory;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Repositories\PaymentLogsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Application\Attributes\Persistent;
use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;
use Nette\Http\IResponse;

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
        $payment = $this->getPayment($this->variableSymbol);

        $this->template->payment = $payment;
        $this->template->bankNumber = $this->applicationConfig->get('supplier_bank_account_number');
        $this->template->bankIban = $this->applicationConfig->get('supplier_iban');
        $this->template->bankSwift = $this->applicationConfig->get('supplier_swift');
        $this->template->note = sprintf('VS%s', $payment->variable_symbol);
    }

    public function createComponentProformaInvoiceAddressForm(): Form
    {
        $payment = $this->getPayment($this->variableSymbol);
        if ($payment->status != PaymentStatusEnum::Form->value) {
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
        $user = $this->getUser();

        if (!$user->isLoggedIn()) {
            throw new BadRequestException('User is not logged in.', httpCode: IResponse::S403_Forbidden);
        }

        if ($variableSymbol !== null) {
            $payment = $this->paymentsRepository->findByVs($variableSymbol);

            if ($payment->user_id !== $user->getId()) {
                throw new BadRequestException("User hasn't access to the payment.", httpCode: IResponse::S403_Forbidden);
            }

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
