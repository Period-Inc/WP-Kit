<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress\Mail;

use Period\WpKit\Mail\Contract\MailQueueInterface;
use Period\WpKit\WordPress\HookRegistrar;
use Throwable;

final class WpMailInterceptor
{
    public function __construct(
        private MailQueueInterface $queue,
        private ?WpMailMessageMapper $mapper = null,
        private ?WpMailContext $context = null,
    ) {
        $this->mapper ??= new WpMailMessageMapper();
        $this->context ??= new WpMailContext();
    }

    public function register(?HookRegistrar $hooks = null): self
    {
        ($hooks ?? new HookRegistrar())->filter('pre_wp_mail', [$this, 'intercept'], 10, 2);

        return $this;
    }

    public function intercept(mixed $shortCircuit, array $atts): mixed
    {
        if ($shortCircuit !== null || $this->context->isInterceptionSuppressed()) {
            return $shortCircuit;
        }

        try {
            $mailId = $this->queue->enqueue($this->mapper->fromWpMailArgs($atts));

            return $mailId !== '';
        } catch (Throwable) {
            return false;
        }
    }
}
