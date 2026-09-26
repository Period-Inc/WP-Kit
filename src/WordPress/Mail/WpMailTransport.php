<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress\Mail;

use Period\WpKit\Mail\Contract\MailTransportInterface;
use Period\WpKit\Mail\MailAddress;
use Period\WpKit\Mail\MailMessage;
use Period\WpKit\Mail\TransportResult;
use ReflectionFunction;
use Throwable;

final class WpMailTransport implements MailTransportInterface
{
    public function __construct(private ?WpMailContext $context = null)
    {
        $this->context ??= new WpMailContext();
    }

    public function name(): string
    {
        return 'wp_mail';
    }

    public function isAvailable(): bool
    {
        return function_exists('wp_mail');
    }

    public function send(MailMessage $message): TransportResult
    {
        if (!$this->isAvailable()) {
            return TransportResult::failed(
                'wp_mail_unavailable',
                'WordPress wp_mail() is not available.',
                false,
            );
        }

        $supportsEmbeds = $this->supportsEmbeds();
        if ($message->embeds() !== [] && !$supportsEmbeds) {
            return TransportResult::failed(
                'wp_mail_embeds_unsupported',
                'This WordPress version does not support wp_mail() embeds.',
                false,
            );
        }

        [$body, $headers] = $this->buildBodyAndHeaders($message);
        $to = array_map(fn (MailAddress $address): string => $this->formatAddress($address), $message->to());
        $attachments = array_map(static fn ($attachment): string => $attachment->location(), $message->attachments());
        $embeds = $this->formatEmbeds($message);

        try {
            $accepted = $this->context->withoutInterception(function () use (
                $to,
                $message,
                $body,
                $headers,
                $attachments,
                $embeds,
                $supportsEmbeds,
            ): bool {
                if ($supportsEmbeds) {
                    return wp_mail($to, $message->subject(), $body, $headers, $attachments, $embeds);
                }

                return wp_mail($to, $message->subject(), $body, $headers, $attachments);
            });
        } catch (Throwable $error) {
            return TransportResult::failed(
                'wp_mail_exception',
                $error->getMessage(),
                true,
                ['exception' => $error::class],
            );
        }

        if ($accepted !== true) {
            return TransportResult::failed(
                'wp_mail_failed',
                'wp_mail() returned false.',
                true,
            );
        }

        return TransportResult::accepted();
    }

    private function buildBodyAndHeaders(MailMessage $message): array
    {
        $headers = $this->withoutTypedHeaders($message->headers());

        if ($message->from() !== null) {
            $headers[] = 'From: ' . $this->formatAddress($message->from());
        }

        if ($message->replyTo() !== null) {
            $headers[] = 'Reply-To: ' . $this->formatAddress($message->replyTo());
        }

        if ($message->cc() !== []) {
            $headers[] = 'Cc: ' . implode(', ', array_map(
                fn (MailAddress $address): string => $this->formatAddress($address),
                $message->cc(),
            ));
        }

        if ($message->bcc() !== []) {
            $headers[] = 'Bcc: ' . implode(', ', array_map(
                fn (MailAddress $address): string => $this->formatAddress($address),
                $message->bcc(),
            ));
        }

        if ($message->html() !== null) {
            if (!$this->hasHeader($headers, 'Content-Type')) {
                $headers[] = 'Content-Type: text/html; charset=UTF-8';
            }

            return [$message->html(), $headers];
        }

        return [$message->text() ?? '', $headers];
    }

    private function withoutTypedHeaders(array $headers): array
    {
        $typed = ['from', 'reply-to', 'cc', 'bcc'];

        return array_values(array_filter($headers, static function (string $header) use ($typed): bool {
            $position = strpos($header, ':');
            if ($position === false) {
                return true;
            }

            return !in_array(strtolower(trim(substr($header, 0, $position))), $typed, true);
        }));
    }

    private function hasHeader(array $headers, string $name): bool
    {
        foreach ($headers as $header) {
            if (strncasecmp($header, $name . ':', strlen($name) + 1) === 0) {
                return true;
            }
        }

        return false;
    }

    private function formatAddress(MailAddress $address): string
    {
        if ($address->name() === null) {
            return $address->email();
        }

        $name = addcslashes($address->name(), '\\"');

        return sprintf('"%s" <%s>', $name, $address->email());
    }

    private function supportsEmbeds(): bool
    {
        try {
            return (new ReflectionFunction('wp_mail'))->getNumberOfParameters() >= 6;
        } catch (Throwable) {
            return false;
        }
    }

    private function formatEmbeds(MailMessage $message): array
    {
        $embeds = [];

        foreach ($message->embeds() as $index => $embed) {
            $key = $embed->contentId() ?? $index;
            $embeds[$key] = $embed->location();
        }

        return $embeds;
    }
}
