<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\TwoFactor\tests\Support\FakeTwoFactorMethod;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodRegistry;

final class TwoFactorMethodRegistryTest extends TestCase
{
    public static function getDefaultProvider(): iterable
    {
        yield 'returns first available' => [
            [new FakeTwoFactorMethod(name: 'email'), new FakeTwoFactorMethod(name: 'totp')],
            'email',
        ];
        yield 'skips unavailable methods' => [
            [new FakeTwoFactorMethod(name: 'unavailable', available: false), new FakeTwoFactorMethod(name: 'email')],
            'email',
        ];
    }

    public static function hasMethodProvider(): iterable
    {
        yield 'registered method' => ['email', true];
        yield 'unregistered method' => ['unavailable', false];
        yield 'null' => [null, false];
    }

    public function testConstructorKeysMethodsByNameAndGetReturnsThem(): void
    {
        $email = new FakeTwoFactorMethod(name: 'email');
        $totp = new FakeTwoFactorMethod(name: 'totp');
        $registry = new TwoFactorMethodRegistry([$email, $totp]);

        self::assertSame($email, $registry->get('email'));
        self::assertSame($totp, $registry->get('totp'));
    }

    public function testGetAvailableOnlyReturnsAvailableMethodsInRegistrationOrder(): void
    {
        $registry = new TwoFactorMethodRegistry([
            new FakeTwoFactorMethod(name: 'email'),
            new FakeTwoFactorMethod(name: 'unavailable', available: false),
            new FakeTwoFactorMethod(name: 'totp'),
        ]);

        self::assertSame(['email', 'totp'], array_map(
            static fn(FakeTwoFactorMethod $method): string => $method->getName(),
            $registry->getAvailable(),
        ));
    }

    #[DataProvider('getDefaultProvider')]
    public function testGetDefault(array $methods, string $expectedName): void
    {
        $registry = new TwoFactorMethodRegistry($methods);
        self::assertSame($expectedName, $registry->getDefault()->getName());
    }

    public function testGetDefaultThrowsWhenNoMethodIsAvailable(): void
    {
        $registry = new TwoFactorMethodRegistry([
            new FakeTwoFactorMethod(name: 'unavailable', available: false),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No two-factor authentication methods are available.');
        $registry->getDefault();
    }

    public function testGetThrowsForUnknownMethod(): void
    {
        $registry = new TwoFactorMethodRegistry([new FakeTwoFactorMethod(name: 'email')]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown two-factor method "unknown".');
        $registry->get('unknown');
    }

    #[DataProvider('hasMethodProvider')]
    public function testHas(?string $name, bool $expected): void
    {
        $registry = new TwoFactorMethodRegistry([new FakeTwoFactorMethod(name: 'email')]);

        self::assertSame($expected, $registry->has($name));
    }

    public function testHasAvailable(): void
    {
        self::assertTrue((new TwoFactorMethodRegistry([new FakeTwoFactorMethod(name: 'email')]))->hasAvailable());
        self::assertFalse((new TwoFactorMethodRegistry())->hasAvailable());
        self::assertFalse(
            (new TwoFactorMethodRegistry([new FakeTwoFactorMethod(name: 'email', available: false)]))->hasAvailable(),
        );
    }
}
