<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\UnitEnumNormalizer;
use Symfony\Component\Serializer\Tests\Fixtures\IntegerBackedEnumDummy;
use Symfony\Component\Serializer\Tests\Fixtures\StringBackedEnumDummy;
use Symfony\Component\Serializer\Tests\Fixtures\UnitEnumDummy;

/**
 * @author Misha Kulakovsky <misha@kulakovs.ky>
 */
class UnitEnumNormalizerTest extends TestCase
{
    /**
     * @var UnitEnumNormalizer
     */
    private $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new UnitEnumNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(StringBackedEnumDummy::GET));
        $this->assertFalse($this->normalizer->supportsNormalization(IntegerBackedEnumDummy::SUCCESS));
        $this->assertTrue($this->normalizer->supportsNormalization(UnitEnumDummy::GET));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize()
    {
        $this->assertSame('GET', $this->normalizer->normalize(UnitEnumDummy::GET));
    }

    public function testNormalizeBadObjectTypeThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->normalizer->normalize(new \stdClass());
    }

    public function testSupportsDenormalization()
    {
        $this->assertFalse($this->normalizer->supportsDenormalization(null, StringBackedEnumDummy::class));
        $this->assertFalse($this->normalizer->supportsDenormalization(null, IntegerBackedEnumDummy::class));
        $this->assertTrue($this->normalizer->supportsDenormalization(null, UnitEnumDummy::class));
        $this->assertFalse($this->normalizer->supportsDenormalization(null, \stdClass::class));
    }

    public function testDenormalize()
    {
        $this->assertSame(
            UnitEnumDummy::GET,
            $this->normalizer->denormalize('GET', UnitEnumDummy::class)
        );
    }

    public function testDenormalizeNullValueThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(null, UnitEnumDummy::class);
    }

    public function testDenormalizeBooleanValueThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(true, UnitEnumDummy::class);
    }

    public function testDenormalizeObjectThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(new \stdClass(), UnitEnumDummy::class);
    }

    public function testDenormalizeBadCaseThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The data must belong to an enumeration of type '.UnitEnumDummy::class);

        $this->normalizer->denormalize('POST', UnitEnumDummy::class);
    }

    public function testNormalizeShouldThrowExceptionForNonEnumObjects()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The data must belong to an enumeration.');

        $this->normalizer->normalize(\stdClass::class);
    }

    public function testNormalizeShouldThrowExceptionForBackedEnumObjects()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The data must belong to a non-backed enumeration, or pass');

        $this->normalizer->normalize(StringBackedEnumDummy::GET);
    }

    public function testDenormalizeShouldThrowExceptionForNonEnumObjects()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The data must belong to an enumeration.');

        $this->normalizer->denormalize('GET', \stdClass::class);
    }

    public function testDenormalizeShouldThrowExceptionForBackedEnumObjects()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The data must belong to a non-backed enumeration, or pass');

        $this->normalizer->denormalize('GET', StringBackedEnumDummy::class);
    }

    public function testSupportsNormalizationShouldFailOnAnyPHPVersionForNonEnumObjects()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    /**
     * @dataProvider providerInvalidCases
     *
     * @return void
     */
    public function testItProducesNullForInvalidCasesIfContextIsPassed($normalized)
    {
        $this->assertNull(
            $this->normalizer->denormalize(
                $normalized,
                UnitEnumDummy::class,
                null,
                [BackedEnumNormalizer::ALLOW_INVALID_VALUES => true]
            )
        );
    }

    public function testItProducesEnumForValidCasesIfContextIsPassed()
    {
        $this->assertSame(
            UnitEnumDummy::GET,
            $this->normalizer->denormalize(
                'GET',
                UnitEnumDummy::class,
                null,
                [BackedEnumNormalizer::ALLOW_INVALID_VALUES => true]
            )
        );
    }

    public function testItCanNormalizeBackedEnumsIfContextIsPassed()
    {
        $this->assertSame('SUCCESS', $this->normalizer->normalize(IntegerBackedEnumDummy::SUCCESS, null, [ UnitEnumNormalizer::BACKED_ENUM_PREFER_NAMES => true ]));
        $this->assertSame('GET', $this->normalizer->normalize(StringBackedEnumDummy::GET, null, [ UnitEnumNormalizer::BACKED_ENUM_PREFER_NAMES => true ]));
    }

    public function testItCanDenormalizeBackedEnumsIfContextIsPassed()
    {
        $this->assertSame(
            IntegerBackedEnumDummy::SUCCESS,
            $this->normalizer->denormalize('SUCCESS', IntegerBackedEnumDummy::class, null, [ UnitEnumNormalizer::BACKED_ENUM_PREFER_NAMES => true ])
        );
        $this->assertSame(
            StringBackedEnumDummy::GET,
            $this->normalizer->denormalize('GET', StringBackedEnumDummy::class, null, [ UnitEnumNormalizer::BACKED_ENUM_PREFER_NAMES => true ])
        );
    }

    protected function providerInvalidCases()
    {
        return [
            'integer' => [1],
            'empty string' => [''],
            'non-existing case' => ['WRONG'],
            'null' => [null],
        ];
    }
}
