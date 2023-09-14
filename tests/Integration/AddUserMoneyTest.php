<?php

namespace App\Tests\Integration;

use App\Entity\UserBalance;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
}
