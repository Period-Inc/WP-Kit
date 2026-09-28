<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress\Mail;

final class WpMailContext
{
    private static int $suppressionDepth = 0;

    public function isInterceptionSuppressed(): bool
    {
        return self::$suppressionDepth > 0;
    }

    public function withoutInterception(callable $callback): mixed
    {
        self::$suppressionDepth++;

        try {
            return $callback();
        } finally {
            self::$suppressionDepth--;
        }
    }
}
