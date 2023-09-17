<?php

namespace App\Controller;

use App\Entity\UserBalance;
use App\Message\AddMoneyToBalanceNotification;
use App\Message\TransferMoneyNotification;
use App\Services\UserBalanceService;
use App\Services\UserOrderService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\TransactionRequiredException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserOrderService       $userOrderService,
        private UserBalanceService     $userBalanceService,
    ) {}

    #[Route('/api/v1/add-money', name: 'add-money')]
    public function addBalance(Request $request, MessageBusInterface $bus): Response
    {
        $user  = $request->get('user_id');
        $money = $request->get('money');

        $balance = $this->entityManager->getRepository(UserBalance::class)
            ->findOneBy(['user_id' => $user]);

        if (!$balance) {
            try {
                $this->userBalanceService->addMoney($user, $money);
            } catch (\Exception $e) {
                $bus->dispatch(new AddMoneyToBalanceNotification(
                    'User with id' . $user . ' couldn\'t top up my balance on ' . $money . ' because: ' . $e->getMessage()
                ));
            }

            $bus->dispatch(new AddMoneyToBalanceNotification(
                'User with id' . $user . ' topped up balance with ' . $money
            ));
        } else {
            $currentBalance = $balance->getBalance();
            $newBalance = $balance->setBalance($money + $currentBalance);

            $this->entityManager->persist($newBalance);
            $this->entityManager->flush();

            $bus->dispatch(new AddMoneyToBalanceNotification(
                'User with id' . $user . ' topped up balance with ' . $money
            ));
        }

        return new Response('Balance replenished by ' . $money, Response::HTTP_OK);
    }

    #[Route('/api/v1/transfer-money', name: 'transfer-money')]
    public function sendMoneyFromUserToUser(Request $request, MessageBusInterface $bus): Response
    {
        $sender_id    = $request->get('sender_id');
        $recipient_id = $request->get('recipient_id');
        $money        = $request->get('money');

        $senderWallet = $this->entityManager->getRepository(UserBalance::class)
            ->findOneBy(['user_id' => $sender_id]);
        $recipientWallet = $this->entityManager->getRepository(UserBalance::class)
            ->findOneBy(['user_id' => $recipient_id]);

        if (!$senderWallet || !$recipientWallet) {
            throw new NotFoundHttpException('User not found');
        }

        if ($senderWallet->getBalance() == 0 || $senderWallet->getBalance() < $money) {
            $bus->dispatch(new TransferMoneyNotification(
                'FAIL!. Transfer of the amount ' . $money . ' from a user with id ' . $sender_id . ' => to a user with id ' . $recipient_id
            ));

            return new Response(
                'There is not enough money in your account. Top up your account.',
                Response::HTTP_BAD_REQUEST
            );
        }

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

        $bus->dispatch(new TransferMoneyNotification(
            'OK!. Transfer of the amount ' . $money . ' from a user with id ' . $sender_id . ' => to a user with id ' . $recipient_id
        ));

        return new Response(
            'User with id ' . $sender_id . ' transferred ' . $money . ' rubles to user with id ' . $recipient_id,
            Response::HTTP_OK
        );
    }

    #[Route('/api/v1/get-balance', name: 'get-balance')]
    public function getUserBalance(Request $request): JsonResponse
    {
        $user = $this->entityManager->getRepository(UserBalance::class)
            ->findOneBy(['user_id' => $request->get('user_id')]);

        if (!$user) {
            throw new NotFoundHttpException('User balance not found');
        }

        return new JsonResponse($user->getBalance(), Response::HTTP_OK);
    }

    #[Route('/api/v1/payment-service', name: 'payment-service')]
    public function paymentForService(Request $request): Response
    {
        $money = $request->get('money');

        $senderWallet = $this->entityManager->getRepository(UserBalance::class)
            ->findOneBy(['user_id' => $request->get('user_id')]);

        if (!$senderWallet) {
            throw new NotFoundHttpException('User not found');
        }

        if ($senderWallet->getBalance() == 0 || $senderWallet->getBalance() < $money) {
            return new Response(
                'There is not enough money in your account. Top up your account.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->entityManager->beginTransaction();

        try {
            $this->userOrderService->createOrder($senderWallet, $request->get('service_id'), $money);

            $senderWallet->setBalance($senderWallet->getBalance() - $money);
            $senderWallet->setHold($senderWallet->getHold() + $money);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return new Response(
            'A user with id ' . $request->get('user_id') . ' placed an order for a service with id ' . $request->get('service_id') . ' in the amount of ' . $money . ' rubles',
            Response::HTTP_OK
        );
    }

    #[Route('/api/v1/confirmed-order', name: 'confirmed-order')]
    public function confirmedOrder(Request $request): Response
    {

    }
}