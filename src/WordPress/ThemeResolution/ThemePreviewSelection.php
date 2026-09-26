<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress\ThemeResolution;

use InvalidArgumentException;

final class ThemePreviewSelection
{
    private ?string $currentId = null;

    public function __construct(private ThemePreviewRegistry $registry)
    {
    }

    public function select(string $id): self
    {
        if (!$this->registry->has($id)) {
            throw new InvalidArgumentException(
                sprintf('Unknown Theme preview target "%s".', $id)
            );
        }

        $this->currentId = $id;

        return $this;
    }

    public function clear(): self
    {
        $this->currentId = null;

        return $this;
    }

    public function currentId(): ?string
    {
        return $this->currentId;
    }

    public function current(): ?ThemePreviewTarget
    {
        return $this->currentId === null
            ? null
            : $this->registry->get($this->currentId);
    }

    public function withContext(
        ThemeContext $context,
        string $contextKey = 'preview.theme',
    ): ThemeContext {
        return $context->with($contextKey, $this->currentId);
    }
}
