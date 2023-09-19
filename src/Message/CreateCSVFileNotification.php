<?php

namespace App\Message;

class CreateCSVFileNotification
{
    public function __construct(private $sum, private $year, private $month, private $service_id){}

    public function getSum()
    {
        return $this->sum;
    }

    public function getYear()
    {
        return $this->year;
    }

    public function getMonth()
    {
        return $this->month;
    }

    public function getServiceId()
    {
        return $this->service_id;
    }
}