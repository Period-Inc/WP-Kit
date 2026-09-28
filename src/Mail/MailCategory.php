<?php

declare(strict_types=1);

namespace Period\WpKit\Mail;

final class MailCategory
{
    public const TRANSACTIONAL = 'transactional';
    public const NOTIFICATION = 'notification';
    public const BROADCAST = 'broadcast';
    public const SYSTEM = 'system';
    public const UNCATEGORIZED = 'uncategorized';

    private function __construct()
    {
    }
}
