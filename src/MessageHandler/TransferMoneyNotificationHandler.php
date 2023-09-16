<?php

namespace App\MessageHandler;

use App\Message\TransferMoneyNotification;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TransferMoneyNotificationHandler
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    public function __invoke(TransferMoneyNotification $message): void
    {
        $this->logger->info($message->getContent());
    }
}