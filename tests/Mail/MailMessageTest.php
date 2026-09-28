<?php

declare(strict_types=1);

namespace Period\WpKit\Tests\Mail;

use InvalidArgumentException;
use Period\WpKit\Mail\MailAddress;
use Period\WpKit\Mail\MailAttachment;
use Period\WpKit\Mail\MailMessage;
use PHPUnit\Framework\TestCase;

final class MailMessageTest extends TestCase
{
    public function testStoresTypedMessageData(): void
    {
        $to = new MailAddress('user@example.com', 'User');
        $attachment = new MailAttachment('/tmp/report.pdf', 'report.pdf', 'application/pdf');

        $message = new MailMessage(
            to: [$to],
            subject: 'Receipt',
            html: '<p>Paid</p>',
            text: 'Paid',
            attachments: [$attachment],
            category: 'transactional',
            source: 'fanika',
            sourceId: 'order:42',
            idempotencyKey: 'order-42-receipt',
            metadata: ['order_id' => 42],
        );

        self::assertSame([$to], $message->to());
        self::assertSame('Receipt', $message->subject());
        self::assertSame([$attachment], $message->attachments());
        self::assertSame('fanika', $message->source());
        self::assertSame('order-42-receipt', $message->idempotencyKey());
        self::assertSame(['order_id' => 42], $message->metadata());
    }

    public function testRequiresAtLeastOneRecipient(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MailMessage([], 'No recipient');
    }

    public function testRejectsHeaderInjectionInSubject(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MailMessage([new MailAddress('user@example.com')], "Subject\r\nBcc: attacker@example.com");
    }

    public function testAllowsCustomCategorySlug(): void
    {
        $message = new MailMessage(
            [new MailAddress('user@example.com')],
            'Custom',
            category: 'membership.notice',
        );

        self::assertSame('membership.notice', $message->category());
    }
}
