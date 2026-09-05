<?php

declare(strict_types=1);

namespace Period\WpKit;

use Period\WpKit\Infrastructure\ShortcodeRegistrar;
use Period\WpKit\Infrastructure\Shortcode\ButtonShortcode;
use Period\WpKit\Infrastructure\Shortcode\FetchTitleShortcode;
use Period\WpKit\Infrastructure\Shortcode\TemplateUrlShortcode;
use Period\WpKit\WordPress\NavMenuClassEnhancer;
use Period\WpKit\WordPress\PostClassEnhancer;
use Period\WpKit\WordPress\PostTypeRegistrar;
use Period\WpKit\WordPress\ScriptStyleRegistrar;
use Period\WpKit\WordPress\DocumentRenderer;
use Period\WpKit\WordPress\MediaReferenceIndex;
use Period\WpKit\WordPress\SiteInfo;
use Period\WpKit\WordPress\TitleResolver;
use Period\WpKit\WordPress\Translator;
use Period\WpKit\Support\ArgsResolver;
use Period\WpKit\View\Renderer;

final class Application
{
    private string $basePath;
    private ArgsResolver $argsResolver;
    private Renderer $renderer;
    private ScriptStyleRegistrar $assets;
    private PostTypeRegistrar $posts;
    private ?Translator $translator = null;
    private ?SiteInfo $siteInfo = null;
    private ?TitleResolver $titleResolver = null;
    private ?DocumentRenderer $documentRenderer = null;
    private bool $booted = false;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->argsResolver = new ArgsResolver();
        $this->renderer = new Renderer($this->basePath . '/templates');
        $this->assets = new ScriptStyleRegistrar($this->basePath);
        $this->posts = new PostTypeRegistrar();
    }

    public function assets(): ScriptStyleRegistrar
    {
        return $this->assets;
    }

    public function posts(): PostTypeRegistrar
    {
        return $this->posts;
    }

    public function mediaReferenceIndex(array $config = []): MediaReferenceIndex
    {
        return new MediaReferenceIndex($config);
    }

    public function translator(): Translator
    {
        if ($this->translator === null) {
            $this->translator = new Translator();
        }

        return $this->translator;
    }

    public function title(): string
    {
        if ($this->titleResolver === null) {
            $this->titleResolver = new TitleResolver($this->site());
        }

        return $this->titleResolver->siteTitle();
    }

    public function site(): SiteInfo
    {
        if ($this->siteInfo === null) {
            $this->siteInfo = new SiteInfo();
        }

        return $this->siteInfo;
    }

    public function document(string $content = '', array $args = []): string
    {
        if ($this->documentRenderer === null) {
            $this->documentRenderer = new DocumentRenderer();
        }

        return $this->documentRenderer->render($content, $args);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $shortcodes = [
            new ButtonShortcode($this),
            new FetchTitleShortcode(),
            new TemplateUrlShortcode(),
        ];

        (new ShortcodeRegistrar($shortcodes))->register();
        (new NavMenuClassEnhancer())->register();
        (new PostClassEnhancer())->register();

        $this->booted = true;
    }

    public function button(array $args = []): string
    {
        $args = $this->argsResolver->resolve($args, [
            'label' => 'Button',
            'url' => '',
            'class' => '',
        ]);

        return $this->renderer->render('button', [
            'label' => (string) $args['label'],
            'url' => (string) $args['url'],
            'class' => trim('period-wp-button ' . (string) $args['class']),
        ]);
    }

    public function basePath(): string
    {
        return $this->basePath;
    }
}
