<?php

namespace App\Controller;

use App\Entity\OrderService;
use App\Entity\UserBalance;
use App\Message\AddMoneyToBalanceNotification;
use App\Message\CreateCSVFileNotification;
use App\Message\TransferMoneyNotification;
use App\Repository\HoldOrderRepository;
use App\Repository\UserBalanceRepository;
use App\Services\UserBalanceService;
use App\Services\UserOrderService;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Writer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;

class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserOrderService       $userOrderService,
        private UserBalanceService     $userBalanceService,
        private SerializerInterface    $serializer,
        private MessageBusInterface    $bus
    ) {}

    /**
     * Add money to user balance
     *
     * @Route("/api/v1/add-money", methods={"POST"})
     * @OA\Response(
     *     response=200,
     *     description="Returns the confirmed message",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=UserBalance::class, groups={"full"}))
     *     )
     * )
     * @OA\Parameter(
     *     name="user_id",
     *     in="query",
     *     description="User ID",
     *     @OA\Schema(type="integer")
     * )
     * @OA\Parameter(
     *     name="money",
     *     in="query",
     *     description="Money",
     *     @OA\Schema(type="integer")
     * )
     * @OA\Tag(name="API for work with user balance")
     */
    public function addBalance(Request $request): Response
    {
        $user  = (integer) $request->get('user_id');
        $money = (integer) $request->get('money');

        $balance = $this->entityManager->getRepository(UserBalance::class)
            ->findOneBy(['user_id' => $user]);

        if (!$balance) {
            try {
                $this->userBalanceService->addMoney($user, $money);
            } catch (\Exception $e) {
                $this->bus->dispatch(new AddMoneyToBalanceNotification(
                    'User with id' . $user . ' couldn\'t top up my balance on ' . $money . ' because: ' . $e->getMessage()
                ));
            }

            $this->bus->dispatch(new AddMoneyToBalanceNotification(
                'User with id' . $user . ' topped up balance with ' . $money
            ));
        } else {
            $this->userBalanceService->updateMoney($balance, $money);

            $this->bus->dispatch(new AddMoneyToBalanceNotification(
                'User with id' . $user . ' topped up balance with ' . $money
            ));
        }

        return new Response('Balance replenished by ' . $money, Response::HTTP_OK);
    }

    /**
     * Transfer money from user to user
     *
     * @Route("/api/v1/transfer-money", methods={"POST"})
     * @OA\Response(
     *     response=200,
     *     description="Returns the confirmed message",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=UserBalance::class, groups={"full"}))
     *     )
     * )
     * @OA\Parameter(
     *     name="sender_id",
     *     in="query",
     *     description="Sender ID",
     *     @OA\Schema(type="integer")
     * )
     * @OA\Parameter(
     *     name="recipient_id",
     *     in="query",
     *     description="Recipient ID",
     *     @OA\Schema(type="integer")
     *  )
     * @OA\Parameter(
     *     name="money",
     *     in="query",
     *     description="Money",
     *     @OA\Schema(type="integer")
     * )
     * @OA\Tag(name="API for work with user balance")
     */
    public function sendMoneyFromUserToUser(Request $request): Response
    {
        $senderWallet = $this->entityManager->getRepository(UserBalance::class)
            ->findOneBy(['user_id' => (integer) $request->get('sender_id')]);

        $recipientWallet = $this->entityManager->getRepository(UserBalance::class)
            ->findOneBy(['user_id' => (integer) $request->get('recipient_id')]);

        if (!$senderWallet || !$recipientWallet) {
            throw new NotFoundHttpException('User not found');
        }

        if ($senderWallet->getBalance() == 0 || $senderWallet->getBalance() < (integer) $request->get('money')) {
            $this->bus->dispatch(new TransferMoneyNotification(
                'ggg'
            ));

            return new Response(
                'There is not enough money in your account. Top up your account.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->userBalanceService->sendMoneyToUserFromUser(
            $senderWallet,
            $recipientWallet,
            (integer) $request->get('money')
        );

        $this->bus->dispatch(new TransferMoneyNotification(
            'OK!. Transfer of the amount ' . $request->get('money') .
            ' from a user with id ' . $senderWallet->getUserId() . ' => to a user with id ' .
            $recipientWallet->getUserId()
        ));

        return new Response(
            'User with id ' . $senderWallet->getUserId() . ' transferred ' .
            $request->get('money') . ' rubles to user with id ' . $recipientWallet->getUserId(),
            Response::HTTP_OK
        );
    }


    /**
     * Get user balance
     *
     * @Route("/api/v1/get-balance", methods={"POST"})
     * @OA\Response(
     *     response=200,
     *     description="Get user balance",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=UserBalance::class, groups={"full"}))
     *     )
     * )
     * @OA\Parameter(
     *     name="user_id",
     *     in="query",
     *     description="User ID",
     *     @OA\Schema(type="integer")
     * )
     * @OA\Tag(name="API for work with user balance")
     */
    public function getUserBalance(Request $request): JsonResponse
    {
        $user = $this->entityManager->getRepository(UserBalance::class)
            ->findOneBy(['user_id' => (integer) $request->get('user_id')]);

        if (!$user) {
            throw new NotFoundHttpException('User balance not found');
        }

        return new JsonResponse($user->getBalance(), Response::HTTP_OK);
    }

    /**
     * Payment for service
     *
     * @Route("/api/v1/create-order", methods={"POST"})
     * @OA\Response(
     *     response=200,
     *     description="Payment for service",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=UserBalance::class, groups={"full"}))
     *     )
     * )
     * @OA\Parameter(
     *     name="user_id",
     *     in="query",
     *     description="User ID",
     *     @OA\Schema(type="integer")
     * )
     * @OA\Parameter(
     *     name="service_id",
     *     in="query",
     *     description="Service ID",
     *     @OA\Schema(type="integer")
     *  )
     * @OA\Parameter(
     *     name="money",
     *     in="query",
     *     description="Money",
     *     @OA\Schema(type="integer")
     *   )
     * @OA\Tag(name="API for work with user balance")
     */
    public function paymentForService(Request $request): Response
    {
        $senderWallet = $this->entityManager->getRepository(UserBalance::class)
            ->findOneBy(['user_id' => (integer) $request->get('user_id')]);

        if (!$senderWallet) {
            throw new NotFoundHttpException('User not found');
        }

        if ($senderWallet->getBalance() == 0 || $senderWallet->getBalance() < (integer) $request->get('money')) {
            return new Response(
                'There is not enough money in your account. Top up your account.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->userOrderService->createOrder($senderWallet, (integer) $request->get('service_id'), (integer) $request->get('money'));

        //Send mail to admin!?

        return new Response(
            'A user with id ' . $request->get('user_id') . ' placed an order for a service with id ' .
            $request->get('service_id') . ' in the amount of ' . $request->get('money') . ' rubles',
            Response::HTTP_OK
        );
    }

    /**
     * Confirm user order
     *
     * @Route("/api/v1/confirmed-order", methods={"POST"})
     * @OA\Response(
     *     response=200,
     *     description="Confirm user order",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=UserBalance::class, groups={"full"}))
     *     )
     * )
     * @OA\Parameter(
     *     name="user_id",
     *     in="query",
     *     description="User ID",
     *     @OA\Schema(type="integer")
     * )
     * @OA\Parameter(
     *     name="order_uuid",
     *     in="query",
     *     description="Order ID/UUID",
     *     @OA\Schema(type="string")
     *  )
     * @OA\Parameter(
     *     name="decision",
     *     in="query",
     *     description="Decision for order",
     *     @OA\Schema(type="boolean")
     *  )
     * @OA\Tag(name="API for work with user balance")
     */
    public function confirmedOrder(Request $request): Response
    {
        $decision = (boolean) $request->get('decision');

        $order = $this->entityManager->getRepository(OrderService::class)
            ->findOneBy(['order_uuid' => (string) $request->get('order_uuid')]);

        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }

        $userWallet = $this->entityManager->getRepository(UserBalance::class)
            ->findOneBy(['user_id' => $order->getUserId()]);

        if (!$decision) {
            $this->userOrderService->rejectedOrder($userWallet, $order);

            return new Response(
                'Your order # ' . $order->getOrderUuid() . ' was rejected by the administrator',
                Response::HTTP_OK
            );
        }

        $this->userOrderService->confirmedOrder($userWallet, $order);

        return new Response(
            'Your order # ' . $order->getOrderUuid() . ' was confirmed!',
            Response::HTTP_OK
        );
    }

    /**
     * Get sum for services transaction per year/month
     *
     * @Route("/api/v1/get-sum", methods={"POST"})
     * @OA\Response(
     *     response=200,
     *     description="Get sum for services transaction per year/month",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=UserBalance::class, groups={"full"}))
     *     )
     * )
     * @OA\Parameter(
     *     name="service_id",
     *     in="query",
     *     description="Service ID",
     *     @OA\Schema(type="integer")
     * )
     * @OA\Parameter(
     *     name="year",
     *     in="query",
     *     description="Year",
     *     @OA\Schema(type="integer")
     *  )
     * @OA\Parameter(
     *     name="month",
     *     in="query",
     *     description="Month(01-12)",
     *     @OA\Schema(type="integer")
     *  )
     * @OA\Tag(name="API for work with user balance")
     */
    public function getProfitSumForService(Request $request, HoldOrderRepository $repository): Response
    {
        $sum = $repository->getSum(
            (integer) $request->get('year'),
            (integer) $request->get('month'),
            (integer) $request->get('service_id')
        );

        $this->bus->dispatch(new CreateCSVFileNotification(
            $sum,
            (integer) $request->get('year'),
            (integer) $request->get('month'),
            (integer) $request->get('service_id')
        ));

        return new Response(
            'CSV File generated and sending to administrator email',
            Response::HTTP_OK
        );
    }

    /**
     * Get user transactions
     *
     * @Route("/api/v1/get-transactions", methods={"POST"})
     * @OA\Response(
     *     response=200,
     *     description="Get user balance",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=UserBalance::class, groups={"full"}))
     *     )
     * )
     * @OA\Parameter(
     *     name="user_id",
     *     in="query",
     *     description="User ID",
     *     @OA\Schema(type="integer")
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="Page number",
     *     @OA\Schema(type="integer")
     *  )
     * @OA\Tag(name="API for work with user balance")
     */
    public function getUserServiceTransactions(Request $request, HoldOrderRepository $repository): JsonResponse
    {
        $row = $repository->getServiceTransactions($request);

        return new JsonResponse(
            $this->serializer->serialize($row->getItems(), 'json'),
            Response::HTTP_OK
        );
    }
}