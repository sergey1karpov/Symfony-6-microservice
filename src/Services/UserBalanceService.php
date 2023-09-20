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

    /**
     * @param UserBalance $balance
     * @param int $money
     * @return void
     */
    public function updateMoney(UserBalance $balance, int $money): void
    {
        $currentBalance = $balance->getBalance();
        $newBalance = $balance->setBalance($money + $currentBalance);
        $this->entityManager->persist($newBalance);
        $this->entityManager->flush();
    }

    /**
     * @param UserBalance $senderWallet
     * @param UserBalance $recipientWallet
     * @param int $money
     * @return void
     * @throws \Exception
     */
    public function sendMoneyToUserFromUser(UserBalance $senderWallet, UserBalance $recipientWallet, int $money): void
    {
        $this->entityManager->beginTransaction();

        try {
            $senderWallet->setBalance($senderWallet->getBalance() - $money);
            $recipientWallet->setBalance($recipientWallet->getBalance() + $money);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}