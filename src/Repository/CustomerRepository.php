<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Throwable\InvalidTypeException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    public function save(Customer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Customer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<Customer>
     */
    public function getAll(): array
    {
        return $this->findAll();
    }

    /**
     * @param non-empty-string $customerId
     */
    public function getById(string $customerId): Customer
    {
        $customer = $this->findOneBy(['id' => $customerId]);
        if ($customer instanceof Customer) {
            return $customer;
        }
        throw new InvalidTypeException($customer, Customer::class);
    }
}
