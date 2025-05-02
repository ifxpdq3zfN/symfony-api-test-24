<?php

/**
 * @noinspection PhpUnused
 * @noinspection PhpPropertyOnlyWrittenInspection
 */

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BrokerRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: BrokerRepository::class)]
#[ORM\Table(name: 'std.vermittler')]
class Broker
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
