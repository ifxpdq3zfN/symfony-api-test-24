<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Address;
use App\Entity\Customer;
use App\Entity\CustomerAddressDetail;
use App\Entity\CustomerUser;
use App\Repository\AddressRepository;
use App\Repository\CustomerAddressDetailRepository;
use App\Repository\CustomerRepository;
use App\Repository\CustomerUserRepository;
use App\State;

final class EntityTest extends WebTestCase
{
    public function testEntities(): void
    {
        $customers = $this->getService(CustomerRepository::class)
            ->getAll();
        $customer = $customers[array_key_first($customers)] ?? null;
        self::assertInstanceOf(Customer::class, $customer);

        $addresses = $this->getService(AddressRepository::class)
            ->getAll();
        $address = $addresses[array_key_first($addresses)] ?? null;
        self::assertInstanceOf(Address::class, $address);
        self::assertSame(1, $address->getId());
        self::assertSame(State::BE, $address->getState());
        self::assertSame('Berlin', $address->getLocation());
        self::assertSame('10115', $address->getZipCode());
        self::assertSame('Invalidenstr. 23', $address->getStreet());

        $customerAddressDetails = $this->getService(CustomerAddressDetailRepository::class)
            ->getAll();
        $customerAddressDetail = $customerAddressDetails[array_key_first($customerAddressDetails)] ?? null;
        self::assertInstanceOf(CustomerAddressDetail::class, $customerAddressDetail);
        self::assertNotNull($customerAddressDetail->getCustomer());
        self::assertNotNull($customerAddressDetail->getAddress());
        self::assertNotNull($customerAddressDetail->isBillingAddress());
        self::assertNotNull($customerAddressDetail->isBusiness());
        self::assertNotNull($customerAddressDetail->isDeleted());

        $customerUsers = $this->getService(CustomerUserRepository::class)
            ->getAll();
        $customerUser = $customerUsers[array_key_first($customerUsers)] ?? null;
        self::assertInstanceOf(CustomerUser::class, $customerUser);

        self::assertSame(1, $customerUser->getId());
        self::assertSame('kari@example.com', $customerUser->getUsername());
    }
}
