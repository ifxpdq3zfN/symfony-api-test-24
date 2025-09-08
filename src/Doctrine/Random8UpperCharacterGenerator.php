<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Symfony\Component\Uid\Uuid;

use function Symfony\Component\String\u;

class Random8UpperCharacterGenerator extends AbstractIdGenerator
{
    public function generateId(EntityManagerInterface $em, $entity): string
    {
        // Database id column default value: UPPER("left"((gen_random_uuid())::text, 8))
        return u((string) Uuid::v4())
            ->slice(0, 8)
            ->upper()
            ->toString();
    }
}
