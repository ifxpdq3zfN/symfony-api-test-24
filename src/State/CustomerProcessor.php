<?php

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\BrokerUser;
use App\Entity\Customer;
use App\Throwable\InvalidTypeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CustomerProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
    ) {
    }

    /**
     * @param Customer $data
     * @return Customer
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Customer) {
            throw new InvalidTypeException($data, Customer::class);
        }
        $data = $this->modifyCustomer($data, $operation);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function modifyCustomer(Customer $customer, Operation $operation): Customer
    {
        if ($operation instanceof Delete) {
            $customer->setIsDeleted(true);
        } elseif ($operation instanceof Post) {
            $currentUser = $this->security->getUser();
            if ($currentUser instanceof BrokerUser) {
                $customer->setBroker($currentUser->getBroker());
            }
        }

        return $customer;
    }
}
