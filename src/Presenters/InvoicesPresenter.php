<?php

namespace Crm\InvoicesModule\Presenters;

use Contributte\PdfResponse\PdfResponse;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\InvoicesModule\Models\BillingDataProviderInterface;
use Crm\InvoicesModule\Models\Generator\InvoiceGenerator;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Application\BadRequestException;

class InvoicesPresenter extends FrontendPresenter
{
    public function __construct(
        private readonly InvoiceGenerator $invoiceGenerator,
        private readonly InvoicesRepository $invoicesRepository,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly DataProviderManager $dataProviderManager,
    ) {
        parent::__construct();
    }

    public function actionDownloadInvoice($id)
    {
        list($user, $payment) = $this->getUserPayment($id);

        /** @var BillingDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('invoices.billing_provider', BillingDataProviderInterface::class);
        foreach ($providers as $provider) {
            if ($provider->isAvailable($payment)) {
                $response = $provider->generate($payment);
                $this->sendResponse($response);
            }
        }

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
