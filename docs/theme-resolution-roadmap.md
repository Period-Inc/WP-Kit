# Theme Resolution Roadmap

Theme Resolutionの拡張は、用途ごとに独立したTheme switching実装を増やさず、すべて同じ `ThemeResolver → ThemeTarget` pipelineへ統合します。

## Foundation

Status: implemented in the Theme Resolution foundation.

- ThemeTarget
- ThemeContext
- priority-based ThemeResolver
- ThemeResolution provenance
- request-scoped WordPress ThemeResolutionRuntime
- template / stylesheet application
- Theme Mods / Custom CSS settings source remap
- `pwk()->themes()` entrypoint
- early-bootstrap requirement

## 1. Management Theme Comparison

Purpose:

管理者が同一WordPress Installation上で複数Theme treeを切り替えて比較する。

Status:

- implemented: ThemePreviewRegistry
- implemented: ThemePreviewTarget
- implemented: request-local ThemePreviewSelection
- implemented: preview logical ID → shared ThemeResolver rule
- documented: Deploy Kit WordPress Deployment Review Target bridge
- remaining: capability/nonce付き管理UI
- remaining: per-session/per-user persistence adapter
- remaining: Baseへの独立recovery UI
- remaining: current resolution diagnostics

Required application adapter:

- registered preview targets
- capability gate
- per-session/per-user preview key
- Baseへの独立recovery path
- current resolution diagnostics

Resolver input example:

- `preview.theme = slot-01`

This adapter must not accept filesystem paths from request values.

Deploymentとの結合は行わない。対象Theme directoryがdeploy-kit等で配置されていても、preview adapterはTheme identifierだけを扱う。

## 2. User / Role Theme Resolution

Purpose:

user identity / role / capability / membership等によってThemeを切り替える。

Required context provider:

- user ID
- roles
- explicit capabilities/attributes needed by the application

Resolver input examples:

- `user.id`
- `user.roles`
- `user.segment`

Ruleはbusiness/application policyとしてsite側が登録する。

WP-Kit Coreは「どのroleならどのTheme」というproduct-specific policyを持たない。

## 3. Request / Route Theme Resolution

Purpose:

host/path/route等、Theme bootstrap前に取得できるrequest情報でThemeを切り替える。

Required context provider:

- host
- normalized path
- query facts explicitly allowed by site policy

Resolver input examples:

- `request.host`
- `request.path`

raw request inputをTheme directory pathへ直接変換しない。

## 4. Post / Post Type Theme Resolution

Purpose:

特定postまたはpost typeに応じてThemeを切り替える。

Required component:

**Early Content Locator**

Theme選択時点では通常のmain queryが未成立の可能性があるため、requestから対象contentをTheme bootstrap前に特定するadapterを用意する。

Resolver input examples:

- `content.post_id`
- `content.post_type`

Content LocatorとTheme Resolverは分離する。

## 5. Taxonomy / Term Theme Resolution

Purpose:

taxonomy / term条件によってThemeを切り替える。

Early Content Locatorまたは専用Request Locatorが、Theme bootstrap前にtaxonomy contextを解決する。

Resolver input examples:

- `content.taxonomy`
- `content.term_id`
- `content.terms`

通常の `is_tax()` が利用できることを前提にしない。

## 6. Diagnostics

Theme Resolutionが複雑になっても判断理由を追跡できるよう、管理者向けdiagnosticsを用意する。

表示候補:

- resolved template
- resolved stylesheet
- settings stylesheet
- matched rule ID
- matched priority
- relevant sanitized context
- native WordPress Themeへfallbackしたか

DiagnosticsはTheme選択のauthorityではなく、Resolver判断の観測面とする。

## Priority Model

用途を別engineに分けず、priorityで競合を解決する。

Typical order:

1. emergency/admin preview
2. explicit user override
3. user/role/application segment
4. request/route
5. post/post type/taxonomy/term
6. no match → native WordPress Theme

数値priorityはsite/applicationが決定する。WP-Kitは順序規則だけを提供する。

## Architectural Rule

新しいTheme切替要件が出た場合、まず次を判断する。

1. 新しいTheme Resolverが必要か？
2. それとも既存Resolverへ渡す新しいcontext/provider/ruleで表現できるか？

原則は **後者**。

Theme切替の用途ごとに別のglobal option操作、別cookie selector、別template filterを作らない。
