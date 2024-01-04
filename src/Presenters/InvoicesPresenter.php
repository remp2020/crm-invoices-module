<?php

namespace Crm\InvoicesModule\Presenters;

use Contributte\PdfResponse\PdfResponse;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\InvoicesModule\Forms\ChangeInvoiceDetailsFormFactory;
use Crm\InvoicesModule\InvoiceGenerator;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\DI\Attributes\Inject;

class InvoicesPresenter extends FrontendPresenter
{
    #[Inject]
    public InvoiceGenerator $invoiceGenerator;

    #[Inject]
    public InvoicesRepository $invoicesRepository;

    #[Inject]
    public PaymentsRepository $paymentsRepository;

    #[Inject]
    public ChangeInvoiceDetailsFormFactory $changeInvoiceDetailsFormFactory;

    public function actionDownloadInvoice($id)
    {
        list($user, $payment) = $this->getUserPayment($id);

        $pdf = null;
        if ($payment->invoice) {
            $pdf = $this->invoiceGenerator->renderInvoicePDF($user, $payment);
            $pdf->setSaveMode(PdfResponse::INLINE);
        } elseif ($this->invoicesRepository->isPaymentInvoiceable($payment)) {
            $pdf = $this->invoiceGenerator->generate($user, $payment);
        }

        if (!$pdf) {
            throw new BadRequestException();
        }

        $this->sendResponse($pdf);
    }

    private function getUserPayment($id)
    {
        $this->onlyLoggedIn();

        $user = $this->getUser();
        $payment = $this->paymentsRepository->find($id);

        if (!$payment) {
            throw new BadRequestException();
        }

        if ($user->id != $payment->user->id) {
            throw new BadRequestException();
        }

        $user = $this->usersRepository->find($user->id);

        if (!$user) {
            throw new BadRequestException();
        }

        return [$user, $payment];
    }

    public function renderInvoiceDetails()
    {
        $this->onlyLoggedIn();
    }
}
