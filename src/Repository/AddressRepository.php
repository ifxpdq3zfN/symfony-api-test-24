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
     * @return list<Address>
     */
    public function getCustomerAddresses(string $customerId): array
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('a')
            ->from(Address::class, 'a')
            ->innerJoin(CustomerAddressDetail::class, 'cad', 'WITH', 'cad.address = a')
            ->innerJoin(Customer::class, 'c', 'WITH', 'cad.customer = c')
            ->andWhere('cad.customer = :customerId')
            ->andWhere('cad.isDeleted = false')
            ->andWhere('c.isDeleted = 0');

        $queryBuilder->setParameter('customerId', $customerId);

        /** @var list<Address> */
        return $queryBuilder->getQuery()->getResult();
    }
}
