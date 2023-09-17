<?php

namespace App\Services;

use App\Entity\UserBalance;
use Doctrine\ORM\EntityManagerInterface;

class UserBalanceService
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @param int $user_id
     * @param int $money
     * @return void
     */
    public function addMoney(int $user_id, int $money): void
    {
        $userBalance = new UserBalance();
        $userBalance->setUserId($user_id);
        $userBalance->setBalance($money);
        $this->entityManager->persist($userBalance);
        $this->entityManager->flush();
    }
}