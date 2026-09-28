<?php

declare(strict_types=1);

namespace Period\WpKit\Tests\Mail;

use InvalidArgumentException;
use Period\WpKit\Mail\Retry\DefaultRetryPolicy;
use Period\WpKit\Mail\TransportResult;
use PHPUnit\Framework\TestCase;

final class DefaultRetryPolicyTest extends TestCase
{
    public function testReturnsConfiguredDelayForRetryableFailure(): void
    {
        $policy = new DefaultRetryPolicy([10, 20]);
        $result = TransportResult::failed('temporary', 'Try again', true);

        self::assertSame(10, $policy->delayForAttempt(1, $result));
        self::assertSame(20, $policy->delayForAttempt(2, $result));
        self::assertNull($policy->delayForAttempt(3, $result));
    }

    public function testPermanentFailureIsNotRetried(): void
    {
        $policy = new DefaultRetryPolicy();
        $result = TransportResult::failed('invalid_recipient', 'Invalid recipient', false);

        self::assertNull($policy->delayForAttempt(1, $result));
    }

    public function testAcceptedResultIsNotRetried(): void
    {
        $policy = new DefaultRetryPolicy();

        self::assertNull($policy->delayForAttempt(1, TransportResult::accepted()));
    }

    public function testRejectsInvalidAttemptNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DefaultRetryPolicy())->delayForAttempt(0, TransportResult::failed(null, 'error'));
    }
}
