<?php

namespace App\Tests\Integration;

use App\Entity\UserBalance;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AddUserMoneyTest extends WebTestCase
{
    /**
     * Зачисление средств на баланс
     * @return void
     */
    public function testAddMoneyToUserBalance(): void
    {
        $client = static::createClient();

        $data = [
            'user_id' => 1,
            'money' => 100,
            'hold' => 0,
        ];

        $client->request('POST', '/api/v1/add-money', $data);

        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Зачисление средств на баланс и еще пополнение
     * @return void
     */
    public function testUpdateMoneyToUserBalance(): void
    {
        $client = static::createClient();

        $container = $client->getContainer();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');

        $repository = $entityManager->getRepository(UserBalance::class);

        $data = [
            'user_id' => 1,
            'money' => 100,
            'hold' => 0,
        ];

        $client->request('POST', '/api/v1/add-money', $data);

        $data2 = [
            'user_id' => 1,
            'money' => 50,
            'hold' => 0,
        ];

        $client->request('POST', '/api/v1/add-money', $data2);

        $user = $repository->findOneBy(['user_id' => 1]);

        $this->assertEquals(150, $user->getBalance());
    }

    public function testGetUserBalance(): void
    {
        $client = static::createClient();

        $container = $client->getContainer();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');

        $userBalance = new UserBalance();
        $userBalance->setUserId(666);
        $userBalance->setBalance(1000);
        $entityManager->persist($userBalance);
        $entityManager->flush();

        $client->request('POST', '/api/v1/get-balance', ['user_id' => $userBalance->getUserId()]);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $balance = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals(1000, $balance);
    }

    public function testGetUserNotFoundBalance(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/get-balance', ['user_id' => 999]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'User balance not found');
    }
}
