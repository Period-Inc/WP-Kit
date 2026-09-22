<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress\ThemeResolution;

use Closure;

final class ThemeResolutionRuntime
{
    private Closure $contextFactory;
    private bool $resolved = false;
    private bool $resolving = false;
    private ?ThemeResolution $resolution = null;

    public function __construct(
        private ThemeResolver $resolver,
        callable $contextFactory,
    ) {
        $this->contextFactory = Closure::fromCallable($contextFactory);
    }

    public function register(): void
    {
        if (!function_exists('add_filter')) {
            return;
        }

        add_filter('pre_option_template', [$this, 'filterTemplate']);
        add_filter('pre_option_stylesheet', [$this, 'filterStylesheet']);
        add_filter('pre_option', [$this, 'filterOption'], 10, 2);
        add_filter('wp_get_custom_css', [$this, 'filterCustomCss'], 10, 2);
    }

    public function resolution(): ?ThemeResolution
    {
        if ($this->resolved) {
            return $this->resolution;
        }

        // Context providers may call WordPress APIs that themselves read
        // options. Those reads re-enter pre_option while Theme Resolution is
        // still being built. During that nested read, leave WordPress on its
        // native Theme/options instead of recursively resolving forever.
        if ($this->resolving) {
            return null;
        }

        $this->resolving = true;

        try {
            $context = ($this->contextFactory)();

            if (!$context instanceof ThemeContext) {
                $this->resolved = true;
                return null;
            }

            $resolution = $this->resolver->resolve($context);
            $this->resolution = $resolution;
            $this->resolved = true;

            return $this->resolution;
        } finally {
            $this->resolving = false;
        }
    }

    public function filterTemplate(mixed $pre): mixed
    {
        $resolution = $this->resolution();

        return $resolution === null
            ? $pre
            : $resolution->target()->template();
    }

    public function filterStylesheet(mixed $pre): mixed
    {
        $resolution = $this->resolution();

        return $resolution === null
            ? $pre
            : $resolution->target()->stylesheet();
    }

    public function filterOption(mixed $pre, string $option): mixed
    {
        $resolution = $this->resolution();
        if ($resolution === null) {
            return $pre;
        }

        $target = $resolution->target();
        if (
            $target->settingsStylesheet() === $target->stylesheet() ||
            $option !== 'theme_mods_' . $target->stylesheet() ||
            !function_exists('get_option')
        ) {
            return $pre;
        }

        $mods = get_option('theme_mods_' . $target->settingsStylesheet());

        return is_array($mods) ? $mods : [];
    }

    public function filterCustomCss(string $css, string $stylesheet): string
    {
        $resolution = $this->resolution();
        if ($resolution === null) {
            return $css;
        }

        $target = $resolution->target();
        if (
            $target->settingsStylesheet() === $target->stylesheet() ||
            $stylesheet !== $target->stylesheet() ||
            !function_exists('wp_get_custom_css_post')
        ) {
            return $css;
        }

        $post = wp_get_custom_css_post($target->settingsStylesheet());
        if (!is_object($post) || !isset($post->post_content)) {
            return '';
        }

        return (string) $post->post_content;
    }
}
