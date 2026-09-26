<?php

declare(strict_types=1);

namespace Period\WpKit\Mail\Contract;

use DateTimeImmutable;
use Period\WpKit\Mail\MailMessage;

interface MailQueueInterface
{
    public function enqueue(MailMessage $message, ?DateTimeImmutable $sendAt = null): string;
}
