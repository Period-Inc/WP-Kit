<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress\ThemeResolution;

use InvalidArgumentException;

final class ThemePreviewRegistry
{
    /** @var array<string, ThemePreviewTarget> */
    private array $targets = [];

    public function register(string $id, string $label, ThemeTarget $target): self
    {
        if (isset($this->targets[$id])) {
            throw new InvalidArgumentException(
                sprintf('Theme preview target id "%s" is already registered.', $id)
            );
        }

        $this->targets[$id] = new ThemePreviewTarget($id, $label, $target);

        return $this;
    }

    public function has(string $id): bool
    {
        return isset($this->targets[$id]);
    }

    public function get(string $id): ?ThemePreviewTarget
    {
        return $this->targets[$id] ?? null;
    }

    /** @return array<string, ThemePreviewTarget> */
    public function all(): array
    {
        return $this->targets;
    }

    /**
     * Register one high-priority resolver rule that turns a logical preview
     * id in ThemeContext into a ThemeTarget.
     */
    public function registerResolverRule(
        ThemeResolver $resolver,
        string $contextKey = 'preview.theme',
        string $ruleId = 'management-preview',
        int $priority = 1000,
    ): self {
        $resolver->addRule(
            $ruleId,
            function (ThemeContext $context) use ($contextKey): ?ThemeTarget {
                $id = $context->get($contextKey);
                if (!is_string($id) || $id === '') {
                    return null;
                }

                return $this->get($id)?->target();
            },
            $priority
        );

        return $this;
    }
}
