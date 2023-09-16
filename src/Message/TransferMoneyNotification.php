<?php

namespace App\Message;

class TransferMoneyNotification
{
    public function __construct(private string $message){}

    public function getContent(): string
    {
        return $this->message;
    }
}