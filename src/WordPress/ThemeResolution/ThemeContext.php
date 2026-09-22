<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress\ThemeResolution;

final class ThemeContext
{
    public function __construct(private array $values = [])
    {
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->values)
            ? $this->values[$key]
            : $default;
    }

    public function with(string $key, mixed $value): self
    {
        $values = $this->values;
        $values[$key] = $value;

        return new self($values);
    }

    public function all(): array
    {
        return $this->values;
    }
}
