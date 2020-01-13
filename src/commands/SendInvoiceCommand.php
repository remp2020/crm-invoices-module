<?php

namespace Crm\InvoicesModule\Commands;

use Crm\InvoicesModule\InvoiceGenerator;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\UsersModule\Events\NotificationEvent;
use League\Event\Emitter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SendInvoiceCommand extends Command
{
    private $paymentsRepository;

    private $invoiceGenerator;

    private $emitter;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        InvoiceGenerator $invoiceGenerator,
        Emitter $emitter
    ) {
        parent::__construct();
        $this->paymentsRepository = $paymentsRepository;
        $this->invoiceGenerator = $invoiceGenerator;
        $this->emitter = $emitter;
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('invoice:send')
            ->setDescription('Sends provided email with invoice attached')
            ->addOption(
                'variable-symbol',
                null,
                InputOption::VALUE_REQUIRED,
                'Transaction identifier'
            )
            ->addOption(
                'template-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Code of an email to be sent'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('variable-symbol')) {
            $output->writeln("<error>Required argument variable-symbol is missing</error>");
            return;
        }
        if (!$input->getOption('template-code')) {
            $output->writeln("<error>Required argument mail-template-code is missing</error>");
            return;
        }

        $templateCode = $input->getOption('template-code');

        $payment = $this->paymentsRepository->findByVs($input->getOption('variable-symbol'));
        if (!$payment) {
            $output->writeln("<error>Payment with given variable symbol was not found: " . $input->getOption('variable-symbol') . "</error>");
            return;
        }
        if (!$payment->invoice) {
            $output->writeln("<error>Payment has no invoice generated</error>");
            return;
        }

        $attachment = $this->invoiceGenerator->renderInvoiceMailAttachment($payment);
        if (!$attachment) {
            $output->writeln('<error>Attachment with invoice was not generated properly for payment: {$payment->variable_symbol}</error>');
        }

        $this->emitter->emit(new NotificationEvent(
            $this->emitter,
            $payment->user,
            $templateCode,
            [],
            null,
            [$attachment]
        ));

        $output->writeln("Sent invoice <info>{$payment->invoice->invoice_number->number}</info> as an attachment of <info>{$templateCode}</info> to <info>{$payment->user->email}</info>.");
    }
}
