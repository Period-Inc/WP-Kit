<?php

declare(strict_types=1);

namespace Period\WpKit\Mail\Contract;

use Period\WpKit\Mail\MailAttachment;

interface AttachmentStoreInterface
{
    public function snapshot(MailAttachment $attachment): MailAttachment;

    public function release(MailAttachment $attachment): void;
}
