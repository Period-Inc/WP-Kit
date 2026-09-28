<?php

declare(strict_types=1);

namespace Period\WpKit\Tests\Mail;

use InvalidArgumentException;
use Period\WpKit\Mail\MailAddress;
use PHPUnit\Framework\TestCase;

final class MailAddressTest extends TestCase
{
    public function testStoresEmailAndName(): void
    {
        $address = new MailAddress('user@example.com', 'Example User');

        self::assertSame('user@example.com', $address->email());
        self::assertSame('Example User', $address->name());
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MailAddress('not-an-email');
    }

    public function testRejectsLineBreakInName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MailAddress('user@example.com', "User\r\nBcc: attacker@example.com");
    }
}
