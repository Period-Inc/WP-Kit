<?php

declare(strict_types=1);

namespace Period\WpKit\Tests\WordPress\ThemeResolution;

use Period\WpKit\WordPress\ThemeResolution\ThemeContext;
use Period\WpKit\WordPress\ThemeResolution\ThemeResolutionRuntime;
use Period\WpKit\WordPress\ThemeResolution\ThemeResolver;
use Period\WpKit\WordPress\ThemeResolution\ThemeTarget;
use PHPUnit\Framework\TestCase;

final class ThemeResolutionRuntimeTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        global $PERIOD_WP_OPTIONS, $PERIOD_WP_CUSTOM_CSS_POSTS;
        $PERIOD_WP_OPTIONS = [];
        $PERIOD_WP_CUSTOM_CSS_POSTS = [];
    }

    public function testRuntimeAppliesTemplateAndStylesheetWithoutPersistingOptions(): void
    {
        $resolver = new ThemeResolver();
        $resolver->addRule(
            'preview',
            fn (ThemeContext $context) => new ThemeTarget('astra', 'purimall-slot-01', 'purimall'),
            1000
        );

        $runtime = new ThemeResolutionRuntime($resolver, fn () => new ThemeContext());

        self::assertSame('astra', $runtime->filterTemplate(false));
        self::assertSame('purimall-slot-01', $runtime->filterStylesheet(false));
    }

    public function testRuntimeFallsBackToNativeValuesWhenNothingResolves(): void
    {
        $resolver = new ThemeResolver();
        $resolver->addRule('none', fn (ThemeContext $context) => null);

        $runtime = new ThemeResolutionRuntime($resolver, fn () => new ThemeContext());

        self::assertSame('native-template', $runtime->filterTemplate('native-template'));
        self::assertSame('native-stylesheet', $runtime->filterStylesheet('native-stylesheet'));
    }

    public function testRuntimeReusesBaseThemeModsForAlternateTree(): void
    {
        global $PERIOD_WP_OPTIONS;
        $PERIOD_WP_OPTIONS = [
            'theme_mods_purimall' => ['custom_logo' => 123],
        ];

        $resolver = new ThemeResolver();
        $resolver->addRule(
            'preview',
            fn (ThemeContext $context) => new ThemeTarget('astra', 'purimall-slot-01', 'purimall')
        );

        $runtime = new ThemeResolutionRuntime($resolver, fn () => new ThemeContext());

        self::assertSame(
            ['custom_logo' => 123],
            $runtime->filterOption(false, 'theme_mods_purimall-slot-01')
        );
        self::assertFalse($runtime->filterOption(false, 'unrelated_option'));
    }

    public function testRuntimeReusesBaseCustomCssForAlternateTree(): void
    {
        global $PERIOD_WP_CUSTOM_CSS_POSTS;
        $PERIOD_WP_CUSTOM_CSS_POSTS = [
            'purimall' => (object) ['post_content' => '.example { display: block; }'],
        ];

        $resolver = new ThemeResolver();
        $resolver->addRule(
            'preview',
            fn (ThemeContext $context) => new ThemeTarget('astra', 'purimall-slot-01', 'purimall')
        );

        $runtime = new ThemeResolutionRuntime($resolver, fn () => new ThemeContext());

        self::assertSame(
            '.example { display: block; }',
            $runtime->filterCustomCss('', 'purimall-slot-01')
        );
        self::assertSame(
            'native',
            $runtime->filterCustomCss('native', 'another-theme')
        );
    }

    public function testContextFactoryReentryFallsBackToNativeOptionProcessing(): void
    {
        $resolver = new ThemeResolver();
        $resolver->addRule('preview', fn (ThemeContext $context) => new ThemeTarget('astra', 'preview'));

        $runtime = null;
        $nested = null;

        $runtime = new ThemeResolutionRuntime(
            $resolver,
            function () use (&$runtime, &$nested): ThemeContext {
                $nested = $runtime->filterOption('native', 'some_option');
                return new ThemeContext();
            }
        );

        self::assertSame('astra', $runtime->filterTemplate(false));
        self::assertSame('native', $nested);
    }

    public function testContextIsResolvedOnlyOncePerRequest(): void
    {
        $calls = 0;
        $resolver = new ThemeResolver();
        $resolver->addRule('preview', fn (ThemeContext $context) => new ThemeTarget('astra', 'preview'));

        $runtime = new ThemeResolutionRuntime(
            $resolver,
            function () use (&$calls): ThemeContext {
                $calls++;
                return new ThemeContext();
            }
        );

        $runtime->filterTemplate(false);
        $runtime->filterStylesheet(false);
        $runtime->filterOption(false, 'theme_mods_preview');

        self::assertSame(1, $calls);
    }
}
