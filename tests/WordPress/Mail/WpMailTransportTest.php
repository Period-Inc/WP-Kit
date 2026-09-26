<?php

declare(strict_types=1);

namespace Period\WpKit\Tests\WordPress\Mail;

use Period\WpKit\Mail\MailAddress;
use Period\WpKit\Mail\MailAttachment;
use Period\WpKit\Mail\MailMessage;
use Period\WpKit\WordPress\Mail\WpMailTransport;
use PHPUnit\Framework\TestCase;

final class WpMailTransportTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testSendsThroughWpMailWithTypedHeadersAndEmbeds(): void
    {
        if (function_exists('wp_mail')) {
            $this->markTestSkipped('wp_mail exists in environment');
        }

        eval(<<<'STUB'
namespace {
function wp_mail($to, $subject, $message, $headers = '', $attachments = [], $embeds = []): bool {
    $GLOBALS['_wpkit_mail_call'] = compact('to', 'subject', 'message', 'headers', 'attachments', 'embeds');
    return true;
}
}
STUB
        );

        $message = new MailMessage(
            to: [new MailAddress('user@example.com', 'User')],
            subject: 'Receipt',
            html: '<p>Paid</p>',
            cc: [new MailAddress('cc@example.com')],
            from: new MailAddress('sender@example.com', 'Sender'),
            attachments: [new MailAttachment('/tmp/report.pdf')],
            embeds: [new MailAttachment('/tmp/hero.jpg', contentId: 'hero', inline: true)],
        );

        $result = (new WpMailTransport())->send($message);
        $call = $GLOBALS['_wpkit_mail_call'] ?? [];

        self::assertTrue($result->isAccepted());
        self::assertSame('"User" <user@example.com>', $call['to'][0] ?? null);
        self::assertContains('Cc: cc@example.com', $call['headers'] ?? []);
        self::assertContains('From: "Sender" <sender@example.com>', $call['headers'] ?? []);
        self::assertContains('Content-Type: text/html; charset=UTF-8', $call['headers'] ?? []);
        self::assertSame(['/tmp/report.pdf'], $call['attachments'] ?? null);
        self::assertSame(['hero' => '/tmp/hero.jpg'], $call['embeds'] ?? null);
    }

    public function testReportsUnavailableOutsideWordPress(): void
    {
        if (function_exists('wp_mail')) {
            $this->markTestSkipped('wp_mail exists in environment');
        }

        $result = (new WpMailTransport())->send(new MailMessage(
            [new MailAddress('user@example.com')],
            'Test',
            text: 'Body',
        ));

        self::assertFalse($result->isAccepted());
        self::assertSame('wp_mail_unavailable', $result->errorCode());
        self::assertFalse($result->isRetryable());
    }
}
