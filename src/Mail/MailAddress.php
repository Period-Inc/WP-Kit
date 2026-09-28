<?php

declare(strict_types=1);

namespace Period\WpKit\Mail;

use InvalidArgumentException;

final class MailAddress
{
    private string $email;
    private ?string $name;

    public function __construct(string $email, ?string $name = null)
    {
        $email = trim($email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(sprintf('Invalid email address: "%s".', $email));
        }

        if ($name !== null && preg_match('/[\r\n]/', $name)) {
            throw new InvalidArgumentException('Mail address name must not contain line breaks.');
        }

        $this->email = $email;
        $this->name = $name !== null && $name !== '' ? $name : null;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): ?string
    {
        return $this->name;
    }
}
