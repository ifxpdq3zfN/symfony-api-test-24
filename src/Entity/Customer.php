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
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Tests\Fixtures\Metadata\Get;
use App\Doctrine\Random8UpperCharacterGenerator;
use App\Repository\CustomerRepository;
use App\State\CustomerProcessor;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(
    repositoryClass: CustomerRepository::class
)]
#[ORM\Table(name: 'std.tbl_kunden')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: self::TRANSLATED_RESOURCES_URI),
        new Post(uriTemplate: self::TRANSLATED_RESOURCES_URI),
        new Get(uriTemplate: self::TRANSLATED_RESOURCE_URI),
        new Put(uriTemplate: self::TRANSLATED_RESOURCE_URI),
        new Delete(uriTemplate: self::TRANSLATED_RESOURCE_URI),
    ],
    normalizationContext: ['groups' => ['customer:read']],
    denormalizationContext: ['groups' => ['customer:write']],
    processor: CustomerProcessor::class,
)]
class Customer
{
    private const TRANSLATED_RESOURCES_URI = '/kunden.{_format}';
    private const TRANSLATED_RESOURCE_URI = '/kunden/{id}.{_format}';

    #[Groups(['customer:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: Random8UpperCharacterGenerator::class)]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $id = null;

    #[Groups(['customer:read', 'customer:write'])]
    #[SerializedName('vorname')]
    #[Assert\NotBlank(message: 'Property "vorname" cannot be blank')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Property "vorname" must be at least {{ limit }} characters long',
        maxMessage: 'Property "vorname" cannot be longer than {{ limit }} characters'
    )]
    #[ORM\Column(name: 'vorname', type: Types::STRING, length: 255)]
    private string $firstName;

    #[Groups(['customer:read', 'customer:write'])]
    #[SerializedName('name')]
    #[Assert\NotBlank(message: 'Property "name" cannot be blank')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Property "name" must be at least {{ limit }} characters long',
        maxMessage: 'Property "name" cannot be longer than {{ limit }} characters'
    )]
    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $lastName;

    #[Groups(['customer:read', 'customer:write'])]
    #[SerializedName('firma')]
    #[ORM\Column(name: 'firma', type: Types::STRING, nullable: true)]
    private ?string $company = null;

    #[Context(['datetime_format' => 'Y-m-d'])]
    #[Groups(['customer:read', 'customer:write'])]
    #[SerializedName('geburtsdatum')]
    #[Assert\NotBlank(message: 'Property "geburtstag" cannot be blank')]
    #[Assert\Type(DateTimeImmutable::class)]
    #[Assert\LessThan('today', message: 'Property "geburtstag" must be in the past')]
    #[ORM\Column(name: 'geburtsdatum', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $dateOfBirth = null;

    // 'männlich', 'weiblich', 'divers'
    #[Groups(['customer:read', 'customer:write'])]
    #[SerializedName('geschlecht')]
    #[ORM\Column(name: 'geschlecht', type: Types::STRING, nullable: true)]
    private ?string $gender = null;

    #[Groups(['customer:read', 'customer:write'])]
    #[SerializedName('email')]
    #[Assert\Email(message: 'Property "email" value {{ value }} is not a valid email.')]
    #[ORM\Column(name: 'email', type: Types::STRING, nullable: true)]
    private ?string $email = null;

    #[Groups(['customer:read'])]
    #[ORM\Column(name: 'geloescht', type: Types::SMALLINT, nullable: false)]
    private bool $isDeleted = false;

    #[ORM\ManyToOne(targetEntity: Broker::class)]
    #[ORM\JoinColumn(name: 'vermittler_id', referencedColumnName: 'id')]
    private Broker $broker;

    /**
     * @var Collection<int,CustomerAddressDetail>
     */
    #[Groups(['customer:write'])]
    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: CustomerAddressDetail::class, cascade: ['persist'])]
    private Collection $customerAddressDetails;

    #[ORM\OneToOne(mappedBy: 'customer', targetEntity: CustomerUser::class, cascade: ['persist'])]
    private CustomerUser $customerUser;

    public function __construct()
    {
        $this->customerAddressDetails = new ArrayCollection();
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function setDateOfBirth(?DateTimeImmutable $dateOfBirth): self
    {
        $this->dateOfBirth = $dateOfBirth?->setTime(0, 0);

        return $this;
    }

    public function setEmail(?string $email): Customer
    {
        $this->email = $email;

        return $this;
    }

    public function setCompany(?string $company): Customer
    {
        $this->company = $company;

        return $this;
    }

    public function setGender(?string $gender): Customer
    {
        $this->gender = $gender;

        return $this;
    }

    #[Ignore()]
    public function setIsDeleted(bool $isDeleted): Customer
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

    #[Ignore()]
    public function setBroker(Broker $broker): Customer
    {
        $this->broker = $broker;

        return $this;
    }

    public function setCustomerUser(CustomerUser $customerUser): Customer
    {
        $this->customerUser = $customerUser;

        return $this;
    }

    #[Groups(['customer:read'])]
    #[SerializedName('vermittlerId')]
    public function getBrokerId(): int
    {
        return $this->getBroker()->getId()
            ?? throw new RuntimeException('Broker id not yet set. Not persisted?');
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function getDateOfBirth(): ?DateTimeImmutable
    {
        return $this->dateOfBirth;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getBroker(): Broker
    {
        return $this->broker;
    }

    #[Groups(['customer:read'])]
    #[SerializedName('user')]
    public function getCustomerUser(): CustomerUser
    {
        return $this->customerUser;
    }

    /**
     * @return Collection<int,CustomerAddressDetail>
     */
    #[Ignore()]
    public function getCustomerAddressDetails(): Collection
    {
        return $this->customerAddressDetails;
    }

    /**
     * @return ReadableCollection<int, Address>
     */
    #[Groups(['customer:read'])]
    #[SerializedName('adressen')]
    public function getCustomerRelatedAddresses(): ReadableCollection
    {
        return $this->customerAddressDetails->map(
            function (CustomerAddressDetail $customerAddressDetail) {
                $customerAddressDetail->getAddress()->setCustomerRelatedCustomerAddressDetail($customerAddressDetail);

                return $customerAddressDetail->getAddress();
            }
        );
    }

    #[Ignore()]
    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }
}
