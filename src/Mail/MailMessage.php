<?php

declare(strict_types=1);

namespace Period\WpKit\Mail;

use InvalidArgumentException;

final class MailMessage
{
    /** @var list<MailAddress> */
    private array $to;

    /** @var list<MailAddress> */
    private array $cc;

    /** @var list<MailAddress> */
    private array $bcc;

    /** @var list<string> */
    private array $headers;

    /** @var list<MailAttachment> */
    private array $attachments;

    /** @var list<MailAttachment> */
    private array $embeds;

    public function __construct(
        array $to,
        private string $subject,
        private ?string $html = null,
        private ?string $text = null,
        array $cc = [],
        array $bcc = [],
        array $headers = [],
        array $attachments = [],
        array $embeds = [],
        private ?MailAddress $from = null,
        private ?MailAddress $replyTo = null,
        private string $category = MailCategory::TRANSACTIONAL,
        private string $source = '',
        private ?string $sourceId = null,
        private ?string $idempotencyKey = null,
        private array $metadata = [],
    ) {
        $this->to = self::assertListOf($to, MailAddress::class, 'to');
        $this->cc = self::assertListOf($cc, MailAddress::class, 'cc');
        $this->bcc = self::assertListOf($bcc, MailAddress::class, 'bcc');
        $this->headers = self::assertStringList($headers, 'headers');
        $this->attachments = self::assertListOf($attachments, MailAttachment::class, 'attachments');
        $this->embeds = self::assertListOf($embeds, MailAttachment::class, 'embeds');

        if ($this->to === [] && $this->cc === [] && $this->bcc === []) {
            throw new InvalidArgumentException('Mail message requires at least one recipient.');
        }

        if (preg_match('/[\r\n]/', $this->subject)) {
            throw new InvalidArgumentException('Mail subject must not contain line breaks.');
        }

        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $this->category)) {
            throw new InvalidArgumentException(sprintf('Invalid mail category: "%s".', $this->category));
        }

        if ($this->source !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', $this->source)) {
            throw new InvalidArgumentException(sprintf('Invalid mail source: "%s".', $this->source));
        }

        if ($this->idempotencyKey !== null && trim($this->idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key must be null or a non-empty string.');
        }
    }

    /** @return list<MailAddress> */
    public function to(): array
    {
        return $this->to;
    }

    /** @return list<MailAddress> */
    public function cc(): array
    {
        return $this->cc;
    }

    /** @return list<MailAddress> */
    public function bcc(): array
    {
        return $this->bcc;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function html(): ?string
    {
        return $this->html;
    }

    public function text(): ?string
    {
        return $this->text;
    }

    /** @return list<string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @return list<MailAttachment> */
    public function attachments(): array
    {
        return $this->attachments;
    }

    /** @return list<MailAttachment> */
    public function embeds(): array
    {
        return $this->embeds;
    }

    public function from(): ?MailAddress
    {
        return $this->from;
    }

    public function replyTo(): ?MailAddress
    {
        return $this->replyTo;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function sourceId(): ?string
    {
        return $this->sourceId;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    private static function assertListOf(array $values, string $class, string $name): array
    {
        $values = array_values($values);

        foreach ($values as $value) {
            if (!$value instanceof $class) {
                throw new InvalidArgumentException(sprintf('%s must contain only %s values.', $name, $class));
            }
        }

        return $values;
    }

    private static function assertStringList(array $values, string $name): array
    {
        $values = array_values($values);

        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(sprintf('%s must contain only strings.', $name));
            }
        }

        return $values;
    }
}
