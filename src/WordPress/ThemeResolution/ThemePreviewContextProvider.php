<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress\ThemeResolution;

use Closure;

/**
 * Builds ThemeContext from an external logical preview-id provider.
 *
 * The provider is application-owned. WP-Kit does not know where the id is
 * stored or whether it came from Deploy Kit, a session, user meta, or another
 * adapter.
 */
final class ThemePreviewContextProvider
{
    private Closure $currentIdProvider;

    public function __construct(
        private ThemePreviewRegistry $registry,
        callable $currentIdProvider,
        private string $contextKey = 'preview.theme',
    ) {
        $this->currentIdProvider = Closure::fromCallable($currentIdProvider);
    }

    public function context(?ThemeContext $base = null): ThemeContext
    {
        $base ??= new ThemeContext();

        $id = ($this->currentIdProvider)();
        if (!is_string($id) || $id === '' || !$this->registry->has($id)) {
            return $base;
        }

        return $base->with($this->contextKey, $id);
    }
}
