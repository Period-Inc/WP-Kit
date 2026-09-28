<?php

declare(strict_types=1);

namespace Period\WpKit\Mail\Retry;

use InvalidArgumentException;
use Period\WpKit\Mail\Contract\RetryPolicyInterface;
use Period\WpKit\Mail\TransportResult;

final class DefaultRetryPolicy implements RetryPolicyInterface
{
    /** @var list<int> */
    private array $delays;

    public function __construct(array $delays = [60, 300, 1800, 7200, 21600])
    {
        $delays = array_values($delays);

        foreach ($delays as $delay) {
            if (!is_int($delay) || $delay < 0) {
                throw new InvalidArgumentException('Retry delays must contain only non-negative integers.');
            }
        }

        $this->delays = $delays;
    }

    public function delayForAttempt(int $attempt, TransportResult $result): ?int
    {
        if ($attempt < 1) {
            throw new InvalidArgumentException('Retry attempt must be 1 or greater.');
        }

        if (!$result->isRetryable() || $result->isAccepted()) {
            return null;
        }

        return $this->delays[$attempt - 1] ?? null;
    }
}
