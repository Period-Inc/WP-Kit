<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress\Mail;

use InvalidArgumentException;
use Period\WpKit\Mail\MailAddress;
use Period\WpKit\Mail\MailAttachment;
use Period\WpKit\Mail\MailCategory;
use Period\WpKit\Mail\MailMessage;

final class WpMailMessageMapper
{
    public function fromWpMailArgs(array $atts): MailMessage
    {
        $headers = $this->normalizeHeaders($atts['headers'] ?? []);
        [$headers, $cc] = $this->extractAddressHeader($headers, 'Cc');
        [$headers, $bcc] = $this->extractAddressHeader($headers, 'Bcc');
        [$headers, $from] = $this->extractSingleAddressHeader($headers, 'From');
        [$headers, $replyTo] = $this->extractSingleAddressHeader($headers, 'Reply-To');

        $message = (string) ($atts['message'] ?? '');
        $isHtml = $this->hasHtmlContentType($headers);

        return new MailMessage(
            to: $this->parseAddresses($atts['to'] ?? []),
            subject: (string) ($atts['subject'] ?? ''),
            html: $isHtml ? $message : null,
            text: $isHtml ? null : $message,
            cc: $cc,
            bcc: $bcc,
            headers: $headers,
            attachments: $this->normalizeAttachments($atts['attachments'] ?? [], false),
            embeds: $this->normalizeAttachments($atts['embeds'] ?? [], true),
            from: $from,
            replyTo: $replyTo,
            category: MailCategory::UNCATEGORIZED,
            source: 'wordpress.wp_mail',
        );
    }

    private function parseAddresses(string|array $value): array
    {
        $parts = [];

        foreach ((array) $value as $entry) {
            foreach (str_getcsv((string) $entry, ',', '"', '\\') as $part) {
                if (trim($part) !== '') {
                    $parts[] = trim($part);
                }
            }
        }

        $addresses = [];

        foreach ($parts as $part) {
            if (preg_match('/^\s*(?:"?(.+?)"?\s*)?<([^<>]+)>\s*$/', $part, $matches) === 1) {
                $name = trim((string) $matches[1], " \t\n\r\0\x0B\"");
                $addresses[] = new MailAddress(trim($matches[2]), $name !== '' ? $name : null);
                continue;
            }

            $addresses[] = new MailAddress($part);
        }

        return $addresses;
    }

    private function normalizeHeaders(string|array $value): array
    {
        $headers = [];

        foreach ((array) $value as $entry) {
            foreach (preg_split('/\r\n|\r|\n/', (string) $entry) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $headers[] = $line;
                }
            }
        }

        return $headers;
    }

    private function extractAddressHeader(array $headers, string $name): array
    {
        $remaining = [];
        $addresses = [];

        foreach ($headers as $header) {
            $value = $this->headerValue($header, $name);
            if ($value === null) {
                $remaining[] = $header;
                continue;
            }

            $addresses = array_merge($addresses, $this->parseAddresses($value));
        }

        return [$remaining, $addresses];
    }

    private function extractSingleAddressHeader(array $headers, string $name): array
    {
        [$remaining, $addresses] = $this->extractAddressHeader($headers, $name);

        if (count($addresses) > 1) {
            throw new InvalidArgumentException(sprintf('%s header must contain at most one address.', $name));
        }

        return [$remaining, $addresses[0] ?? null];
    }

    private function headerValue(string $header, string $name): ?string
    {
        $prefix = $name . ':';
        if (strncasecmp($header, $prefix, strlen($prefix)) !== 0) {
            return null;
        }

        return trim(substr($header, strlen($prefix)));
    }

    private function hasHtmlContentType(array $headers): bool
    {
        foreach ($headers as $header) {
            $value = $this->headerValue($header, 'Content-Type');
            if ($value !== null && stripos($value, 'text/html') === 0) {
                return true;
            }
        }

        return false;
    }

    private function normalizeAttachments(string|array $value, bool $inline): array
    {
        $attachments = [];

        foreach ((array) $value as $key => $path) {
            $path = trim((string) $path);
            if ($path === '') {
                continue;
            }

            $contentId = $inline && is_string($key) && $key !== '' ? $key : null;
            $attachments[] = new MailAttachment(
                location: $path,
                contentId: $contentId,
                inline: $inline,
            );
        }

        return $attachments;
    }
}
