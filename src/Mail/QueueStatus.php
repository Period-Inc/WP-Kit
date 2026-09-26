<?php

declare(strict_types=1);

namespace Period\WpKit\Mail;

final class QueueStatus
{
    public const QUEUED = 'queued';
    public const PROCESSING = 'processing';
    public const RETRY_WAIT = 'retry_wait';
    public const SUBMITTED = 'submitted';
    public const DEAD = 'dead';
    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [
            self::QUEUED,
            self::PROCESSING,
            self::RETRY_WAIT,
            self::SUBMITTED,
            self::DEAD,
            self::CANCELLED,
        ];
    }

    private function __construct()
    {
    }
}
