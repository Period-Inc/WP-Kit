# Theme Resolution

## Purpose

WP-Kit Theme Resolution は、1つの WordPress Installation で **その request がどの Theme を使用するか** を決定するための共通基盤です。

Deployment は扱いません。Theme のファイルがどのように配置されたか、どの Git branch / commit から来たか、どの deployment tool が展開したかは Theme Resolution の責務外です。

逆に deploy-kit は Theme の意味を解釈せず、許可された directory へ repository content を展開するだけです。

## Responsibility Boundary

```text
deploy-kit
└─ repository / resolved commit
   → configured directory

WP-Kit Theme Resolution
└─ request context
   → rule evaluation
   → ThemeTarget(template / stylesheet / settings source)
   → WordPress Theme selection
```

この境界により、Theme Resolution は deploy-kit がなくても利用でき、deploy-kit も Theme 以外の directory deployment にそのまま利用できます。

## Intended Uses

同じ Resolver 基盤で、少なくとも次を扱います。

- 管理者が複数 Theme を比較する preview override
- user / role / capability による Theme 切替
- request path / host / route による Theme 切替
- post / post type による Theme 切替
- taxonomy / term による Theme 切替
- campaign / experiment 等の条件付き Theme 切替
- development/test 用に別 directory へ展開した Theme tree の一時 preview

Test Slot は Theme Resolution の特殊概念ではありません。

別 directory に存在する Theme tree を管理者 preview rule が選択することで、結果として Test Slot のように利用できます。

## Core Model

### ThemeTarget

Themeとして選択する値を表します。

- `template`: parent/base template identifier
- `stylesheet`: requestで使用する stylesheet/theme directory identifier
- `settings_stylesheet`: theme mods / Custom CSS 等の設定をどの stylesheet identity から読むか

同じコードの別treeを比較する場合は、例えば:

- template: `astra`
- stylesheet: `purimall-dk-slot-01`
- settings_stylesheet: `purimall`

とすることで、Theme codeだけを切り替えながら既存site設定を共有できます。

### ThemeContext

Ruleに渡す request-specific facts のbagです。

Coreはkeyの意味を固定しません。例:

- `preview.theme`
- `user.id`
- `user.roles`
- `request.host`
- `request.path`
- `content.post_id`
- `content.post_type`
- `content.taxonomy`
- `content.term_id`

サイト側またはadapterが、Theme選択前に取得可能な情報を入れます。

### ThemeResolver

複数Ruleをpriority順に評価します。

- priorityが高いRuleを先に評価する
- 同priorityは登録順
- Ruleが `ThemeTarget` を返した時点で確定
- 全Ruleが `null` の場合はWordPress標準Themeをそのまま利用

Ruleの典型的なpriority例:

1. emergency/admin preview
2. explicit user override
3. user/role rule
4. route/content rule
5. site default rule

priority値そのものはサイトが決定し、WP-Kitは固定しません。

## WordPress Runtime

`ThemeResolutionRuntime` はResolverの結果をWordPressへ適用します。

対象:

- `template`
- `stylesheet`
- Theme Mods
- Custom CSS

globalなactive Theme optionを書き換えず、request単位で解決します。

Theme Resolution Runtimeは **Themeがロードされる前** に登録される必要があります。

そのため、サイト成立条件として利用する場合は通常Themeの `functions.php` から起動してはいけません。MU Plugin / site bootstrap 等、Themeより前にロードされる層からWP-Kitを読み込みます。

## Content-Aware Resolution

post / post type / taxonomy / term条件にはタイミング上の注意があります。

WordPressはTheme codeをロードする前に `template` / `stylesheet` を解決するため、その時点では通常のmain queryやconditional tagsがまだ確定していない場合があります。

したがってWP-Kit Coreは:

- `is_singular()`
- `is_tax()`
- `get_queried_object()`

等をTheme Resolutionの必須前提にしません。

Content-aware routingを行うadapterは、Theme bootstrap前にrequestから対象contentを特定し、ThemeContextへ明示的に値を供給します。

このcontent lookup方式は別adapterとして設計・テストし、Resolver本体から分離します。

## Preview / Comparison

管理者比較機能はTheme Resolutionの上位機能です。

Foundationとして ThemePreviewRegistry / ThemePreviewTarget / ThemePreviewSelection を提供します。logical preview ID を登録済み ThemeTarget へ変換し、preview.theme を既存 ThemeResolver へ入力します。

preview selector/applicationは:

1. 登録済みThemeTargetだけを選択可能にする
2. arbitrary filesystem pathを受け付けない
3. 適切なcapabilityを要求する
4. user/session persistenceから得たlogical IDだけをThemePreviewSelectionへ渡す
5. Baseへ戻る経路をTheme codeから独立させる

capability / nonce / persistence / admin UI は Site Core / Application Plugin の責務です。

deploy-kitのdeployment名やfilesystem pathをTheme Resolution ruleへ直接渡しません。Deploy Kit WordPress Deploymentとのreview-target bridgeは docs/theme-resolution-deployment-integration.md を参照します。

## Deployment Integration

deploy-kitとの連携は疎結合です。

例:

```text
deploy-kit
  fanika-test2-slot-01
  → wp-content/themes/purimall-dk-slot-01/

WP-Kit Theme Resolution
  preview "slot-01"
  → ThemeTarget(
       template="astra",
       stylesheet="purimall-dk-slot-01",
       settings_stylesheet="purimall"
     )
```

WP-Kitは `fanika-test2-slot-01` というdeploymentの存在を知る必要がありません。

deploy-kitは `purimall-dk-slot-01` がThemeであることを知る必要がありません。

## Failure Behavior

Theme Resolutionはfail-safeを原則とします。

- Ruleが一致しない → WordPress標準Theme
- invalid ThemeTarget → 登録時/解決時に拒否
- preview対象が利用不能 → Baseへfallbackする上位preview adapterを用意
- Resolver例外でsite全体を壊さない運用はsite bootstrap側で考慮する

Theme switchingがサイト成立条件の場合でも、recovery pathは選択対象Themeのcodeに依存させないこと。

## Initial Implementation

Foundationでは以下のみを提供します。

- `ThemeTarget`
- `ThemeContext`
- priority付き `ThemeResolver`
- rule provenanceを保持する `ThemeResolution`
- WordPressへrequest単位で適用する `ThemeResolutionRuntime`
- Theme Mods / Custom CSS のsettings source remap
- ThemePreviewRegistry / ThemePreviewTarget
- request-local ThemePreviewSelection
- preview logical ID を既存Resolverへ接続するrule helper

次段階:

- 管理者Preview UI / persistence adapter
- user/role context provider
- request/path provider
- post/post type content locator
- taxonomy/term content locator
- diagnostics / current resolution inspector
