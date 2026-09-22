<?php

declare(strict_types=1);

namespace Period\WpKit\Tests\WordPress\ThemeResolution;

use InvalidArgumentException;
use Period\WpKit\WordPress\ThemeResolution\ThemeContext;
use Period\WpKit\WordPress\ThemeResolution\ThemeResolver;
use Period\WpKit\WordPress\ThemeResolution\ThemeTarget;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ThemeResolverTest extends TestCase
{
    public function testHigherPriorityRuleWins(): void
    {
        $resolver = new ThemeResolver();
        $resolver
            ->addRule('content', fn (ThemeContext $context) => new ThemeTarget('base', 'content'), 100)
            ->addRule('preview', fn (ThemeContext $context) => new ThemeTarget('base', 'preview'), 1000);

        $resolution = $resolver->resolve(new ThemeContext());

        self::assertNotNull($resolution);
        self::assertSame('preview', $resolution->ruleId());
        self::assertSame(1000, $resolution->priority());
        self::assertSame('preview', $resolution->target()->stylesheet());
    }

    public function testSamePriorityUsesRegistrationOrder(): void
    {
        $resolver = new ThemeResolver();
        $resolver
            ->addRule('first', fn (ThemeContext $context) => new ThemeTarget('base', 'first'), 100)
            ->addRule('second', fn (ThemeContext $context) => new ThemeTarget('base', 'second'), 100);

        $resolution = $resolver->resolve(new ThemeContext());

        self::assertNotNull($resolution);
        self::assertSame('first', $resolution->ruleId());
    }

    public function testNullRuleFallsThrough(): void
    {
        $resolver = new ThemeResolver();
        $resolver
            ->addRule('no-match', fn (ThemeContext $context) => null, 1000)
            ->addRule(
                'user',
                fn (ThemeContext $context) => $context->get('user.id') === 42
                    ? new ThemeTarget('base', 'user-theme')
                    : null,
                500
            );

        $resolution = $resolver->resolve(new ThemeContext(['user.id' => 42]));

        self::assertNotNull($resolution);
        self::assertSame('user', $resolution->ruleId());
        self::assertSame('user-theme', $resolution->target()->stylesheet());
    }

    public function testNoMatchLeavesWordPressThemeUnchanged(): void
    {
        $resolver = new ThemeResolver();
        $resolver->addRule('none', fn (ThemeContext $context) => null);

        self::assertNull($resolver->resolve(new ThemeContext()));
    }

    public function testDuplicateRuleIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $resolver = new ThemeResolver();
        $resolver->addRule('preview', fn (ThemeContext $context) => null);
        $resolver->addRule('preview', fn (ThemeContext $context) => null);
    }

    public function testInvalidRuleResultIsRejected(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $resolver = new ThemeResolver();
        $resolver->addRule('invalid', fn (ThemeContext $context) => 'theme-name');
        $resolver->resolve(new ThemeContext());
    }

    public function testThemeTargetRejectsFilesystemPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ThemeTarget('astra', '../purimall');
    }
}
