<?php

namespace App\Message;

class AddMoneyToBalanceNotification
{
    public function __construct(private string $message){}

    public function getContent(): string
    {
        return $this->message;
    }
}