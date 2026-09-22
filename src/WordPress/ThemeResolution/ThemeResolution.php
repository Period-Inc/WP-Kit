<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress\ThemeResolution;

final class ThemeResolution
{
    public function __construct(
        private ThemeTarget $target,
        private string $ruleId,
        private int $priority,
    ) {
    }

    public function target(): ThemeTarget
    {
        return $this->target;
    }

    public function ruleId(): string
    {
        return $this->ruleId;
    }

    public function priority(): int
    {
        return $this->priority;
    }
}
