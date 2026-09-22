# Theme Resolution Usage

## Minimal setup

Theme Resolution must be registered before WordPress loads the active Theme.

A Site Core / MU Plugin can load WP-Kit and register the resolver.

```php
use Period\WpKit\WordPress\ThemeResolution\ThemeContext;
use Period\WpKit\WordPress\ThemeResolution\ThemeResolutionRuntime;
use Period\WpKit\WordPress\ThemeResolution\ThemeResolver;
use Period\WpKit\WordPress\ThemeResolution\ThemeTarget;

$resolver = new ThemeResolver();

$resolver->addRule(
    'admin-preview',
    function (ThemeContext $context): ?ThemeTarget {
        if ($context->get('preview.theme') !== 'slot-01') {
            return null;
        }

        return new ThemeTarget(
            template: 'astra',
            stylesheet: 'purimall-dk-slot-01',
            settingsStylesheet: 'purimall',
        );
    },
    priority: 1000,
);

$runtime = new ThemeResolutionRuntime(
    $resolver,
    function (): ThemeContext {
        return new ThemeContext([
            'preview.theme' => null,
        ]);
    },
);

$runtime->register();
```

If no rule matches, WordPress uses its normal configured Theme.

## User-based selection

User information is just context.

```php
$resolver->addRule(
    'member-theme',
    function (ThemeContext $context): ?ThemeTarget {
        $roles = (array) $context->get('user.roles', []);

        if (!in_array('special_member', $roles, true)) {
            return null;
        }

        return new ThemeTarget('astra', 'special-member-theme');
    },
    500,
);
```

WP-Kit does not prescribe where the user facts come from. The early site bootstrap may derive them from the authenticated WordPress user.

## Post type selection

The rule itself is simple.

```php
$resolver->addRule(
    'product-theme',
    function (ThemeContext $context): ?ThemeTarget {
        if ($context->get('content.post_type') !== 'product') {
            return null;
        }

        return new ThemeTarget('astra', 'product-theme');
    },
    200,
);
```

However, the difficult part is producing `content.post_type` before Theme bootstrap.

Do not assume that normal main-query conditional tags are ready when template/stylesheet are resolved. A content locator adapter must resolve the request early and populate ThemeContext.

## Taxonomy / term selection

The same rule model applies.

```php
$resolver->addRule(
    'adult-zone-theme',
    function (ThemeContext $context): ?ThemeTarget {
        $terms = (array) $context->get('content.terms', []);

        return in_array('adult', $terms, true)
            ? new ThemeTarget('astra', 'adult-zone-theme')
            : null;
    },
    250,
);
```

The early content locator remains responsible for identifying the relevant taxonomy/term facts.

## Management preview / Theme comparison

Preview is a high-priority override, not a separate Theme switching engine.

A preview adapter should:

- expose only registered ThemeTarget IDs;
- require a capability;
- persist only a logical preview key, never a filesystem path;
- populate `preview.theme` in ThemeContext;
- use a higher resolver priority than normal user/content rules;
- provide an independent way to clear the preview and return to Base.

This permits an administrator to compare multiple Theme trees while another user continues to receive the normal resolved Theme.

## Deployment tool integration

Suppose a deployment tool places files at:

`wp-content/themes/purimall-dk-slot-01/`

Theme Resolution only needs:

```php
new ThemeTarget(
    template: 'astra',
    stylesheet: 'purimall-dk-slot-01',
    settingsStylesheet: 'purimall',
);
```

It does not need:

- deployment name;
- Git repository;
- branch;
- commit;
- worktree path;
- server path.

That separation is intentional.

## Priority guidance

A site may choose its own values, but a typical order is:

| Priority class | Example |
| --- | --- |
| highest | emergency/admin preview |
| high | explicit user override |
| medium | user/role/capability |
| normal | post/post type/taxonomy/route |
| none matched | native WordPress Theme |

The actual numeric values are application policy and are not fixed by WP-Kit.
