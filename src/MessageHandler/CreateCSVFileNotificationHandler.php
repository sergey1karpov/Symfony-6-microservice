<?php

namespace App\MessageHandler;

use App\Message\CreateCSVFileNotification;
use League\Csv\Writer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class CreateCSVFileNotificationHandler
{
    public function __construct(private MailerInterface $mailer) {}

    public function __invoke(CreateCSVFileNotification $message): void
    {
        $csvWriter = Writer::createFromString('', 'w+');

        $csvWriter->insertOne(['Month/Year', 'Service ID', 'Total sum']);

        $csvWriter->insertOne([
            $message->getMonth() . '-' . $message->getYear(),
            $message->getServiceId(),
            $message->getSum()
        ]);

        $csvContent = $csvWriter->getContent();

        $email = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('CSV File')
            ->text('Attached is the CSV file.');

        $email->attach($csvContent, 'data.csv', 'text/csv');

        $this->mailer->send($email);
    }
}