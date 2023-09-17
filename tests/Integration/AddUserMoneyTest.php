<?php

namespace App\Tests\Integration;

use App\Entity\OrderService;
use App\Entity\UserBalance;
use App\Services\UserOrderService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AddUserMoneyTest extends WebTestCase
{
    private KernelBrowser $client;

    private ?object $em;

    private $userRepository;

    private $orderRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $container = $this->client->getContainer();
        $this->em = $container->get('doctrine.orm.default_entity_manager');

        $this->userRepository = $this->em->getRepository(UserBalance::class);

        $this->orderRepository = $this->em->getRepository(OrderService::class);
    }

    public function testAddMoneyToUserBalance(): void
    {
        $data = ['user_id' => 1, 'money' => 100, 'hold' => 0];

        $this->client->request('POST', '/api/v1/add-money', $data);

        $user = $this->userRepository->findOneBy(['user_id' => 1]);

        $this->assertEquals(100, $user->getBalance());
    }

    public function testUpdateMoneyToUserBalance(): void
    {
        $data = [
            'user_id' => 1,
            'money' => 100,
            'hold' => 0,
        ];

        $this->client->request('POST', '/api/v1/add-money', $data);

        $data2 = [
            'user_id' => 1,
            'money' => 50,
            'hold' => 0,
        ];

        $this->client->request('POST', '/api/v1/add-money', $data2);

        $user = $this->userRepository->findOneBy(['user_id' => 1]);

        $this->assertEquals(150, $user->getBalance());
    }

    public function testTransferMoneyToUserFromUser(): void
    {
        $userBalance1 = new UserBalance();
        $userBalance1->setUserId(1);
        $userBalance1->setBalance(100);
        $this->em->persist($userBalance1);
        $this->em->flush();

        $userBalance2 = new UserBalance();
        $userBalance2->setUserId(2);
        $userBalance2->setBalance(100);
        $this->em->persist($userBalance2);
        $this->em->flush();

        $this->client->request('POST', '/api/v1/transfer-money', [
            'sender_id' => $userBalance1->getUserId(),
            'recipient_id' => $userBalance2->getUserId(),
            'money' => 50
        ]);

        $this->assertEquals(50, $userBalance1->getBalance());
        $this->assertEquals(150, $userBalance2->getBalance());
    }

    public function testNotEnoughMoneyToUserBalanceToTransfer(): void
    {
        $userBalance1 = new UserBalance();
        $userBalance1->setUserId(1);
        $userBalance1->setBalance(100);
        $this->em->persist($userBalance1);
        $this->em->flush();

        $userBalance2 = new UserBalance();
        $userBalance2->setUserId(2);
        $userBalance2->setBalance(100);
        $this->em->persist($userBalance2);
        $this->em->flush();

        $this->client->request('POST', '/api/v1/transfer-money', [
            'sender_id' => $userBalance1->getUserId(),
            'recipient_id' => $userBalance2->getUserId(),
            'money' => 500
        ]);

        $this->assertResponseStatusCodeSame(
            Response::HTTP_BAD_REQUEST
        );
    }

    public function testGetUserBalance(): void
    {
        $userBalance = new UserBalance();
        $userBalance->setUserId(666);
        $userBalance->setBalance(1000);
        $this->em->persist($userBalance);
        $this->em->flush();

        $this->client->request('POST', '/api/v1/get-balance', ['user_id' => $userBalance->getUserId()]);

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $balance = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals(1000, $balance);
    }

    public function testGetUserNotFoundBalance(): void
    {
        $this->client->request('POST', '/api/v1/get-balance', ['user_id' => 999]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'User balance not found');
    }

    public function testCreateNewOrder(): void
    {
        $userBalance = new UserBalance();
        $userBalance->setUserId(1);
        $userBalance->setBalance(1000);
        $this->em->persist($userBalance);
        $this->em->flush();

        $orderData = [
            'user_id' => $userBalance->getUserId(),
            'service_id' => 1,
            'money' => 100
        ];

        $this->client->request('POST', '/api/v1/create-order', $orderData);

        $userWallet = $this->userRepository->findOneBy(['user_id' => $userBalance->getUserId()]);
        $order = $this->orderRepository->findOneBy(['user_id' => $userBalance->getUserId()]);

        $this->assertEquals(900, $userWallet->getBalance());
        $this->assertEquals(100, $userWallet->getHold());
        $this->assertEquals(1, $order->getUserId());
        $this->assertEquals(1, $order->getServiceId());
        $this->assertEquals(100, $order->getMoney());
        $this->assertEquals(100, $order->getMoney());
    }

    public function testCreateNewOrderIfNotMoney(): void
    {
        $userBalance = new UserBalance();
        $userBalance->setUserId(1);
        $userBalance->setBalance(1000);
        $this->em->persist($userBalance);
        $this->em->flush();

        $orderData = [
            'user_id' => $userBalance->getUserId(),
            'service_id' => 1,
            'money' => 1000000
        ];

        $this->client->request('POST', '/api/v1/create-order', $orderData);

        $this->assertResponseStatusCodeSame(
            Response::HTTP_BAD_REQUEST,
            'There is not enough money in your account. Top up your account.'
        );
    }

    public function testConfirmedOrder(): void
    {
        $userBalance = new UserBalance();
        $userBalance->setUserId(1);
        $userBalance->setBalance(1000);
        $this->em->persist($userBalance);
        $this->em->flush();

        $orderData = [
            'user_id' => $userBalance->getUserId(),
            'service_id' => 1,
            'money' => 100
        ];

        $this->client->request('POST', '/api/v1/create-order', $orderData);

        $order = $this->orderRepository->findOneBy(['user_id' => $userBalance->getUserId()]);

        $orderData = [
            'order_uuid' => $order->getOrderUuid(),
            'decision' => true
        ];

        $this->client->request('POST', '/api/v1/confirmed-order', $orderData);

        $confirmedOrder = $this->orderRepository->findOneBy(['user_id' => $userBalance->getUserId()]);

        $this->assertEquals(900, $userBalance->getBalance());
        $this->assertEquals(100, $userBalance->getHold());
        $this->assertEquals(UserOrderService::CONFIRMED, $confirmedOrder->getStatus());

        $this->assertResponseStatusCodeSame(
            Response::HTTP_OK,
            'Your order # ' . $confirmedOrder->getOrderUuid() . ' was confirmed!'
        );
    }

    public function testDoesntConfirmedOrder(): void
    {
        $userBalance = new UserBalance();
        $userBalance->setUserId(1);
        $userBalance->setBalance(1000);
        $this->em->persist($userBalance);
        $this->em->flush();

        $orderData = [
            'user_id' => $userBalance->getUserId(),
            'service_id' => 1,
            'money' => 100
        ];

        $this->client->request('POST', '/api/v1/create-order', $orderData);

        $order = $this->orderRepository->findOneBy(['user_id' => $userBalance->getUserId()]);

        $orderData = [
            'order_uuid' => $order->getOrderUuid(),
            'decision' => false
        ];

        $this->client->request('POST', '/api/v1/confirmed-order', $orderData);

        $confirmedOrder = $this->orderRepository->findOneBy(['user_id' => $userBalance->getUserId()]);

        $this->assertEquals(900, $userBalance->getBalance());
        $this->assertEquals(100, $userBalance->getHold());
        $this->assertEquals(UserOrderService::REJECTED, $confirmedOrder->getStatus());

        $this->assertResponseStatusCodeSame(
            Response::HTTP_OK,
            'Your order # ' . $confirmedOrder->getOrderUuid() . ' was rejected by the administrator'
        );
    }
}
