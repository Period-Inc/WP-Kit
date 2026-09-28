<?php

declare(strict_types=1);

namespace Period\WpKit\Mail\Contract;

use Period\WpKit\Mail\MailMessage;
use Period\WpKit\Mail\TransportResult;

interface MailTransportInterface
{
    public function name(): string;

    public function isAvailable(): bool;

    public function send(MailMessage $message): TransportResult;
}
