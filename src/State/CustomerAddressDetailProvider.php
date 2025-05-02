<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\AddressRepository;

class CustomerAddressDetailProvider implements ProviderInterface
{
    public function __construct(
        private readonly AddressRepository $customerRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->customerRepository
            ->getCustomerAddresses($uriVariables['customerId']);
    }
}
