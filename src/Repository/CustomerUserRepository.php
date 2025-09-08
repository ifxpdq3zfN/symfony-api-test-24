<?php

namespace App\Repository;

use App\Entity\CustomerUser;
use App\Throwable\InvalidTypeException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerUser>
 */
class CustomerUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerUser::class);
    }

    public function save(CustomerUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CustomerUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<CustomerUser>
     */
    public function getAll(): array
    {
        return $this->findAll();
    }

    public function getById(int $customerUserId): CustomerUser
    {
        $customerUser = $this->findOneBy(['id' => $customerUserId]);
        if ($customerUser instanceof CustomerUser) {
            return $customerUser;
        }
        throw new InvalidTypeException($customerUser, CustomerUser::class);
    }
}
