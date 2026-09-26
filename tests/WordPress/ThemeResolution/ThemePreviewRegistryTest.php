<?php

declare(strict_types=1);

namespace Period\WpKit\Tests\WordPress\ThemeResolution;

use InvalidArgumentException;
use Period\WpKit\WordPress\ThemeResolution\ThemeContext;
use Period\WpKit\WordPress\ThemeResolution\ThemePreviewRegistry;
use Period\WpKit\WordPress\ThemeResolution\ThemePreviewSelection;
use Period\WpKit\WordPress\ThemeResolution\ThemeResolver;
use Period\WpKit\WordPress\ThemeResolution\ThemeTarget;
use PHPUnit\Framework\TestCase;

final class ThemePreviewRegistryTest extends TestCase
{
    public function testRegisteredPreviewTargetResolvesThroughSharedResolver(): void
    {
        $registry = new ThemePreviewRegistry();
        $registry->register(
            'slot-01',
            'Slot 01',
            new ThemeTarget('astra', 'purimall-dk-slot-01', 'purimall')
        );

        $resolver = new ThemeResolver();
        $registry->registerResolverRule($resolver);

        $resolution = $resolver->resolve(new ThemeContext([
            'preview.theme' => 'slot-01',
        ]));

        self::assertNotNull($resolution);
        self::assertSame('management-preview', $resolution->ruleId());
        self::assertSame('purimall-dk-slot-01', $resolution->target()->stylesheet());
        self::assertSame('purimall', $resolution->target()->settingsStylesheet());
    }

    public function testUnknownPreviewTargetFallsThrough(): void
    {
        $registry = new ThemePreviewRegistry();
        $resolver = new ThemeResolver();
        $registry->registerResolverRule($resolver);

        self::assertNull($resolver->resolve(new ThemeContext([
            'preview.theme' => 'slot-unknown',
        ])));
    }

    public function testDuplicatePreviewTargetIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $registry = new ThemePreviewRegistry();
        $target = new ThemeTarget('astra', 'purimall-slot-01', 'purimall');

        $registry->register('slot-01', 'Slot 01', $target);
        $registry->register('slot-01', 'Duplicate', $target);
    }

    public function testSelectionKeepsLogicalIdAndAddsItToContext(): void
    {
        $registry = new ThemePreviewRegistry();
        $registry->register(
            'slot-01',
            'Slot 01',
            new ThemeTarget('astra', 'purimall-dk-slot-01', 'purimall')
        );

        $selection = new ThemePreviewSelection($registry);
        $selection->select('slot-01');

        self::assertSame('slot-01', $selection->currentId());
        self::assertSame('Slot 01', $selection->current()?->label());

        $context = $selection->withContext(new ThemeContext([
            'user.id' => 42,
        ]));

        self::assertSame('slot-01', $context->get('preview.theme'));
        self::assertSame(42, $context->get('user.id'));

        $selection->clear();
        self::assertNull($selection->currentId());
    }

    public function testSelectionRejectsUnregisteredTarget(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $selection = new ThemePreviewSelection(new ThemePreviewRegistry());
        $selection->select('slot-01');
    }
}
