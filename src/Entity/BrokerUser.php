<?php

/**
 * @noinspection PhpUnused
 * @noinspection PhpPropertyOnlyWrittenInspection
 */

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BrokerUserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: BrokerUserRepository::class)]
#[ORM\Table(name: 'sec.vermittler_user')]
class BrokerUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 200, nullable: false)]
    private string $username;

    #[ORM\Column(name: 'passwd', type: Types::STRING, length: 60, nullable: false)]
    private string $password;

    #[ORM\ManyToOne(targetEntity: Broker::class)]
    #[ORM\JoinColumn(name: 'vermittler_id', referencedColumnName: 'id', nullable: false)]
    private Broker $broker;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setBroker(Broker $broker): BrokerUser
    {
        $this->broker = $broker;

        return $this;
    }

    public function setUsername(string $username): BrokerUser
    {
        $this->username = $username;

        return $this;
    }

    public function getRoles(): array
    {
        return [];
    }

    public function eraseCredentials(): void
    {
        $this->password = '';
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getBroker(): Broker
    {
        return $this->broker;
    }
}
