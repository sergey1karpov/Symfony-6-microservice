<?php

namespace App\Controller;

use App\Entity\UserBalance;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('/add-money', name: 'blog_list')]
    public function addBalance(Request $request, LoggerInterface $logger): Response
    {
        $user = $request->get('userId');

        $money = $request->get('money');

        $balance = $this->entityManager->getRepository(UserBalance::class)->findOneBy([
            'user_id' => $user
        ]);

        if (!$balance) {
            try {
                $userBalance = new UserBalance();
                $userBalance->setUserId($user);
                $userBalance->setBalance($money);
                $this->entityManager->persist($userBalance);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                $logger->error(
                    'User with id' . $user . ' couldn\'t top up my balance on ' . $money . ' because: ' . $e->getMessage()
                );
            }

            $logger->info('user with id' . $user . ' topped up balance with ' . $money);
        } else {
            $currentBalance = $balance->getBalance();
            $newBalance = $balance->setBalance($money + $currentBalance);

            $this->entityManager->persist($newBalance);
            $this->entityManager->flush();
        }

        return new Response('Balance replenished by ' . $money, Response::HTTP_OK);
    }
}