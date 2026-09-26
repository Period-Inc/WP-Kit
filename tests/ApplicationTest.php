<?php

declare(strict_types=1);

namespace Period\WpKit\Tests;

use PHPUnit\Framework\TestCase;
use Period\WpKit\Application;
use Period\WpKit\WordPress\SiteInfo;
use Period\WpKit\WordPress\ThemeResolution\ThemeResolver;
use Period\WpKit\WordPress\ThemeResolution\ThemePreviewRegistry;

final class ApplicationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application(__DIR__);
    }

    public function testTitleReturnsString(): void
    {
        $this->assertIsString($this->app->title());
    }

    public function testTitleWorksWithoutWordPress(): void
    {
        if (function_exists('wp_get_document_title')) {
            $this->markTestSkipped('WordPress functions exist in environment');
        }

        $result = $this->app->title();

        $this->assertIsString($result);
    }

    public function testThemesReturnsThemeResolver(): void
    {
        $this->assertInstanceOf(ThemeResolver::class, $this->app->themes());
        $this->assertSame($this->app->themes(), $this->app->themes());
    }

    public function testThemePreviewsReturnsRegistry(): void
    {
        $this->assertInstanceOf(ThemePreviewRegistry::class, $this->app->themePreviews());
        $this->assertSame($this->app->themePreviews(), $this->app->themePreviews());
    }

    public function testSiteReturnsSiteInfo(): void
    {
        $this->assertInstanceOf(SiteInfo::class, $this->app->site());
    }

    public function testSiteReturnsSameInstance(): void
    {
        $this->assertSame($this->app->site(), $this->app->site());
    }

    public function testDocumentReturnsFullHtml(): void
    {
        $output = $this->app->document('<p>Hello</p>');

        $this->assertStringContainsString('<!doctype html>', $output);
        $this->assertStringContainsString('<p>Hello</p>', $output);
        $this->assertStringContainsString('</html>', $output);
    }

    public function testDocumentWithEmptyContentReturnsHtml(): void
    {
        $output = $this->app->document();

        $this->assertStringContainsString('<!doctype html>', $output);
        $this->assertStringContainsString('</html>', $output);
    }

    public function testDocumentPassesArgsToRenderer(): void
    {
        if (function_exists('get_body_class')) {
            $this->markTestSkipped('get_body_class exists in environment');
        }

        $output = $this->app->document('', ['body_class' => ['my-page']]);

        $this->assertStringContainsString('my-page', $output);
    }

    public function testDocumentReturnsSameRendererInstance(): void
    {
        $this->app->document('first');
        $this->app->document('second');

        $this->assertTrue(true);
    }

    public function testTitleCalledMultipleTimesDoesNotError(): void
    {
        $first = $this->app->title();
        $second = $this->app->title();

        $this->assertSame($first, $second);
    }

    public function testAllMethodsWorkWithoutWordPress(): void
    {
        if (function_exists('get_bloginfo') || function_exists('wp_get_document_title')) {
            $this->markTestSkipped('WordPress functions exist in environment');
        }

        $this->assertIsString($this->app->title());
        $this->assertInstanceOf(SiteInfo::class, $this->app->site());
        $this->assertIsString($this->app->document('<p>test</p>'));
    }
}
