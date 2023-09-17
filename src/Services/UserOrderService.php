<?php

namespace App\Services;

use App\Entity\OrderService;
use App\Entity\UserBalance;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class UserOrderService
{
    public const CONFIRMED = 'CONFIRM';

    public const NOT_CONFIRMED = 'NOT CONFIRM';

    public const REJECTED = 'REJECTED';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    /**
     * @param UserBalance $user
     * @param int $service_id
     * @param int $money
     * @return void
     */
    public function createOrder(UserBalance $user, int $service_id, int $money): void
    {
        $this->entityManager->beginTransaction();

        try {
            $holdOrder = new OrderService();
            $holdOrder->setUserId($user->getUserId());
            $holdOrder->setServiceId($service_id);
            $holdOrder->setMoney($money);
            $holdOrder->setOrderUuid(Uuid::v4());
            $holdOrder->setStatus(self::NOT_CONFIRMED);
            $holdOrder->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($holdOrder);

            $user->setBalance($user->getBalance() - $money);
            $user->setHold($user->getHold() + $money);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @param UserBalance $userBalance
     * @param OrderService $order
     * @return void
     */
    public function confirmedOrder(UserBalance $userBalance, OrderService $order): void
    {
        $this->entityManager->beginTransaction();

        try {
            $userBalance->setHold($userBalance->getHold() - $order->getMoney());
            $order->setStatus(self::CONFIRMED);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @param UserBalance $userBalance
     * @param OrderService $order
     * @return void
     */
    public function rejectedOrder(UserBalance $userBalance, OrderService $order): void
    {
        $this->entityManager->beginTransaction();

        try {
            $userBalance->setHold($userBalance->getHold() - $order->getMoney());
            $userBalance->setBalance($userBalance->getBalance() + $order->getMoney());
            $order->setStatus(self::REJECTED);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error($e->getMessage());
        }
    }
}