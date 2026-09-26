# period-wp-kit

WordPress のテーマ・プラグイン開発向け軽量ライブラリ。MetaBox、カスタム投稿タイプ、スクリプト/スタイル管理、HTML生成などの定型処理をまとめる。

- namespace: `Period\WpKit`
- エントリポイント: `pwk()`
- WordPress 依存は `src/Infrastructure/WordPress/` に閉じている
- WordPress 非依存ユーティリティは `src/Support/` に属する

## セットアップ

```bash
composer require period/wp-kit
```

```php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/period/wp-kit/bootstrap.php';
$app = pwk();
```

## 基本使用（pwk）

```php
// HTML ドキュメント生成
echo pwk()->document('<h1>Hello</h1>');

// ページタイトル
echo pwk()->title();

// サイト情報
echo pwk()->site()->name();
```

## HTML レンダリング

```php
echo pwk()->document('<main>...</main>', [
    'body_class'        => ['home'],
    'head_elements'     => ['<meta name="description" content="説明">'],
    'include_wp_head'   => true,
    'include_wp_footer' => true,
]);
```

## WordPress 連携（HookRegistrar）

`HookRegistrar` は `add_action` / `add_filter` / `add_shortcode` を統一的に登録する基盤です。WordPress がない環境では noop になります。

```php
use Period\WpKit\Infrastructure\WordPress\HookRegistrar;

$hooks = new HookRegistrar();
$hooks
    ->action('init', function (): void { /* ... */ })
    ->filter('the_content', function (string $c): string { return $c; })
    ->shortcode('my_tag', function (): string { return '<p>Hello</p>'; });
```

ショートコード登録には `ShortcodeRegistrar` も使えます。

```php
use Period\WpKit\Infrastructure\WordPress\ShortcodeRegistrar;

(new ShortcodeRegistrar())->register(); // [document] [title] [site_name]
```

## データ取得（SiteInfo / TitleResolver）

```php
use Period\WpKit\Infrastructure\WordPress\SiteInfo;
use Period\WpKit\Infrastructure\WordPress\TitleResolver;

$info     = new SiteInfo();
$resolver = new TitleResolver($info);

$info->name();            // サイト名
$resolver->siteTitle();  // "タイトル | サイト名"
```


## Application Plugin

WP-KitはPHP Framework / Libraryです。WordPress上で具体的なapplication responsibilityを完成させるPluginは **WP-Kit Application Plugin** としてWP-Kit本体から分離します。

Application PluginはWP-Kitを利用するconsumerであり、依存方向は `Application Plugin → WP-Kit` の一方向です。分類は機能数や成熟度ではなく責務で判断します。

→ [docs/application-plugin.md](docs/application-plugin.md)

## Theme Resolution

requestごとに使用する WordPress Theme を決定する基盤です。

`ThemeResolver` は priority 付きRuleを評価し、`ThemeTarget(template, stylesheet, settingsStylesheet)` を返します。管理者preview、user/role、request path、post/post type、taxonomy/term等はすべて同じResolverへの入力として扱います。

Deploymentは責務外です。deploy-kit等が別directoryへTheme treeを展開しても、WP-Kitはdeployment名やGit branchを知りません。利用可能なTheme identifierだけを扱います。

Themeをロードする前にResolverを有効化する必要があるため、サイト成立条件として使う場合はThemeの `functions.php` ではなくMU Plugin / site bootstrapから登録します。

→ [docs/theme-resolution.md](docs/theme-resolution.md)

管理者previewとDeploy Kit WordPress Deploymentを「現在レビュー中の論理Target」で接続する場合は [docs/theme-resolution-deployment-integration.md](docs/theme-resolution-deployment-integration.md) を参照。

## MetaBox

```php
pwk()->posts()
    ->register('news', ['label' => 'ニュース', 'menu_icon' => 'dashicons-media-text'])
    ->metaBox([
        'id'     => 'news_detail',
        'title'  => 'ニュース詳細',
        'fields' => [
            ['name' => 'lead',       'type' => 'textarea', 'label' => 'リード文'],
            ['name' => 'main_image', 'type' => 'image',    'label' => 'メイン画像'],
        ],
    ])
    ->registerTaxonomy('news_category', 'news', ['label' => 'カテゴリー'])
    ->boot();
```

ボタンラベルの指定は `labels` 配列を使います（`button_label` 等は deprecated）。

```php
['name' => 'thumb', 'type' => 'image', 'labels' => ['select_image' => '画像を選択', 'clear' => 'クリア']]
```

管理画面 JS の読み込みは [docs/js-loading.md](docs/js-loading.md) を参照。

## ユーティリティ（Support）

```php
use Period\WpKit\Support\TemplateFormatter;
use Period\WpKit\Support\CssName;
use Period\WpKit\Support\ImageUtil;

// {{ key }} 置換（WordPress 非依存）
(new TemplateFormatter())->format('{{ title }} | {{ site }}', ['title' => 'About', 'site' => 'My Site']);

// CSS クラス名生成
CssName::fromString('Hello World'); // → "hello-world"

// 画像向き判定
ImageUtil::orientation(1920, 1080); // → "landscape"
```

## i18n（Translator）

`Translator` はテンプレート層・呼び出し側で使います。内部ロジックへの注入は行いません。

```php
$t = pwk()->translator();
echo $t->html('Save'); // esc_html__('Save', 'period-wp-kit')
```

MetaBox のラベルを翻訳したい場合は呼び出し側で `labels` に渡します。

```php
$t = pwk()->translator();
new MetaBox([
    'id'     => 'sample',
    'post_type' => 'post',
    'fields' => [[
        'name'   => 'thumb',
        'type'   => 'image',
        'labels' => ['select_image' => $t->text('Select image'), 'clear' => $t->text('Clear')],
    ]],
]);
```

## ドキュメント

- [docs/application-plugin.md](docs/application-plugin.md) — WP-Kit Application Pluginの定義・責務境界
- [docs/usage.md](docs/usage.md) — 使用例リファレンス（全機能）
- [docs/metabox.md](docs/metabox.md) — MetaBox フィールド定義・save() の挙動
- [docs/usage-metabox.md](docs/usage-metabox.md) — MetaBox 使用例（gallery / repeater）
- [docs/usage-image-renderer.md](docs/usage-image-renderer.md) — ImageTagRenderer（img タグ生成）
- [docs/js-loading.md](docs/js-loading.md) — 管理画面 JS の読み込み方法
- [docs/usage-site-info.md](docs/usage-site-info.md) — SiteInfo（サイト情報取得）
- [docs/usage-title-resolver.md](docs/usage-title-resolver.md) — TitleResolver（ページタイトル取得）
- [docs/usage-template-formatter.md](docs/usage-template-formatter.md) — TemplateFormatter（WordPress 非依存テンプレート整形）
- [docs/usage-body-renderer.md](docs/usage-body-renderer.md) — BodyRenderer（body タグ生成）
- [docs/usage-document-renderer.md](docs/usage-document-renderer.md) — DocumentRenderer（完全な HTML ドキュメント生成）
- [docs/usage-hooks.md](docs/usage-hooks.md) — HookRegistrar / ShortcodeRegistrar（action / filter / shortcode 登録）
- [docs/theme-resolution.md](docs/theme-resolution.md) — request単位のTheme Resolution責務・設計
- [docs/theme-resolution-roadmap.md](docs/theme-resolution-roadmap.md) — 管理比較 / user / route / post / taxonomyを同じResolverへ統合する拡張計画
- [docs/theme-resolution-deployment-integration.md](docs/theme-resolution-deployment-integration.md) — 現在レビュー中Theme targetとDeploy Kit deploymentを疎結合で同期するbridge
- [docs/usage-theme-resolution.md](docs/usage-theme-resolution.md) — preview / user / post type / taxonomy条件の利用例
- [docs/usage-template-tags.md](docs/usage-template-tags.md) — Template Tags（pwk()->title() / site() / document()）
- [docs/migration.md](docs/migration.md) — v1 → v2 移行ガイド・非推奨項目一覧
- [docs/design-decisions.md](docs/design-decisions.md) — 設計判断の記録
- [docs/testing.md](docs/testing.md) — テスト方針・モック構成
