<?php

namespace App\Repository;

use App\Entity\OrderService;
use App\Services\UserOrderService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends ServiceEntityRepository<OrderService>
 *
 * @method OrderService|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderService|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderService[]    findAll()
 * @method OrderService[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HoldOrderRepository extends ServiceEntityRepository
{
    private $paginator;

    public function __construct(ManagerRegistry $registry, PaginatorInterface $paginator)
    {
        parent::__construct($registry, OrderService::class);
        $this->paginator = $paginator;
    }

    public function getSum(int $year, int $month, string $service_id): array
    {
        $startDate = new \DateTimeImmutable("$year-$month-01 00:00:00");
        $endDate = (clone $startDate)->modify('last day of this month')
            ->setTime(23, 59, 59);

        return $this->createQueryBuilder('o')
            ->select(['SUM(o.money) AS sum'])
            ->andWhere('o.service_id = :service_id')
            ->andWhere('o.created_at >= :start_date')
            ->andWhere('o.created_at <= :end_date')
            ->setParameter('service_id', $service_id)
            ->setParameter('start_date', $startDate)
            ->setParameter('end_date', $endDate)
            ->getQuery()
            ->getResult();
    }

    public function getServiceTransactions(Request $request): PaginationInterface
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.user_id = :user_id')
            ->andWhere('o.status = :status')
            ->setParameter('user_id', $request->get('user_id'))
            ->setParameter('status', UserOrderService::CONFIRMED)
            ->getQuery()
            ->getResult();

        return $this->paginator->paginate($qb, $request->get('page'), 2);
    }

//    /**
//     * @return OrderService[] Returns an array of OrderService objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('h')
//            ->andWhere('h.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('h.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?OrderService
//    {
//        return $this->createQueryBuilder('h')
//            ->andWhere('h.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
