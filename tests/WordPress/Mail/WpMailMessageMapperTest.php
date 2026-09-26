<?php

declare(strict_types=1);

namespace Period\WpKit\Tests\WordPress\Mail;

use Period\WpKit\Mail\MailCategory;
use Period\WpKit\WordPress\Mail\WpMailMessageMapper;
use PHPUnit\Framework\TestCase;

final class WpMailMessageMapperTest extends TestCase
{
    public function testMapsWpMailArguments(): void
    {
        $message = (new WpMailMessageMapper())->fromWpMailArgs([
            'to' => 'User <user@example.com>',
            'subject' => 'Hello',
            'message' => '<p>Hello</p>',
            'headers' => [
                'Content-Type: text/html; charset=UTF-8',
                'Cc: cc@example.com',
                'Bcc: bcc@example.com',
                'From: Sender <sender@example.com>',
                'Reply-To: reply@example.com',
                'X-Test: yes',
            ],
            'attachments' => ['/tmp/file.pdf'],
            'embeds' => ['hero' => '/tmp/hero.jpg'],
        ]);

        self::assertSame('user@example.com', $message->to()[0]->email());
        self::assertSame('cc@example.com', $message->cc()[0]->email());
        self::assertSame('bcc@example.com', $message->bcc()[0]->email());
        self::assertSame('sender@example.com', $message->from()?->email());
        self::assertSame('reply@example.com', $message->replyTo()?->email());
        self::assertSame('<p>Hello</p>', $message->html());
        self::assertNull($message->text());
        self::assertSame('/tmp/file.pdf', $message->attachments()[0]->location());
        self::assertSame('hero', $message->embeds()[0]->contentId());
        self::assertSame(MailCategory::UNCATEGORIZED, $message->category());
        self::assertSame('wordpress.wp_mail', $message->source());
        self::assertContains('X-Test: yes', $message->headers());
    }

    public function testDefaultsToPlainTextWithoutContentTypeHeader(): void
    {
        $message = (new WpMailMessageMapper())->fromWpMailArgs([
            'to' => 'user@example.com',
            'subject' => 'Hello',
            'message' => 'Plain',
        ]);

        self::assertSame('Plain', $message->text());
        self::assertNull($message->html());
    }
}
