<?php

declare(strict_types=1);

namespace Period\WpKit\Mail;

final class TransportResult
{
    private function __construct(
        private bool $accepted,
        private ?string $providerMessageId,
        private ?string $errorCode,
        private ?string $errorMessage,
        private bool $retryable,
        private array $metadata,
    ) {
    }

    public static function accepted(?string $providerMessageId = null, array $metadata = []): self
    {
        return new self(true, $providerMessageId, null, null, false, $metadata);
    }

    public static function failed(
        ?string $errorCode,
        string $errorMessage,
        bool $retryable = true,
        array $metadata = [],
    ): self {
        return new self(false, null, $errorCode, $errorMessage, $retryable, $metadata);
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    public function providerMessageId(): ?string
    {
        return $this->providerMessageId;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }
}
