<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

/**
 * Normalizes a {@see \UnitEnum} to name of enum case.
 * With {@see \BackedEnum} only normalizes if {@see self::BACKED_ENUM_PREFER_NAMES} context is passed.
 *
 * @author Misha Kulakovsky <misha@kulakovs.ky>
 */
final class UnitEnumNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * If true, will handle BackedEnums by their case names.
     * If false, will skip BackedEnums, passing them next to {@see BackedEnumNormalizer}
     */
    const BACKED_ENUM_PREFER_NAMES = 'backed_enum_prefer_names';

    /**
     * If true, will denormalize any invalid value into null.
     */
    const ALLOW_INVALID_CASES = 'allow_invalid_values';

    public function getSupportedTypes(?string $format): array
    {
        return [
            \UnitEnum::class => true,
        ];
    }

    public function normalize(mixed $object, string $format = null, array $context = []): int|string
    {
        if (!$object instanceof \UnitEnum) {
            throw new InvalidArgumentException('The data must belong to an enumeration.');
        }

        if ($object instanceof \BackedEnum && !($context[self::BACKED_ENUM_PREFER_NAMES] ?? false)) {
            throw new InvalidArgumentException(sprintf('The data must belong to a non-backed enumeration, or pass "%s" context attribute to normalize BackedEnums to names instead of values.', self::BACKED_ENUM_PREFER_NAMES));
        }

        return $object->name;
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        if (!$data instanceof \UnitEnum) {
            return false;
        }
        if ($data instanceof \BackedEnum && !($context[self::BACKED_ENUM_PREFER_NAMES] ?? false)) {
            return false;
        }
        return true;
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed
    {
        if (!is_subclass_of($type, \UnitEnum::class)) {
            throw new InvalidArgumentException('The data must belong to an enumeration.');
        }

        if (is_subclass_of($type, \BackedEnum::class) && !($context[self::BACKED_ENUM_PREFER_NAMES] ?? false)) {
            throw new InvalidArgumentException(sprintf('The data must belong to a non-backed enumeration, or pass "%s" context attribute to normalize BackedEnums to names instead of values.', self::BACKED_ENUM_PREFER_NAMES));
        }

        if (!\is_string($data) && !($context[self::ALLOW_INVALID_CASES] ?? false)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(sprintf("The data is not a string, you should pass a string that can be parsed as an enumeration case of type %s.", $type), $data, [Type::BUILTIN_TYPE_STRING], $context['deserialization_path'] ?? null, true);
        }

        try {
            $constantName = "$type::$data";
            if (!\defined($constantName)) {
                throw new \ValueError(sprintf("%s is not a valid case for enum %s.", $data, $type));
            }

            $value = \constant($constantName);
            if (!\is_object($value) || $value::class !== $type) {
                throw new \ValueError(sprintf("%s is not a valid enum case.", $constantName));
            }

            return $value;
        } catch (\ValueError $e) {
            if ($context[self::ALLOW_INVALID_CASES] ?? false) {
                return null;
            }
            if (isset($context['has_constructor'])) {
                throw new InvalidArgumentException(sprintf("The data must belong to an enumeration of type %s.", $type));
            }
            throw NotNormalizableValueException::createForUnexpectedDataType(sprintf("The data must belong to an enumeration of type %s.", $type), $data, [$type], $context['deserialization_path'] ?? null, true, 0, $e);
        }
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        if (!is_subclass_of($type, \UnitEnum::class)) {
            return false;
        }
        if (is_subclass_of($type, \BackedEnum::class) && !($context[self::BACKED_ENUM_PREFER_NAMES] ?? false)) {
            return false;
        }
        return true;
    }
}
