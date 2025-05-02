<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\CustomerUser;
use App\Throwable\InvalidTypeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CustomerUserProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * @param CustomerUser $data
     * @return CustomerUser
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof CustomerUser) {
            throw new InvalidTypeException($data, CustomerUser::class);
        }
        $customerUser = $this->modifyCustomerUser($data, $operation);

        return $this->persistProcessor->process($customerUser, $operation, $uriVariables, $context);
    }

    /**
     * @param CustomerUser $customerUser
     */
    private function modifyCustomerUser(CustomerUser $customerUser, Operation $operation): CustomerUser
    {
        if ($operation instanceof Delete) {
            $customerUser->setIsActive(false);
        }

        if ($customerUser->getPlainPassword()) {
            $hashedPassword = $this->passwordHasher->hashPassword(
                $customerUser,
                $customerUser->getPlainPassword()
            );
            $customerUser->setPassword($hashedPassword);
            $customerUser->setPlainPassword(null);
        }

        return $customerUser;
    }
}
