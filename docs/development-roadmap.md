# Development Roadmap

## Phase 1 — Core Infrastructure

**Status:** Done

- Application singleton (`pwk()`)
- HookRegistrar, ShortcodeRegistrar
- ScriptStyleRegistrar (assets)
- PostTypeRegistrar + MetaBox
- StartHtmlRenderer, BodyRenderer, EndHtmlRenderer, DocumentRenderer
- SiteInfo, TitleResolver, TemplateFormatter
- ImageTagRenderer, PageNavigationRenderer
- Condition (isPostType / isUser)
- Translator

---

## Phase 2 — Support Layer

**Status:** Done

- Element (HTML builder, shorthands, array children)
- HtmlTemplate (mustache-style templating)
- ArrayUtil, Encoding, CssName, ImageUtil, LineEnding
- Calendar + CalendarDay
- Locale (WeekdayName, MonthName, BloodType, Zodiac)

---

## Phase 3 （Progress: 80%） — WordPress Application Features

**Status:** In progress

**Current focus:** PostAssets / Asset Access Control / Relation

### Done

- [x] PostMetaManager — get / set / has、WordPress なしは noop

### Current Status

Foundation completed:

- PostMetaManager
- PostAssets compile pipeline
- Renderer / CompileService separation
- semantic tooling (`context` / `impact`)

Current implementation priorities:

1. Asset Access Control
2. Relation
3. PostAssets productization

### TODO

#### Content / Meta

- [ ] **Post Assets** — 投稿単位で CSS/JS を管理する

  Foundation:
  - [x] PostMetaManager integration
  - [x] PostAssets accessor layer
  - [x] PostAssetsRenderer
  - [x] enqueue / inline responsibility split
  - [x] csscode_minified → csscode_compiled fallback
  - [x] PostAssetsCompilerInterface
  - [x] NullPostAssetsCompiler
  - [x] ScssPhpPostAssetsCompiler
  - [x] PostAssetsCompileService
  - [x] MetaBox save → compile integration
  - [x] renderer / compile integration tests

  Remaining:
  - [ ] **Post Asset Attachment** — postmeta を attachment relation として CSS / JS / file / code を投稿へ関連付ける
    - postmeta はコード本体ではなく attachment の参照・関係を保持する
    - inline / file の両方を扱えるようにする
    - 投稿単位で必要な asset のみ enqueue / render する
    - source と生成物を分離し、保存先を filesystem / CPT / media 等へ差し替え可能にする
    - SCSS / Sass source → compile/render → CSS artifact → post attach の一連の pipeline を提供する
    - source 更新時のみ再 compile/render できるようにする
    - 複数 post からの asset 共有、dependency、version / cache busting は後続拡張とする
    - Object History と接続し、source code の変更履歴を opt-in で保持できる設計を検討する
  - [ ] wp_head / wp_footer auto integration
  - [ ] actual MetaBox field definitions
  - [ ] CodeMirror editor integration
  - [ ] compile error admin UI
  - [ ] minify implementation
  - [ ] asset dependency management
  - [ ] asset version strategy
  - [ ] frontend cache strategy
  - [ ] asset preload / defer strategy
  - [ ] frontend output documentation
  - [ ] examples / starter templates

- [ ] **Object History** — WordPress オブジェクトの選択的な変更履歴を保存する
  - デフォルトは無効（opt-in / default false）
  - 対象を明示的に指定した場合のみ履歴を保存する
  - 対象候補: 特定 post_id / post_type・CPT / meta_key / taxonomy / taxonomy terms / user meta / term meta
  - meta に限定せず、taxonomy term の付け替え等も扱える汎用履歴基盤として設計する
  - 保存候補: 変更前値 / 変更後値 / 更新日時 / 更新ユーザー / object type・ID / key / 操作種別（add / update / delete）
  - 管理画面で時系列参照・差分表示を可能にする
  - 過去値への復元は履歴基盤と分離可能な機能として検討する
  - 履歴削除機能を持つ: 個別削除 / 対象単位の全削除 / 指定期間以前の削除
  - 保存期間による自動 purge は後続拡張候補
  - WooCommerce / ACF 等の大量更新を無条件に記録しない

- [ ] **Relation** — post type 間の親子関係
  - 親/子 post_id を保持するメタフィールド
  - 管理画面の親/子リンク UI
  - 複数 post type 間の双方向参照

- [ ] **SiteData** — HTML コードスニペットの挿入
  - 別 Post やショートコードから HTML 断片を挿入
  - head / body open / footer へのインジェクションポイント

- [ ] **Featured Post** — チェックした投稿を一覧化
  - 管理画面でチェックボックスによる「おすすめ」フラグ
  - WP_Query 連携

- [ ] **Breadcrumb** — パンくずリスト生成
  - 投稿タイプ・taxonomy・階層ページに対応

#### Access / Security

- [ ] **Asset Access Control** — `wp-content` 以下のアセットアクセス制限

  Foundation:
  - [x] AssetAccessPolicyInterface
  - [x] AssetAccessResult
  - [x] AssetRequestContext
  - [x] AssetDeliveryInterface
  - [x] AssetStorage abstraction
  - [x] AssetAccessManager / policy factory composition
  - [x] Settings repository / form handler / settings page renderer
  - [x] WordPress hook registrar / runtime installer foundation
  - [x] Runtime defaults / plugin bootstrap composition

  Runtime safety notes:
  - `protected-uploads` に置くだけでは安全ではない
  - protected path への直アクセスを拒否する direct access protection が必要
  - outside webroot strategy は最も安全な推奨構成
  - shared hosting では `protected-uploads` + deny/rewrite を fallback とする
  - VPS / 専用環境では outside webroot を推奨
  - runtime health check layer で危険な設定を検出する
  - rewrite-only strategy は warning として扱う
  - 対応方針:
    - rewrite interception
    - deny direct access
    - outside webroot

  Runtime diagnostics:
  - [x] Direct access protection health check
  - [x] Outside webroot strategy health check
  - [x] Filesystem inspector abstraction
  - [x] private asset root diagnostics
  - [x] health reporter runtime composition
  - [x] settings page health section rendering

  Default policies:
  - [x] WordPress role policy
  - [x] logged-in user policy
  - [x] public / private policy
  - [x] expiration policy
  - [x] arbitrary callback policy
  - [x] multiple policy composition

  Delivery:
  - [x] PHP proxy delivery
  - [x] protected image delivery
  - [x] protected PDF delivery
  - [x] protected video delivery
  - [x] streamed file delivery foundation
  - [ ] signed URL strategy production integration
  - [ ] direct file protection strategy production integration
  - [ ] nginx / apache compatibility strategy production integration

  Production Delivery Backend:
  - [ ] X-Sendfile support
  - [ ] X-Accel-Redirect support
  - [ ] large PDF/video delivery optimization
  - [ ] streaming backend abstraction
  - [ ] PHP body delivery fallback

  Filesystem Auto Repair:
  - [ ] dry-run / execution mode separation
  - [ ] private root creation plan
  - [ ] permission diagnostics
  - [ ] explicit execution only
  - [ ] no automatic destructive operations

  Rewrite Guide Output:
  - rewrite auto-write は行わない
  - [ ] `.htaccess` guide output
  - [ ] nginx config guide output
  - [ ] copy/paste install flow
  - [ ] diff/review based operation

  Upload Channel Policy:
  - [ ] Public Upload
  - [ ] Protected Upload
  - [ ] upload channel based policy
  - [ ] upload workflow separation

  Media Category Access Policy:
  - [ ] attachment taxonomy integration
  - [ ] category based access policy
  - [ ] role-based category access
  - [ ] reserved protected media category
  - [ ] protected category internal slug reservation

  Policy Resolution Priority:
  1. attachment explicit
  2. upload channel
  3. media category
  4. global default

  WordPress integration:
  - [x] Media Library protected state column foundation
  - [x] attachment meta integration foundation
  - [x] settings admin page foundation
  - [x] upload interception pipeline foundation
  - [ ] upload restriction production support
  - [ ] role-based configuration UI polish

  REST API Integration:
  - [ ] protected media API
  - [ ] signed URL issuance
  - [ ] headless integration
  - [ ] JWT/session integration possibility
  - [ ] REST adapter layer

  External Auth / Subscription Integration:
  - Status: deferred / pending
  - [ ] WooCommerce
  - [ ] Stripe
  - [ ] membership plugins
  - [ ] external identity provider

  Constraints:
  - [ ] Headless WP Support より優先度を高く扱う
  - [ ] 通常テーマ利用時にも副作用が出ないようにする
  - [ ] uploads 直接公開前提を壊さない
  - [ ] runtime dependency を最小限に保つ

#### Mail / Delivery

- [ ] **Mail Queue foundation** — Provider非依存のメール配送基盤
  - [x] architecture / responsibility boundary documented
  - [x] MailMessage / value objects
  - [x] Queue / Scheduler / Transport / AttachmentStore / RetryPolicy contracts
  - [ ] Repository contract / persistent Queue entity
  - [x] QueueStatus / DeliveryStatus separation
  - [x] TransportResult / RetryPolicy
  - [x] WpMailTransport
  - [x] WpMailContext recursion guard
  - [x] wp_mail compatibility interceptor primitive
  - [x] WpMailMessageMapper
  - [x] attachment / embed normalization contract
  - [x] WordPress-free smoke tests
  - [ ] full PHPUnit suite
  - [ ] WP-Kit Mail Application Plugin側へ永続Queue runtimeを分離
  - [ ] Action Scheduler integrationはApplication Plugin側で実装
  - [ ] provider transportsはfoundation完成後に追加

  Architecture: `docs/mail-queue.md`

#### Search / Query

- [ ] **TermSearch** — taxonomy term の AND/OR 検索・絞り込み
  - 複数 taxonomy をまたいだ絞り込み
  - AND / OR モード切り替え

- [ ] **Posts shortcode** — `[posts]` ショートコード
  - post_type / taxonomy / 件数などをパラメータで指定
  - 出力テンプレートを差し替え可能

#### Rendering / Theme

- [ ] **ThemeImage** — テーマフォルダ内アセットアクセス
  - テーマ内画像の URL / パス解決ヘルパー
  - 存在チェック付き

- [ ] **Include shortcode** — `[include]` ショートコード
  - テンプレートパーツをショートコードで埋め込む

#### Calendar / Data

- [ ] **Calendar WP 展開** — Support\Calendar をスケジュール表として WordPress で使う
  - 投稿をカレンダー上にマッピング
  - WP クエリと Calendar::month() の統合ヘルパー

#### Admin / UX

- [ ] **Admin UI** — 本機能群の管理画面 view 整理
  - Phase 3 各機能の管理画面コンポーネントを統一

- [ ] **旧 wpcf-shortcodes の再設計**
  - Legacy ショートコード群を現行アーキテクチャに移植
  - HookRegistrar ベースで再実装

---

## Notes

- Phase 3 の各機能は PostMetaManager を基盤として構築する
- WordPress なし環境での動作（noop / 空返却）を維持する
- HTML 生成は Element / View 層に委譲し、Renderer クラスはデータのみ扱う
- Legacy コードは編集しない（新規クラスとして並行実装）

### Pending: Infrastructure/Shortcode の取捨選択

`src/Infrastructure/Shortcode/*` は現時点では移動せず、後続で取捨選択する。

対象:

- `ButtonShortcode.php`
- `FetchTitleShortcode.php`
- `TemplateUrlShortcode.php`
- `ShortcodeInterface.php`

整理方針:

- `ShortcodeInterface` は WordPress FW基盤として残す可能性が高い
- `ButtonShortcode` / `FetchTitleShortcode` / `TemplateUrlShortcode` は実用ショートコード集またはサンプル扱いとして再分類する
- 候補は `src/WordPress/Shortcodes/` または `src/Examples/Shortcodes/`
- Relation 実装を優先し、この項目は後続タスクとする

---

### Pending: Editor UI strategy

PostAssets の `csscode` / `jscode` 編集UIは、通常の textarea を CodeMirror 化する方針とする。

対象:

- `csscode`
- `jscode`
- Sass / SCSS 編集
- JavaScript 編集

方針:

- 保存値は textarea / post meta と同期する
- CodeMirror は編集UIとして利用し、保存形式には依存させない
- CSS / SCSS / JavaScript の syntax highlight、indent、括弧対応、validation を検討する
- 管理画面での読み込み負荷を抑えるため、PostAssets の編集画面に限定して enqueue する
- リッチエディタではなくコードエディタとして扱う

#### Monaco Editor Strategy

WordPress 本体のメインエディターについては、ソース編集モードに Monaco Editor を導入する方向で検討する。

対象:

- 投稿本文の HTML / ブロックソース編集
- テーマ制作用途での高度なソース編集
- 将来的な validation / formatting / search / replace

方針:

- WordPress の通常編集体験は壊さない
- Monaco Editor はソース編集用UIとして限定的に導入する
- 保存データ構造には依存させない
- Gutenberg / Classic Editor との接続方法を別途検証する

---

### PostAssetsRenderer foundation

DONE:

- PostAssetsRenderer
- enqueue / inline responsibility split
- csscode_minified → csscode_compiled fallback
- ScriptStyleRegistrar integration

### DONE: MetaBox ↔ PostAssets compile integration

- MetaBox save 時に `csscode` 保存後、`PostAssetsCompileService::compileCss()` を呼ぶ
- compile source は DB 再読込ではなく、保存処理中の入力値を使う
- `post_assets_compile_service` option 経由で任意注入
- `csscode` 以外では compile しない
- compile failure 時も MetaBox save flow は継続
- PHPUnit integration tests 追加済み

---

### DONE: period-wp-kit-agent semantic tooling

Commands:

- roadmap
- architecture
- context <topic>
- impact <topic>

Capabilities:

- project-aware context packing
- architecture-aware impact guidance
- AI implementation boundary guidance
- warning continue strategy for missing files

Impact guidance:

- implementation files
- integration points
- tests
- documentation
- architecture constraints

---

## Phase 4 — Headless WP Support

**Status:** Planned

### Goal

WordPress を Headless CMS として使う場合に、REST API / GraphQL / frontend preview / media / revalidation を扱いやすくする。

### TODO

- [ ] **Headless API Profile**
  - post_type ごとに API 出力フィールドを定義する
  - meta / taxonomy / featured image / relation を整形する
  - REST API の `_fields` 相当の軽量レスポンスを作る

- [ ] **Media Transformer**
  - featured image / gallery / srcset / alt / caption を JSON 化する
  - frontend 側で扱いやすい画像オブジェクトを生成する

- [ ] **Headless Preview**
  - 下書き・予約投稿・未公開投稿の preview token を発行する
  - frontend preview URL を生成する
  - token の期限管理を行う

- [ ] **Revalidation Hook**
  - post 保存時に webhook を送信する
  - Next.js / Astro / Nuxt などの再生成通知に対応する

- [ ] **Relation API Adapter**
  - `relation_parent` / `relation_children` を API 向けに展開する
  - ID だけでなく title / slug / link などを返せるようにする

- [ ] **SiteData API**
  - headless frontend 用の共通設定 JSON を返す
  - site name / logo / nav / snippets / theme assets を扱う

- [ ] **Headless Route Helper**
  - frontend URL と WordPress permalink の対応を管理する
  - slug / post_type / taxonomy から frontend URL を生成する

- [ ] **Auth Helper**
  - Application Passwords 利用時の接続確認を補助する
  - REST API 権限チェックを補助する

### Notes

- WordPress 本体の REST API を壊さず、必要な出力整形を追加する
- WPGraphQL 連携は後続オプションとする
- Headless 機能は WordPress 通常テーマ利用時にも副作用が出ないようにする
- Phase 3 の Asset Access Control を優先し、Headless WP Support はその後に実装する


### DONE: Asset Access Control streamed file delivery

- FileStreamInterface を追加
- NativeFileStream を追加
- StreamedAssetDelivery を追加
- AssetStorage / AssetAccessManager / Delivery を接続
- readfile / fopen / passthru は使わず、file_get_contents ベースに限定
- 実ファイル delivery の foundation tests 追加済み
