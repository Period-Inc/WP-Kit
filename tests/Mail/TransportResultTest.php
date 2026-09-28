<?php

declare(strict_types=1);

namespace Period\WpKit\Tests\Mail;

use Period\WpKit\Mail\TransportResult;
use PHPUnit\Framework\TestCase;

final class TransportResultTest extends TestCase
{
    public function testAcceptedResult(): void
    {
        $result = TransportResult::accepted('provider-123', ['status' => 202]);

        self::assertTrue($result->isAccepted());
        self::assertFalse($result->isRetryable());
        self::assertSame('provider-123', $result->providerMessageId());
        self::assertSame(['status' => 202], $result->metadata());
    }

    public function testFailedResult(): void
    {
        $result = TransportResult::failed('timeout', 'Timed out', true);

        self::assertFalse($result->isAccepted());
        self::assertTrue($result->isRetryable());
        self::assertSame('timeout', $result->errorCode());
        self::assertSame('Timed out', $result->errorMessage());
    }
}
