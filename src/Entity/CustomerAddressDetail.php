<?php

/**
 * @noinspection PhpUnused
 * @noinspection PhpPropertyOnlyWrittenInspection
 */

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Repository\CustomerAddressDetailRepository;
use App\State\CustomerAddressDetailProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ORM\Entity(repositoryClass: CustomerAddressDetailRepository::class)]
#[ORM\Table(name: 'std.kunde_adresse')]
#[ApiResource(
    uriTemplate: '/kunden/{customerId}/adressen/{addressId}/details.{_format}',
    operations: [new Get()],
    uriVariables: [
        'customerId' => new Link(
            fromClass: Customer::class,
            identifiers: ['id']
        ),
        'addressId' => new Link(
            fromClass: Address::class,
            identifiers: ['id']
        ),
    ],
    provider: CustomerAddressDetailProvider::class,
)]
class CustomerAddressDetail
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'customerAddressDetails')]
    #[ORM\JoinColumn(name: 'kunde_id', referencedColumnName: 'id', nullable: false)]
    private Customer $customer;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Address::class, inversedBy: 'customerAddressDetail')]
    #[ORM\JoinColumn(name: 'adresse_id', referencedColumnName: 'adresse_id', nullable: false)]
    private Address $address;

    #[ORM\Column(name: 'geschaeftlich', type: Types::BOOLEAN)]
    private bool $isBusiness = false;

    #[ORM\Column(name: 'rechnungsadresse', type: Types::BOOLEAN)]
    private bool $isBillingAddress = false;

    #[ORM\Column(name: 'geloescht', type: Types::BOOLEAN)]
    private bool $isDeleted = false;

    #[Groups(['address:write'])]
    #[SerializedName('kunde')]
    public function setCustomer(Customer $customer): CustomerAddressDetail
    {
        $this->customer = $customer;

        return $this;
    }

    #[Groups(['address:write'])]
    #[SerializedName('adresse')]
    public function setAddress(Address $address): CustomerAddressDetail
    {
        $this->address = $address;

        return $this;
    }

    #[Groups(['address:write'])]
    #[SerializedName('geschaeftlich')]
    public function setIsBusiness(bool $isBusiness): CustomerAddressDetail
    {
        $this->isBusiness = $isBusiness;

        return $this;
    }

    #[Groups(['address:write'])]
    #[SerializedName('rechnungsadresse')]
    public function setIsBillingAddress(bool $isBillingAddress): CustomerAddressDetail
    {
        $this->isBillingAddress = $isBillingAddress;

        return $this;
    }

    public function setIsDeleted(bool $isDeleted): CustomerAddressDetail
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

    #[Groups(['address:read'])]
    #[SerializedName('kunde')]
    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    #[Groups(['address:read', 'customer:read'])]
    #[SerializedName('geschaeftlich')]
    public function isBusiness(): bool
    {
        return $this->isBusiness;
    }

    #[Groups(['address:read', 'customer:read'])]
    #[SerializedName('rechnungsadresse')]
    public function isBillingAddress(): bool
    {
        return $this->isBillingAddress;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }
}
