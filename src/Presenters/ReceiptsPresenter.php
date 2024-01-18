<?php

namespace Crm\InvoicesModule\Presenters;

use Contributte\PdfResponse\PdfResponse;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\InvoicesModule\Models\Generator\ReceiptGenerator;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\DI\Attributes\Inject;

class ReceiptsPresenter extends FrontendPresenter
{
    #[Inject]
    public ReceiptGenerator $receiptGenerator;

    #[Inject]
    public PaymentsRepository $paymentsRepository;

    public function actionDownloadReceipt($paymentId)
    {
        $payment = $this->paymentsRepository->find($paymentId);

        if ($this->getUser()->getId() !== $payment->user_id) {
            throw new ForbiddenRequestException();
        }

        $pdf = $this->receiptGenerator->generate($payment);
        $pdf->setSaveMode(PdfResponse::INLINE);
        if (!$pdf) {
            throw new BadRequestException();
        }

        $this->sendResponse($pdf);
    }
}
