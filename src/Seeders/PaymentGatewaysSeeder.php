<?php

namespace Crm\InvoicesModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\InvoicesModule\Gateways\ProformaInvoice;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Symfony\Component\Console\Output\OutputInterface;

class PaymentGatewaysSeeder implements ISeeder
{
    private $paymentGatewaysRepository;
    
    public function __construct(PaymentGatewaysRepository $paymentGatewaysRepository)
    {
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
    }

    public function seed(OutputInterface $output)
    {
        if (!$this->paymentGatewaysRepository->exists(ProformaInvoice::GATEWAY_CODE)) {
            $this->paymentGatewaysRepository->add(
                'Proforma invoice',
                ProformaInvoice::GATEWAY_CODE,
                200,
                true,
            );
            $output->writeln('  <comment>* payment type <info>proforma invoice</info> created</comment>');
        } else {
            $output->writeln('  * payment type <info>proforma invoice</info> exists');
        }
    }
}
