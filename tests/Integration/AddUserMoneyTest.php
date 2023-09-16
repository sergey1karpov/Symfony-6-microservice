<?php

namespace App\Tests\Integration;

use App\Entity\UserBalance;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AddUserMoneyTest extends WebTestCase
{
    private KernelBrowser $client;

    private ?object $em;

    private $userRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $container = $this->client->getContainer();
        $this->em = $container->get('doctrine.orm.default_entity_manager');

        $this->userRepository = $this->em->getRepository(UserBalance::class);
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
            Response::HTTP_BAD_REQUEST,
            'There is not enough money in your account. Top up your account.'
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
}
