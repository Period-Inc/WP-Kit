# Theme Resolution / Deployment Review Target Integration

## Purpose

WP-Kit Theme Resolution と deployment tool の責務を分離したまま、WordPress 管理画面上の「現在レビュー中の対象」と deployment 先を一致させます。

WP-Kit は Theme の論理的な preview/review target を扱います。deploy-kit は deployment と configured directory を扱います。両者を直接依存させず、Site Core / Application Plugin が橋渡しします。

より一般的な Slot の概念定義は `Period-Inc/deploy-kit/docs/2026-09-27-slot-model-decision.md` を正本とします。WP-Kit の ThemePreviewRegistry / ThemePreviewSelection は Slot 自体の定義ではなく、Theme binding / Theme-side selection adapter として利用できます。

## Responsibility Boundary

~~~text
WP-Kit
  ThemePreviewRegistry
  ThemePreviewSelection
      ↓ current logical id
   slot-01

Site Core / Application Plugin
      ↓ site-specific mapping
   slot-01 → project-test-slot-01

Deploy Kit WordPress Deployment
      ↓ Broker-authorized deployment
   project-test-slot-01

deploy-kit Core
      ↓
configured directory
~~~

WP-Kit は deployment 名・Git branch・server path を知りません。deploy-kit は Theme identifier・ThemeTarget・Theme Resolver rule を知りません。

## WP-Kit Preview Registration

~~~php
use Period\WpKit\WordPress\ThemeResolution\ThemeContext;
use Period\WpKit\WordPress\ThemeResolution\ThemePreviewSelection;
use Period\WpKit\WordPress\ThemeResolution\ThemeResolutionRuntime;
use Period\WpKit\WordPress\ThemeResolution\ThemeTarget;

$previews = pwk()->themePreviews();

$previews
    ->register(
        'base-review',
        'Base',
        new ThemeTarget('base-parent', 'base-theme', 'base-theme')
    )
    ->register(
        'slot-01',
        'Slot 01',
        new ThemeTarget('base-parent', 'project-theme-slot-01', 'base-theme')
    );

$previews->registerResolverRule(pwk()->themes());

$selection = new ThemePreviewSelection($previews);
~~~

ThemePreviewSelection は request-local な論理選択だけを保持します。

どの user/session がどの target を選んでいるかの永続化、capability、nonce、管理UIは Site Core / Application Plugin の責務です。永続化された選択値を request bootstrap 時に読み込み、登録済み target の場合だけ select() します。

## Theme Resolution Context

現在選択中の logical target は既存の Theme Resolution pipeline へ渡します。

外部adapterが logical ID を公開している場合は `ThemePreviewContextProvider` を使えます。provider は callable だけを受け取るため、WP-Kit Core は Deploy Kit や永続化方式に依存しません。

~~~php
use Period\WpKit\WordPress\ThemeResolution\ThemePreviewContextProvider;

$contextProvider = new ThemePreviewContextProvider(
    $previews,
    static function (): ?string {
        return function_exists('deploy_kit_wordpress_current_slot_id')
            ? deploy_kit_wordpress_current_slot_id()
            : null;
    }
);

$runtime = new ThemeResolutionRuntime(
    pwk()->themes(),
    static fn () => $contextProvider->context(
        new ThemeContext([
            // user/request/content context...
        ])
    )
);

$runtime->register();
~~~

未知のID・未選択・adapter未導入の場合はTheme overrideを行わず、WordPress標準Themeへfall throughします。

管理previewは独立した Theme switching engine ではありません。preview.theme が高priority ruleへ入り、通常の user / route / content rule と同じ Resolver で競合解決されます。

## Deploy Kit WordPress Deployment Bridge

Deploy Kit WordPress Deployment は optional filter deploy_kit_wordpress_review_target を提供します。

Site Core / Application Plugin は現在の logical preview target を site-specific deployment へ変換します。

~~~php
add_filter(
    'deploy_kit_wordpress_review_target',
    function ($current, array $deployments) use ($selection, $previews) {
        $id = $selection->currentId();
        if ($id === null) {
            return null;
        }

        $deploymentMap = [
            'base-review' => 'project-test',
            'slot-01' => 'project-test-slot-01',
        ];

        if (!isset($deploymentMap[$id])) {
            return null;
        }

        $preview = $previews->get($id);

        return [
            'id' => $id,
            'label' => $preview?->label() ?? $id,
            'deployment' => $deploymentMap[$id],
            'lock' => true,
        ];
    },
    10,
    2
);
~~~

この mapping は環境policyなので WP-Kit Core には置きません。

## Deploy UI Behavior

lock: true の review target がある場合、Deploy Kit WordPress Deployment は:

1. 現在の Review Target ID / label を表示する
2. 対応する Broker-authorized deployment を自動選択する
3. deployment selector をその target にロックする
4. Preflight / Dry Run / Deploy を同じ target に対して行う
5. submit 時にも current review target を再評価する
6. POST を改ざんして別deploymentを指定した場合は拒否する

したがって、管理者が slot-01 をレビューしている間は、管理画面からのDeployも slot-01 に対応するdirectoryへ向きます。branch は deployment policy の範囲内で従来どおり指定できます。

## Base / Slot Semantics

Slot は WP-Kit 固有概念ではありません。generic Slot Modelでは Slot は logical selectable workspace/target identity であり、Theme・deployment・directoryを内包しません。

WP-Kit Core は Slot を特別な型として持たず、logical ID を ThemeTarget へ結び付ける Theme binding 側として動作します。

base-review / slot-01 / slot-02 / member-theme-check / campaign-preview は、すべて logical preview target ID として同じです。

同様に deploy-kit Core にとって project-test / project-test-slot-01 / project-test-slot-02 は単なる deployment identifiers です。

## Safety

- browser/request input から filesystem path を生成しない
- WP-Kit preview target は事前登録制にする
- deploy-kit deployment mapping は Application Plugin の固定policyにする
- Deploy Kit Plugin は Broker allowlist に存在しない mapping を fail closed する
- locked review target 中は別deploymentへの forged POST を拒否する
- production authorization は review target mapping で迂回できない
- branch allowlist / confirmation / Broker policy / deploy-kit safety は従来どおり最終authorityとする

## Extension

同じ契約は Theme review 以外にも使えます。Application Plugin が「現在レビュー中のPlugin sandbox」等を論理targetとして管理する場合も、deploy-kit側は同じ review target contract を利用できます。

そのため Deploy Kit WordPress Deployment 側の契約名は Theme/Slot 固有にせず Review Target とします。
