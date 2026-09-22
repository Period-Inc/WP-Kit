<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress\ThemeResolution;

use Closure;
use InvalidArgumentException;
use UnexpectedValueException;

final class ThemeResolver
{
    private array $rules = [];
    private int $sequence = 0;

    public function addRule(string $id, callable $rule, int $priority = 0): self
    {
        if ($id === '') {
            throw new InvalidArgumentException('Theme rule id must not be empty.');
        }

        foreach ($this->rules as $registered) {
            if ($registered['id'] === $id) {
                throw new InvalidArgumentException(sprintf('Theme rule id "%s" is already registered.', $id));
            }
        }

        $this->rules[] = [
            'id' => $id,
            'priority' => $priority,
            'sequence' => $this->sequence++,
            'rule' => Closure::fromCallable($rule),
        ];

        return $this;
    }

    public function resolve(ThemeContext $context): ?ThemeResolution
    {
        $rules = $this->rules;

        usort(
            $rules,
            static function (array $a, array $b): int {
                if ($a['priority'] === $b['priority']) {
                    return $a['sequence'] <=> $b['sequence'];
                }

                return $b['priority'] <=> $a['priority'];
            }
        );

        foreach ($rules as $registered) {
            $target = ($registered['rule'])($context);

            if ($target === null) {
                continue;
            }

            if (!$target instanceof ThemeTarget) {
                throw new UnexpectedValueException(
                    sprintf('Theme rule "%s" must return ThemeTarget or null.', $registered['id'])
                );
            }

            return new ThemeResolution(
                $target,
                $registered['id'],
                $registered['priority'],
            );
        }

        return null;
    }
}
