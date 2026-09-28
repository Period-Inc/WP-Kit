<?php

declare(strict_types=1);

namespace Period\WpKit\Mail;

final class DeliveryStatus
{
    public const UNKNOWN = 'unknown';
    public const DELIVERED = 'delivered';
    public const BOUNCED = 'bounced';
    public const COMPLAINED = 'complained';

    public static function all(): array
    {
        return [
            self::UNKNOWN,
            self::DELIVERED,
            self::BOUNCED,
            self::COMPLAINED,
        ];
    }

    private function __construct()
    {
    }
}
