<?php

namespace Crm\InvoicesModule\Presenters;

use Contributte\PdfResponse\PdfResponse;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\InvoicesModule\Models\Generator\InvoiceGenerator;
use Crm\InvoicesModule\Models\Invoice\PaymentInvoiceStatusResolver;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Application\BadRequestException;

class InvoicesPresenter extends FrontendPresenter
{
    public function __construct(
        private readonly InvoiceGenerator $invoiceGenerator,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly PaymentInvoiceStatusResolver $paymentInvoiceStatusResolver,
    ) {
        parent::__construct();
    }

    public function actionDownloadInvoice($id)
    {
        list($user, $payment) = $this->getUserPayment($id);

        $status = $this->paymentInvoiceStatusResolver->resolve($payment);
        if ($status->isExternal()) {
            $this->sendResponse($status->billingProvider->generate($payment));
        }

        $pdf = null;
        if ($status->invoiceExists) {
            $pdf = $this->invoiceGenerator->renderInvoicePDF($user, $payment);
            $pdf->setSaveMode(PdfResponse::INLINE);
        } elseif ($status->isGeneratable()) {
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
