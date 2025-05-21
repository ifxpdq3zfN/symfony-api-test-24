<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\CustomerAddressDetail;
use App\Repository\CustomerAddressDetailRepository;
use App\Throwable\InvalidTypeException;

/**
 * @implements ProviderInterface<CustomerAddressDetail>
 */
class CustomerAddressDetailProvider implements ProviderInterface
{
    public function __construct(
        private readonly CustomerAddressDetailRepository $customerAddressDetailRepository,
    ) {
    }

    /**
     * @param array<array-key,mixed> $uriVariables
     * @param array<array-key,mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomerAddressDetail
    {
        $customerId = $uriVariables['customerId'];
        if (!is_string($customerId)) {
            throw new InvalidTypeException($customerId, 'string');
        }

        $addressId = $uriVariables['addressId'];
        if (!is_int($addressId)) {
            throw new InvalidTypeException($addressId, 'int');
        }

        return $this->customerAddressDetailRepository
            ->getCustomerAddressDetail($customerId, $addressId);
    }
}
