<?php

/**
 * @noinspection PhpUnused
 * @noinspection PhpPropertyOnlyWrittenInspection
 */

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Tests\Fixtures\Metadata\Get;
use App\Repository\AddressRepository;
use App\State;
use App\State\AddressProcessor;
use App\State\CustomerAddressProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AddressRepository::class)]
#[ORM\Table(name: 'std.adresse')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: self::TRANSLATED_RESOURCES_URI),
        new Post(uriTemplate: self::TRANSLATED_RESOURCES_URI),
        new Get(uriTemplate: self::TRANSLATED_RESOURCE_URI),
        new Put(uriTemplate: self::TRANSLATED_RESOURCE_URI),
        new Delete(uriTemplate: self::TRANSLATED_RESOURCE_URI),
    ],
    normalizationContext: ['groups' => ['address:read']],
    denormalizationContext: ['groups' => ['address:write']],
    processor: AddressProcessor::class
)]
#[ApiResource(
    uriTemplate: '/kunden/{customerId}/adressen.{_format}',
    operations: [new GetCollection()],
    uriVariables: [
        'customerId' => new Link(
            fromClass: Customer::class,
            identifiers: ['customerId']
        ),
    ],
    provider: CustomerAddressProvider::class
)]
class Address
{
    private const TRANSLATED_RESOURCES_URI = '/adressen.{_format}';
    private const TRANSLATED_RESOURCE_URI = '/adressen/{id}.{_format}';

    #[Groups(['address:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'adresse_id', type: Types::INTEGER)]
    private int $id;

    #[Groups(['address:read', 'address:write', 'customer:read'])]
    #[SerializedName('strasse')]
    #[Assert\NotBlank(message: 'Property "strasse" cannot be blank')]
    #[ORM\Column(name: 'strasse', type: Types::STRING)]
    private string $street = '';

    #[Groups(['address:read', 'address:write', 'customer:read'])]
    #[SerializedName('plz')]
    #[Assert\NotBlank(message: 'Property "plz" cannot be blank')]
    #[ORM\Column(name: 'plz', type: Types::STRING, length: 10, nullable: true)]
    private ?string $zipCode;

    #[Groups(['address:read', 'address:write', 'customer:read'])]
    #[SerializedName('ort')]
    #[Assert\NotBlank(message: 'Property "ort" cannot be blank')]
    #[ORM\Column(name: 'ort', type: Types::STRING)]
    private string $location = '';

    #[Groups(['address:read', 'address:write', 'customer:read'])]
    #[SerializedName('bundesland')]
    #[Assert\NotBlank(message: 'Property "bundesland" cannot be blank')]
    #[Assert\NotEqualTo(value: State::NONE, message: 'Property "bundesland" cannot be blank')]
    #[ORM\Column(name: 'bundesland', type: Types::STRING, length: 2, nullable: false, enumType: State::class)]
    private State $state;

    /**
     * @var Collection<int, CustomerAddressDetail>
     */
    #[Groups(['address:read'])]
    #[ORM\OneToMany(mappedBy: 'address', targetEntity: CustomerAddressDetail::class, cascade: ['persist'])]
    private Collection $customerAddressDetails;

    private CustomerAddressDetail $customerRelatedCustomerAddressDetail;

    public function __construct()
    {
        $this->customerAddressDetails = new ArrayCollection();
    }

    public function setId(int $id): Address
    {
        $this->id = $id;

        return $this;
    }

    #[Groups(['customer:read'])]
    #[SerializedName('adresseId')]
    public function getAddressId(): int
    {
        return $this->getId();
    }

    #[Groups(['address:read', 'customer:read'])]
    #[SerializedName('details')]
    public function getCustomerRelatedCustomerAddressDetail(): CustomerAddressDetail
    {
        return $this->customerRelatedCustomerAddressDetail;
    }

    public function setCustomerRelatedCustomerAddressDetail(CustomerAddressDetail $customerAddressDetail): Address
    {
        $this->customerRelatedCustomerAddressDetail = $customerAddressDetail;

        return $this;
    }

    public function setStreet(?string $street): Address
    {
        $this->street = $street ?? '';

        return $this;
    }

    public function setZipCode(?string $zipCode): Address
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function setLocation(?string $location): Address
    {
        $this->location = $location ?? '';

        return $this;
    }

    public function setState(?State $state): Address
    {
        $this->state = $state ?? State::NONE;

        return $this;
    }

    #[Groups(['address:write'])]
    public function setCustomerAddressDetail(CustomerAddressDetail $customerAddressDetail): Address
    {
        $this->customerRelatedCustomerAddressDetail = $customerAddressDetail;
        $this->customerAddressDetails->add($customerAddressDetail);

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getState(): ?State
    {
        return $this->state;
    }

    public function getLocation(): string
    {
        return $this->location ?? '';
    }

    public function getZipCode(): string
    {
        return $this->zipCode ?? '';
    }

    public function getStreet(): string
    {
        return $this->street ?? '';
    }

    /**
     * @return Collection<int, CustomerAddressDetail>
     */
    #[Groups(['address:read'])]
    public function getCustomerAddressDetails(): Collection
    {
        return $this->customerAddressDetails;
    }
}
