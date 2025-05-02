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
use App\Repository\CustomerUserRepository;
use App\State\CustomerUserProcessor;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CustomerUserRepository::class)]
#[ORM\Table(name: 'sec.user')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: self::TRANSLATED_RESOURCES_URI),
        new Post(uriTemplate: self::TRANSLATED_RESOURCES_URI),
        new Get(uriTemplate: self::TRANSLATED_RESOURCE_URI),
        new Put(uriTemplate: self::TRANSLATED_RESOURCE_URI),
        new Delete(uriTemplate: self::TRANSLATED_RESOURCE_URI),
    ],
    normalizationContext: ['groups' => ['customer-user:read']],
    denormalizationContext: ['groups' => ['customer-user:write']],
    processor: CustomerUserProcessor::class,
)]
#[ApiResource(
    uriTemplate: '/kunden/{customerId}/user.{_format}',
    operations: [new Get()],
    uriVariables: [
        'customerId' => new Link(
            fromProperty: 'customerUser',
            fromClass: Customer::class,
        ),
    ],
    normalizationContext: ['groups' => ['customer-user:read']],
)]
class CustomerUser implements PasswordAuthenticatedUserInterface
{
    private const TRANSLATED_RESOURCES_URI = '/user.{_format}';
    private const TRANSLATED_RESOURCE_URI = '/user/{id}.{_format}';
    private const PASSWORD_REQUIREMENTS_VIOLATION_MESSAGE = 'Property "passwd" must contain at least '
        . 'one uppercase letter, lowercase letter, digit and special character';

    #[Groups(['customer-user:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['customer:read', 'customer-user:read', 'customer-user:write'])]
    #[Assert\NotBlank(message: 'Property "email" cannot be blank')]
    #[Assert\Email(message: 'Property "email" value {{ value }} is not a valid email.')]
    #[ORM\Column(name: 'email', type: Types::STRING, length: 200, nullable: false)]
    private string $username;

    #[Ignore()]
    #[ORM\Column(name: 'passwd', type: Types::STRING, length: 60, nullable: false)]
    private string $password;

    // 'Property "passwd" cannot be blank'
    #[Assert\NotBlank(message: 'Property "passwd" cannot be blank')]
    #[Assert\Length(
        min: 8,
        minMessage: 'Property "passwd" must be at least {{ limit }} characters long'
    )]
    #[Assert\Regex(pattern: '/[A-Z]/', message: self::PASSWORD_REQUIREMENTS_VIOLATION_MESSAGE)]
    #[Assert\Regex(pattern: '/[a-z]/', message: self::PASSWORD_REQUIREMENTS_VIOLATION_MESSAGE)]
    #[Assert\Regex(pattern: '/\d/', message: self::PASSWORD_REQUIREMENTS_VIOLATION_MESSAGE)]
    #[Assert\Regex(pattern: '/[!@#$%^&*(),.?":{}|<>]/', message: self::PASSWORD_REQUIREMENTS_VIOLATION_MESSAGE)]
    private ?string $plainPassword = null;

    #[Groups(['customer:read', 'customer-user:read'])]
    #[SerializedName('aktiv')]
    #[ORM\Column(name: 'aktiv', type: Types::SMALLINT, length: 60, nullable: false)]
    private bool $isActive = true;

    #[Groups(['customer:read', 'customer-user:read'])]
    #[SerializedName('lastLogin')]
    #[ORM\Column(name: 'last_login', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeInterface $lastLogin;

    #[ORM\OneToOne(mappedBy: 'customerUser', targetEntity: Customer::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'kundenid', referencedColumnName: 'id')]
    private Customer $customer;

    public function setUsername(string $username): CustomerUser
    {
        $this->username = $username;

        return $this;
    }

    #[Groups(['customer-user:write'])]
    #[SerializedName('passwd')]
    public function setPlainPassword(?string $plainPassword): CustomerUser
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    #[Ignore()]
    public function setPassword(string $password): CustomerUser
    {
        $this->password = $password;

        return $this;
    }

    #[Ignore()]
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setIsActive(bool $isActive): CustomerUser
    {
        $this->isActive = $isActive;

        return $this;
    }

    #[Groups(['customer-user:write'])]
    #[SerializedName('kunde')]
    public function setCustomer(Customer $customer): CustomerUser
    {
        $this->customer = $customer;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    #[Groups(['customer:read', 'customer-user:read'])]
    #[SerializedName('aktiv')]
    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getLastLogin(): ?DateTimeInterface
    {
        return $this->lastLogin;
    }

    #[Groups(['customer-user:read'])]
    #[SerializedName('kunde')]
    public function getCustomer(): Customer
    {
        return $this->customer;
    }
}
