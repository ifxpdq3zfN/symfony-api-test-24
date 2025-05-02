<?php

namespace App\Repository;

use App\Entity\Address;
use App\Entity\Customer;
use App\Entity\CustomerAddressDetail;
use App\Throwable\InvalidTypeException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Address>
 */
class AddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Address::class);
    }

    public function save(Address $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Address $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<Address>
     */
    public function getAll(): array
    {
        return $this->findAll();
    }

    public function getById(int $addressId): Address
    {
        $address = $this->findOneBy(['id' => $addressId]);
        if ($address instanceof Address) {
            return $address;
        }
        throw new InvalidTypeException($address, Address::class);
    }

    /**
     * @param string $customerId
     * @return list<Address>
     */
    public function getCustomerAddresses(string $customerId): mixed
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('a')
            ->from(Address::class, 'a')
            ->innerJoin(CustomerAddressDetail::class, 'ca', 'WITH', 'ca.address = a')
            ->innerJoin(Customer::class, 'c', 'WITH', 'ca.customer = c')
            ->andWhere('ca.customer = :customerId')
            ->andWhere('ca.isDeleted = false')
            ->andWhere('c.isDeleted = 0');

        $queryBuilder->setParameter('customerId', $customerId);

        return $queryBuilder->getQuery()->getResult();
    }
}
