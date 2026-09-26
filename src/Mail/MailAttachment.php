<?php

declare(strict_types=1);

namespace Period\WpKit\Mail;

use InvalidArgumentException;

final class MailAttachment
{
    public function __construct(
        private string $location,
        private ?string $name = null,
        private ?string $contentType = null,
        private ?string $contentId = null,
        private bool $inline = false,
    ) {
        if (trim($this->location) === '') {
            throw new InvalidArgumentException('Mail attachment location must not be empty.');
        }

        if ($this->contentId !== null && preg_match('/[\r\n]/', $this->contentId)) {
            throw new InvalidArgumentException('Mail attachment content ID must not contain line breaks.');
        }
    }

    public function location(): string
    {
        return $this->location;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function contentType(): ?string
    {
        return $this->contentType;
    }

    public function contentId(): ?string
    {
        return $this->contentId;
    }

    public function isInline(): bool
    {
        return $this->inline;
    }
}
