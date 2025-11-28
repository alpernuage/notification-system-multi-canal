<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Notification;
use App\Message\SendNotificationMessage;
use App\Repository\NotificationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsCommand(
    name: 'notification:send',
    description: 'Create and send a notification',
)]
class NotificationSendCommand extends Command
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly WorkflowInterface $notificationStateMachine,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('channel', 'c', InputOption::VALUE_REQUIRED, 'Channel (email, slack, sms, webhook)', 'email')
            ->addOption('recipient', 'r', InputOption::VALUE_REQUIRED, 'Recipient address')
            ->addOption('subject', 's', InputOption::VALUE_OPTIONAL, 'Subject (for email)')
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Message content')
            ->addOption('draft', 'd', InputOption::VALUE_NONE, 'Create as draft (do not auto-approve)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $channel = $input->getOption('channel');
        $recipient = $input->getOption('recipient');
        $subject = $input->getOption('subject');
        $message = $input->getOption('message');
        $draft = $input->getOption('draft');

        if (!$recipient || !$message) {
            $io->error('Recipient and message are required!');

            return Command::FAILURE;
        }

        // Create notification
        $notification = new Notification();
        $notification
            ->setChannel($channel)
            ->setRecipient($recipient)
            ->setSubject($subject)
            ->setMessage($message);

        $this->notificationRepository->save($notification, true);

        $io->success(sprintf('Notification #%d created in state: %s', $notification->getId(), $notification->getState()));

        // Auto-approve if not draft
        if (!$draft) {
            if ($this->notificationStateMachine->can($notification, 'approve')) {
                $this->notificationStateMachine->apply($notification, 'approve');
                $this->notificationRepository->save($notification, true);

                $io->success(sprintf('Notification #%d approved! State: %s', $notification->getId(), $notification->getState()));

                // Dispatch async message
                $this->messageBus->dispatch(new SendNotificationMessage($notification->getId()));
                $io->info('Notification dispatched to async queue');
            } else {
                $io->warning('Cannot approve notification (guard failed)');
            }
        }

        return Command::SUCCESS;
    }
}

