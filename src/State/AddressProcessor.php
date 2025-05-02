<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Address;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AddressProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
    ) {
    }

    /**
     * @param Address $data
     * @return Address
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation instanceof Delete) {
            foreach ($data->getCustomerAddressDetails() as $customerAddressDetail) {
                $customerAddressDetail->setIsDeleted(true);
            }
        } elseif ($operation instanceof Post) {
            foreach ($data->getCustomerAddressDetails() as $customerAddressDetail) {
                $customerAddressDetail->setAddress($data);
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
