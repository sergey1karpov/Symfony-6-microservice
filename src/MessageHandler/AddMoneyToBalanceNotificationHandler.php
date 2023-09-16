<?php

namespace App\MessageHandler;

use App\Message\AddMoneyToBalanceNotification;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class AddMoneyToBalanceNotificationHandler
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    public function __invoke(AddMoneyToBalanceNotification $message): void
    {
        $this->logger->info($message->getContent());
    }
}