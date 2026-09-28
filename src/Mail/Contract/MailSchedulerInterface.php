<?php

declare(strict_types=1);

namespace Period\WpKit\Mail\Contract;

use DateTimeImmutable;

interface MailSchedulerInterface
{
    public function schedule(string $mailId, DateTimeImmutable $sendAt): void;
}
