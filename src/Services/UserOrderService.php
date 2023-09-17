<?php

namespace App\Services;

use App\Entity\OrderService;
use App\Entity\UserBalance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class UserOrderService
{
    private const CONFIRMED = true;

    private const NOT_CONFIRMED = false;

    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @param UserBalance $user
     * @param int $service_id
     * @param int $money
     * @return void
     */
    public function createOrder(UserBalance $user, int $service_id, int $money): void
    {
        $holdOrder = new OrderService();
        $holdOrder->setUserId($user->getUserId());
        $holdOrder->setServiceId($service_id);
        $holdOrder->setMoney($money);
        $holdOrder->setOrderUuid(Uuid::v4());
        $holdOrder->setStatus(self::NOT_CONFIRMED);
        $holdOrder->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($holdOrder);
        $this->entityManager->flush();
    }
}