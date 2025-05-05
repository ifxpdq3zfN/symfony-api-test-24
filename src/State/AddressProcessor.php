<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Address;
use App\Throwable\InvalidTypeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AddressProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
    ) {
    }

    /**
     * @param Address|mixed $data
     * @param array<array-key,mixed> $uriVariables
     * @param array<array-key,mixed> $context
     * @return Address
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Address) {
            throw new InvalidTypeException($data, Address::class);
        }
        if ($operation instanceof Delete) {
            foreach ($data->getCustomerAddressDetails() as $customerAddressDetail) {
                $customerAddressDetail->setIsDeleted(true);
            }
        } elseif ($operation instanceof Post) {
            foreach ($data->getCustomerAddressDetails() as $customerAddressDetail) {
                $customerAddressDetail->setAddress($data);
            }
        }

        $processedAddress = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        if (!$processedAddress instanceof Address) {
            throw new InvalidTypeException($processedAddress, Address::class);
        }

        return $processedAddress;
    }
}
