<?php

namespace Crm\InvoicesModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\InvoicesModule\ReceiptGenerator;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\DI\Attributes\Inject;

class ReceiptsAdminPresenter extends AdminPresenter
{
    #[Inject]
    public ReceiptGenerator $receiptGenerator;

    #[Inject]
    public PaymentsRepository $paymentsRepository;

    /**
     * @admin-access-level read
     */
    public function actionDownloadReceipt($paymentId)
    {
        $payment = $this->paymentsRepository->find($paymentId);

        $pdf = $this->receiptGenerator->generate($payment);
        if (!$pdf) {
            throw new BadRequestException();
        }

        $this->sendResponse($pdf);
        $this->terminate();
    }
}
