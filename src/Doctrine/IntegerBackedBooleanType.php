<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

class IntegerBackedBooleanType extends Type
{
    public const INTEGER_BACKED_BOOLEAN = 'integer_backed_boolean';

    /**
     * @param array<array-key,mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getSmallIntTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): bool
    {
        if (!($value === null || is_int($value))) {
            throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['int', 'null']);
        }

        return (bool) $value;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): int
    {
        if (!($value === null || is_bool($value))) {
            throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['bool', 'null']);
        }

        if (null === $value) {
            return 0;
        }

        return (int) $value;
    }

    public function getName(): string
    {
        return self::INTEGER_BACKED_BOOLEAN;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
