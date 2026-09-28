<?php

declare(strict_types=1);

namespace Period\WpKit\Tests\WordPress\ThemeResolution;

use Period\WpKit\WordPress\ThemeResolution\ThemeContext;
use Period\WpKit\WordPress\ThemeResolution\ThemePreviewContextProvider;
use Period\WpKit\WordPress\ThemeResolution\ThemePreviewRegistry;
use Period\WpKit\WordPress\ThemeResolution\ThemeTarget;
use PHPUnit\Framework\TestCase;

final class ThemePreviewContextProviderTest extends TestCase
{
    public function testKnownExternalIdIsAddedToContext(): void
    {
        $registry = new ThemePreviewRegistry();
        $registry->register(
            'slot-01',
            'Slot 01',
            new ThemeTarget('astra', 'purimall-slot-01', 'purimall')
        );

        $provider = new ThemePreviewContextProvider(
            $registry,
            static fn (): ?string => 'slot-01'
        );

        $context = $provider->context(new ThemeContext(['user.id' => 42]));

        self::assertSame('slot-01', $context->get('preview.theme'));
        self::assertSame(42, $context->get('user.id'));
    }

    public function testMissingOrUnknownExternalIdFallsBackWithoutThemeOverride(): void
    {
        $registry = new ThemePreviewRegistry();
        $base = new ThemeContext(['user.id' => 42]);

        $missing = new ThemePreviewContextProvider(
            $registry,
            static fn (): ?string => null
        );
        self::assertSame($base->all(), $missing->context($base)->all());

        $unknown = new ThemePreviewContextProvider(
            $registry,
            static fn (): ?string => 'slot-unknown'
        );
        self::assertSame($base->all(), $unknown->context($base)->all());
    }
}
