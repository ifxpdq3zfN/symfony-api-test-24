<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Address;
use App\Entity\Broker;
use App\Entity\BrokerUser;
use App\Entity\Customer;
use App\Entity\CustomerUser;
use App\Throwable\InvalidTypeException;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

final class CurrentUserExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void {
        $this->modifyQueryBuilder($queryBuilder, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        Operation $operation = null,
        array $context = []
    ): void {
        $this->modifyQueryBuilder($queryBuilder, $resourceClass);
    }

    private function modifyQueryBuilder(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (Customer::class === $resourceClass) {
            $this->modifyCustomerQueryBuilder($queryBuilder);
        } elseif (Address::class === $resourceClass) {
            $this->modifyAddressQuery($queryBuilder);
        } elseif (CustomerUser::class === $resourceClass) {
            $this->modifyCustomerUserQueryBuilder($queryBuilder);
        }
    }

    private function modifyAddressQuery(QueryBuilder $queryBuilder): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->leftJoin("{$rootAlias}.customerAddressDetails", 'customerAddressDetail')
            ->leftJoin("customerAddressDetail.customer", 'customer')
            ->andWhere("customer.broker = :currentUser")
            ->andWhere("customerAddressDetail.isDeleted = :isDeleted")
            ->andWhere("customer.isDeleted = :isDeleted");

        $queryBuilder
            ->setParameter('currentUser', $this->getCurrentLoggedInBroker())
            ->setParameter('isDeleted', false, ParameterType::INTEGER);
    }

    private function modifyCustomerQueryBuilder(QueryBuilder $queryBuilder): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere("{$rootAlias}.broker = :currentUser")
            ->andWhere("{$rootAlias}.isDeleted = :isDeleted");

        $queryBuilder
            ->setParameter('currentUser', $this->getCurrentLoggedInBroker())
            ->setParameter('isDeleted', false, ParameterType::INTEGER);
    }

    private function modifyCustomerUserQueryBuilder(QueryBuilder $queryBuilder): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->leftJoin("{$rootAlias}.customer", 'customer')
            ->andWhere("{$rootAlias}.isActive = :isActive")
            ->andWhere("customer.broker = :currentUser")
            ->andWhere("customer.isDeleted = :isDeleted");

        $queryBuilder
            ->setParameter('currentUser', $this->getCurrentLoggedInBroker())
            ->setParameter('isActive', true, ParameterType::INTEGER)
            ->setParameter('isDeleted', false, ParameterType::INTEGER);
    }

    private function getCurrentLoggedInBroker(): Broker
    {
        $currentUser = $this->security->getUser();
        if ($currentUser instanceof BrokerUser) {
            return $currentUser->getBroker();
        }
        throw new InvalidTypeException($currentUser, BrokerUser::class);

    }
}
