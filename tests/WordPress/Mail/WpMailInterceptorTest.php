<?php

declare(strict_types=1);

namespace Period\WpKit\Tests\WordPress\Mail;

use DateTimeImmutable;
use Period\WpKit\Mail\Contract\MailQueueInterface;
use Period\WpKit\Mail\MailMessage;
use Period\WpKit\WordPress\Mail\WpMailContext;
use Period\WpKit\WordPress\Mail\WpMailInterceptor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WpMailInterceptorTest extends TestCase
{
    public function testQueuesWpMailAndShortCircuitsWithTrue(): void
    {
        $queue = new RecordingMailQueue();
        $interceptor = new WpMailInterceptor($queue);

        $result = $interceptor->intercept(null, [
            'to' => 'user@example.com',
            'subject' => 'Queued',
            'message' => 'Body',
        ]);

        self::assertTrue($result);
        self::assertCount(1, $queue->messages);
        self::assertSame('wordpress.wp_mail', $queue->messages[0]->source());
    }

    public function testPreservesExistingShortCircuitValue(): void
    {
        $queue = new RecordingMailQueue();
        $interceptor = new WpMailInterceptor($queue);

        self::assertFalse($interceptor->intercept(false, []));
        self::assertCount(0, $queue->messages);
    }

    public function testBypassesQueueInsideTransportContext(): void
    {
        $queue = new RecordingMailQueue();
        $context = new WpMailContext();
        $interceptor = new WpMailInterceptor($queue, context: $context);

        $result = $context->withoutInterception(fn () => $interceptor->intercept(null, [
            'to' => 'user@example.com',
            'subject' => 'Direct',
            'message' => 'Body',
        ]));

        self::assertNull($result);
        self::assertCount(0, $queue->messages);
    }

    public function testQueueFailureShortCircuitsWithFalse(): void
    {
        $queue = new class implements MailQueueInterface {
            public function enqueue(MailMessage $message, ?DateTimeImmutable $sendAt = null): string
            {
                throw new RuntimeException('queue unavailable');
            }
        };

        self::assertFalse((new WpMailInterceptor($queue))->intercept(null, [
            'to' => 'user@example.com',
            'subject' => 'Failure',
            'message' => 'Body',
        ]));
    }
}

final class RecordingMailQueue implements MailQueueInterface
{
    public array $messages = [];

    public function enqueue(MailMessage $message, ?DateTimeImmutable $sendAt = null): string
    {
        $this->messages[] = $message;

        return 'mail-1';
    }
}
