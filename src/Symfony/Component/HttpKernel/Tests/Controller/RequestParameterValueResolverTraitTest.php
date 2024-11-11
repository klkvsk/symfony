<?php

namespace Symfony\Component\HttpKernel\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\FromBody;
use Symfony\Component\HttpKernel\Attribute\FromFile;
use Symfony\Component\HttpKernel\Attribute\FromHeader;
use Symfony\Component\HttpKernel\Attribute\MapDateTime;
use Symfony\Component\HttpKernel\Attribute\FromQuery;
use Symfony\Component\HttpKernel\Attribute\FromRoute;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\RequestParameterValueResolverTrait;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Tests\Fixtures\Suit;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Validator\Constraints\DateTime;

class RequestParameterValueResolverTraitTest extends TestCase
{
    protected ArgumentResolverInterface $argumentResolver;

    public static function intValues(): array
    {
        return [
            '123'            => [ 123, 123 ],
            '"123"'          => [ 123, '123' ],
            '(empty string)' => [ null, '' ],
        ];
    }

    public static function floatValues(): array
    {
        return [
            '1.23'            => [ 1.23, 1.23 ],
            '"1.23"'          => [ 1.23, '1.23' ],
            '(empty string)'  => [ null, '' ],
        ];
    }

    public static function boolValues(): array
    {
        return [
            '"true"'         => [ true, 'true' ],
            '"1"'            => [ true, '1' ],
            '"on"'           => [ true, 'on' ],
            '"false"'        => [ false, 'false' ],
            '"off"'          => [ false, 'off' ],
            '"0"'            => [ false, '0' ],
            '(empty string)' => [ false, '' ],
        ];
    }

    public function testStringDefault()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(attributes: [ 'foo' => 'bar' ]),
            fn(string $foo) => $foo,
        );
        $this->assertEquals([ 'bar' ], $args);
    }

    public function testIgnoreWhenNoAttributes()
    {
        $this->expectExceptionMessage('requires the "$foo"');
        $this->argumentResolver->getArguments(
            new Request(query: [ 'foo' => 'bar' ]),
            fn(string $foo) => $foo,
        );

        $this->expectExceptionMessage('requires the "$foo"');
        $this->argumentResolver->getArguments(
            new Request(request: [ 'foo' => 'bar' ]),
            fn(string $foo) => $foo,
        );
    }

    public function testStringFromRoute()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(attributes: [ 'foo' => 'bar' ]),
            fn(#[FromRoute] string $foo) => $foo,
        );
        $this->assertEquals([ 'bar' ], $args);
    }

    public function testStringFromQuery()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'foo' => 'bar' ]),
            fn(#[FromQuery] string $foo) => $foo,
        );
        $this->assertEquals([ 'bar' ], $args);

    }

    public function testStringFromBody()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(request: [ 'foo' => 'bar' ]),
            fn(#[FromBody] string $foo) => $foo,
        );
        $this->assertEquals([ 'bar' ], $args);
    }

    public function testStringFromJsonBody()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(server: [ 'HTTP_CONTENT_TYPE' => 'application/json' ], content: json_encode([ 'foo' => 'bar' ])),
            fn(#[FromBody] string $foo) => $foo,
        );
        $this->assertEquals([ 'bar' ], $args);
    }

    public function testStringFromHeader()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(server: [ 'HTTP_FOO' => 'bar' ]),
            fn(#[FromHeader] string $foo) => $foo
        );

        $this->assertEquals([ 'bar' ], $args);
    }

    public function testStringFromQueryRenamed()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'theFoo' => 'bar' ]),
            fn(#[FromQuery('theFoo')] string $foo) => $foo
        );

        $this->assertSame([ 'bar' ], $args);
    }

    public function testWholeBagAsParameter()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'sort' => 'name', 'order' => 'desc' ]),
            fn(#[FromQuery('*')] array $sorting) => $sorting
        );

        $this->assertSame([ [ 'sort' => 'name', 'order' => 'desc' ] ], $args);
    }

    public function testStringFromHeaderRenamed()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(server: [ 'HTTP_X_FORWARDED_FOR' => 'bar' ]),
            fn(#[FromHeader('x-forwarded-for')] string $foo) => $foo,
        );
        $this->assertEquals([ 'bar' ], $args);
    }

    public function testFromFile()
    {
        $file = __DIR__.'/../Fixtures/Controller/ArgumentResolver/UploadedFile/file-small.txt';
        $request = new Request(files: [
            'attach' => [
                'name' => 'file-small.txt',
                'type' => 'text/plain',
                'tmp_name' => $file,
                'size' => filesize($file),
                'error' => UPLOAD_ERR_OK,
            ]
        ]);

        $args = $this->argumentResolver->getArguments(
            $request,
            fn(#[FromFile] UploadedFile $attach) => $attach,
        );
        $this->assertCount(1, $args);
        $this->assertInstanceOf(UploadedFile::class, $args[0]);
        $this->assertSame('file-small.txt', $args[0]->getClientOriginalName());
    }

    public function testInt()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'page' => '1' ]),
            fn(#[FromQuery] int $page) => $page,
        );
        $this->assertEquals([ 1 ], $args);

        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'page' => '' ]),
            fn(#[FromQuery] ?int $page) => $page,
        );
        $this->assertEquals([ null ], $args);
    }

    public function testValidationFailed()
    {
        $this->expectException(BadRequestHttpException::class);
        $this->argumentResolver->getArguments(
            new Request(query: [ 'page' => 'not-an-integer' ]),
            fn(#[FromQuery()] ?int $page) => $page,
        );
    }

    public function testEmptyStringAsNull()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'page' => '' ]),
            fn(#[FromQuery] ?int $page) => $page,
        );
        $this->assertEquals([ null ], $args);

        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'weight' => '' ]),
            fn(#[FromQuery] ?float $weight) => $weight,
        );
        $this->assertEquals([ null ], $args);

        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'title' => '' ]),
            fn(#[FromQuery] ?int $title) => $title,
        );
        $this->assertEquals([ null ], $args);
    }

    public function testEmptyStringAsNullDisabled()
    {
        $this->expectException(BadRequestHttpException::class);
        $this->argumentResolver->getArguments(
            new Request(query: [ 'page' => '' ]),
            fn(#[FromQuery(options: null)] ?int $page) => $page,
        );
    }

    public function testMultipleAttributesDisallowed()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Multiple');
        $this->argumentResolver->getArguments(
            new Request(query: [ 'page' => 1 ], request: [ 'page' => 2 ]),
            fn(#[FromQuery] #[FromBody] ?int $page) => $page,
        );
    }

    /** @dataProvider intValues */
    public function testIntFromQuery($expected, $actual)
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'int' => $actual ]),
            fn(#[FromQuery] ?int $int) => $int,
        );
        $this->assertEquals([ $expected ], $args);
    }

    /** @dataProvider floatValues */
    public function testFloatFromQuery($expected, $actual)
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'float' => $actual ]),
            fn(#[FromQuery] ?float $float) => $float,
        );
        $this->assertEquals([ $expected ], $args);
    }

    public function testFilterFlags()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'int' => '0xff' ]),
            fn(#[FromQuery(throwOnFilterFailure: false)] ?int $int) => $int,
        );
        $this->assertEquals([ null ], $args);

        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'int' => '0xff' ]),
            fn(#[FromQuery(options: FILTER_FLAG_ALLOW_HEX)] ?int $int) => $int,
        );
        $this->assertEquals([ 255 ], $args);

        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'int' => '0xff' ]),
            fn(#[FromQuery(options: [ 'flags' => FILTER_FLAG_ALLOW_HEX ])] ?int $int) => $int,
        );
        $this->assertEquals([ 255 ], $args);
    }

    public function testFilterOptions()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'int' => 42 ]),
            fn(#[FromQuery] ?int $int) => $int,
        );
        $this->assertEquals([ 42 ], $args);

        $this->expectException(BadRequestHttpException::class);
        $this->argumentResolver->getArguments(
            new Request(query: [ 'int' => 42 ]),
            fn(#[FromQuery(options: [ 'max_range' => 10 ])] ?int $int) => $int,
        );
    }

    public function testFilterFlagAndOptions()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'int' => 42 ]),
            fn(#[FromQuery(options: [ 'flags' => FILTER_FLAG_ALLOW_HEX, 'max_range' => 100 ])] ?int $int) => $int,
        );
        $this->assertEquals([ 42 ], $args);

        $this->expectException(BadRequestHttpException::class);
        $this->argumentResolver->getArguments(
            new Request(query: [ 'int' => 42 ]),
            fn(#[FromQuery(options: [ 'flags' => FILTER_FLAG_ALLOW_HEX, 'max_range' => 10 ])] ?int $int) => $int,
        );

    }

    /** @dataProvider boolValues */
    public function testBoolFromQuery($expected, $actual)
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'bool' => $actual ]),
            fn(#[FromQuery] bool $bool) => $bool,
        );
        $this->assertEquals([ $expected ], $args);
    }

    public function testBoolFromQueryMissing()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: []),
            fn(#[FromQuery] ?bool $bool) => $bool,
        );
        $this->assertEquals([ null ], $args);

        $this->expectExceptionMessage('requires the "$bool"');
        $args = $this->argumentResolver->getArguments(
            new Request(query: []),
            fn(#[FromQuery] bool $bool) => $bool,
        );
        $this->assertEquals([ null ], $args);
    }

    public function testUidFromQuery()
    {
        $uid = new UuidV1();

        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'uid' => $uid->toString() ]),
            fn(#[FromQuery] AbstractUid $uid) => $uid,
        );
        $this->assertEquals([ $uid ], $args);
    }

    public function testDateFromQuery()
    {
        $date = new \DateTimeImmutable('2021-01-01 01:02:03');

        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'date' => $date->format(\DateTimeInterface::ATOM) ]),
            fn(#[FromQuery] \DateTimeInterface $date) => $date,
        );
        $this->assertEquals([ $date ], $args);

        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'date' => $date->getTimestamp() ]),
            fn(#[FromQuery] \DateTimeInterface $date) => $date,
        );
        $this->assertEquals([ $date ], $args);
    }

    public function testDateWithMapDateTimeFromQuery()
    {
        $date = new \DateTimeImmutable('2021-12-01 01:02:03');

        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'date' => '2021/01/12 01:02:03' ]),
            fn(#[FromQuery] #[MapDateTime(format: 'Y/d/m H:i:s')] \DateTimeInterface $date) => $date,
        );
        $this->assertEquals([ $date ], $args);
    }

    public function testEnumFromQuery()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'suit' => 'H' ]),
            fn(#[FromQuery] Suit $suit) => $suit,
        );
        $this->assertEquals([ Suit::Hearts ], $args);
    }

    public function testArrayFromQuery()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'foo' => [ '1', '2' ] ]),
            fn(#[FromQuery('foo')] array $foo) => $foo,
        );
        $this->assertSame([ [ '1', '2' ] ], $args);
    }

    public function testVariadic()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'foo' => [ '1', '2' ] ]),
            fn(#[FromQuery('foo')] int ...$foo) => $foo,
        );
        $this->assertSame([ 1, 2 ], $args);

        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'foo' => [ '1', '0' ] ]),
            fn(#[FromQuery('foo')] bool ...$foo) => $foo,
        );
        $this->assertSame([ true, false ], $args);
    }

    public function testVariadicEnum()
    {
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'foo' => [ 'H', 'S' ] ]),
            fn(#[FromQuery('foo')] Suit ...$foo) => $foo,
        );
        $this->assertSame([ Suit::Hearts, Suit::Spades ], $args);
    }

    public function testVariadicUid()
    {
        $uids = [
            new UuidV1(),
            new UuidV1(),
        ];
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'foo' => [ $uids[0]->toString(), $uids[1]->toString() ] ]),
            fn(#[FromQuery('foo')] AbstractUid ...$foo) => $foo,
        );
        $this->assertEquals($uids, $args);
    }

    public function testVariadicDateTime()
    {
        $dates = [
            new \DateTimeImmutable('2021-01-01 01:01:01'),
            new \DateTimeImmutable('2021-02-02 02:02:02'),
        ];
        $args = $this->argumentResolver->getArguments(
            new Request(query: [ 'foo' => [ $dates[0]->getTimestamp(), $dates[1]->format(\DATE_ATOM) ] ]),
            fn(#[FromQuery('foo')] \DateTimeInterface ...$foo) => $foo,
        );
        $this->assertEquals($dates, $args);
    }

    public function testExtraParametersAreKept()
    {
        $resolver = new ArgumentResolver(new ArgumentMetadataFactory(), [ new test_AllBagParametersValueResolver() ]);

        $args = $resolver->getArguments(
            new Request(attributes: [ 'country' => 'XX', 'area' => '42' ]),
            fn ($area) => $area
        );
        $this->assertSame([ [ 'country' => 'XX', 'area' => '42' ] ], $args);

        $args = $resolver->getArguments(
            new Request(attributes: [ 'country' => 'XX', 'area' => '42' ]),
            fn (#[FromRoute] $area) => $area
        );
        $this->assertSame([ [ 'country' => 'XX', 'area' => '42' ] ], $args);

        $args = $resolver->getArguments(
            new Request(query: [ 'country' => 'XX', 'area' => '42' ]),
            fn (#[FromQuery] $area) => $area
        );
        $this->assertSame([ [ 'country' => 'XX', 'area' => '42' ] ], $args);

        $args = $resolver->getArguments(
            new Request(request: [ 'country' => 'XX', 'area' => '42' ]),
            fn (#[FromBody] $area) => $area
        );
        $this->assertSame([ [ 'country' => 'XX', 'area' => '42' ] ], $args);
    }

    protected function setUp(): void
    {
        $this->argumentResolver = new ArgumentResolver(
            new ArgumentMetadataFactory(),
            [
                new ArgumentResolver\BackedEnumValueResolver(),
                new ArgumentResolver\DateTimeValueResolver(),
                new ArgumentResolver\UidValueResolver(),
                ...ArgumentResolver::getDefaultArgumentValueResolvers()
            ]
        );
    }

}

class test_AllBagParametersValueResolver implements ValueResolverInterface {
    use RequestParameterValueResolverTrait;

    public function resolveValue(Request $request, ArgumentMetadata $argument, ParameterBag $valueBag): array
    {
        return [ $valueBag->all() ];
    }

}
