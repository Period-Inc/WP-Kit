<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress\ThemeResolution;

use InvalidArgumentException;

final class ThemeTarget
{
    private string $template;
    private string $stylesheet;
    private string $settingsStylesheet;

    public function __construct(
        string $template,
        string $stylesheet,
        ?string $settingsStylesheet = null,
    ) {
        self::assertIdentifier($template, 'template');
        self::assertIdentifier($stylesheet, 'stylesheet');

        $settingsStylesheet = $settingsStylesheet ?? $stylesheet;
        self::assertIdentifier($settingsStylesheet, 'settingsStylesheet');

        $this->template = $template;
        $this->stylesheet = $stylesheet;
        $this->settingsStylesheet = $settingsStylesheet;
    }

    public function template(): string
    {
        return $this->template;
    }

    public function stylesheet(): string
    {
        return $this->stylesheet;
    }

    public function settingsStylesheet(): string
    {
        return $this->settingsStylesheet;
    }

    private static function assertIdentifier(string $value, string $name): void
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            throw new InvalidArgumentException(
                sprintf('%s must be a WordPress theme identifier, got "%s".', $name, $value)
            );
        }
    }
}
