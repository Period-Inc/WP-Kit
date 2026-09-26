<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress\ThemeResolution;

use InvalidArgumentException;

final class ThemePreviewTarget
{
    public function __construct(
        private string $id,
        private string $label,
        private ThemeTarget $target,
    ) {
        if ($id === '' || !preg_match('/^[A-Za-z0-9_.:-]+$/', $id)) {
            throw new InvalidArgumentException('Theme preview target id is invalid.');
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException('Theme preview target label must not be empty.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function target(): ThemeTarget
    {
        return $this->target;
    }
}
