<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Address;
use App\Repository\AddressRepository;
use App\Throwable\InvalidTypeException;

/**
 * @implements ProviderInterface<Address>
 */
class CustomerAddressDetailProvider implements ProviderInterface
{
    public function __construct(
        private readonly AddressRepository $customerRepository,
    ) {
    }

    /**
     * @param array<array-key,mixed> $uriVariables
     * @param array<array-key,mixed> $context
     * @return list<Address>|Address|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $customerId = $uriVariables['customerId'];
        if (!is_string($customerId)) {
            throw new InvalidTypeException($customerId, 'string');
        }

        return $this->customerRepository
            ->getCustomerAddresses($customerId);
    }
}
