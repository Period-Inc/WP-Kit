<?php

declare(strict_types=1);

namespace Period\WpKit\Mail\Contract;

use Period\WpKit\Mail\TransportResult;

interface RetryPolicyInterface
{
    public function delayForAttempt(int $attempt, TransportResult $result): ?int;
}
