<?php

namespace App\Repository;

use App\Entity\Address;
use App\Entity\Customer;
use App\Entity\CustomerAddressDetail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerAddressDetail>
 */
class CustomerAddressDetailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerAddressDetail::class);
    }

    public function save(CustomerAddressDetail $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CustomerAddressDetail $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<CustomerAddressDetail>
     */
    public function getAll(): array
    {
        return $this->findAll();
    }

    public function getCustomerAddressDetail(string $customerId, int $addressId): CustomerAddressDetail
    {
        $queryBuilder = $this->createQueryBuilder('cad')
            ->leftJoin(Address::class, 'a', 'WITH', 'cad.address = a')
            ->leftJoin(Customer::class, 'c', 'WITH', 'cad.customer = c');

        $queryBuilder->andWhere('cad.isDeleted = false')
            ->andWhere('a.id = :addressId')
            ->andWhere('c.id = :customerId')
            ->andWhere('c.isDeleted = 0');

        $queryBuilder->setParameter('addressId', $addressId)
            ->setParameter('customerId', $customerId);

        $customerAddressDetail = $queryBuilder->getQuery()->getSingleResult();
        assert($customerAddressDetail instanceof CustomerAddressDetail);

        return $customerAddressDetail;
    }
}
