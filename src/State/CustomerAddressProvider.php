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
class CustomerAddressProvider implements ProviderInterface
{
    public function __construct(
        private readonly AddressRepository $customerRepository,
    ) {
    }

    /**
     * @param array<array-key,mixed> $uriVariables
     * @param array<array-key,mixed> $context
     * @return list<Address>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $customerId = $uriVariables['customerId'];
        if (!is_string($customerId)) {
            throw new InvalidTypeException($customerId, 'string');
        }

        return $this->customerRepository
            ->getCustomerAddresses($customerId);
    }
}
