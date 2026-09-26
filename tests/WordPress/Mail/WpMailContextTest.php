<?php

declare(strict_types=1);

namespace Period\WpKit\Tests\WordPress\Mail;

use Period\WpKit\WordPress\Mail\WpMailContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WpMailContextTest extends TestCase
{
    public function testSuppressesInterceptionOnlyInsideCallback(): void
    {
        $context = new WpMailContext();

        self::assertFalse($context->isInterceptionSuppressed());
        $value = $context->withoutInterception(function () use ($context): string {
            self::assertTrue($context->isInterceptionSuppressed());
            return 'ok';
        });
        self::assertSame('ok', $value);
        self::assertFalse($context->isInterceptionSuppressed());
    }

    public function testRestoresContextAfterException(): void
    {
        $context = new WpMailContext();

        try {
            $context->withoutInterception(static function (): void {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
        }

        self::assertFalse($context->isInterceptionSuppressed());
    }
}
