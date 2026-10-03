# Legacy Audit

調査対象: `src/Legacy/WP-Custom-Utility/` および `src/Legacy/WPCF/`

---

## Summary

| 項目 | 件数 |
|---|---|
| Total checked | 42 |
| Migrated | 22 |
| Partially migrated | 5 |
| Not migrated | 10 |
| Intentionally skipped | 5 |

---

## High Priority Candidates

| Legacy | 概要 | 現状 | 推奨 | 理由 |
|---|---|---|---|---|
| `WPCF/class.BreadCrumb.php` | パンくずナビ生成。taxonomy / archive / single / page 対応 | not migrated | 再設計 | ロードマップ記載済み。頻出ニーズ |
| `WPCF/class.Relation.php` | post type 間の親子関係定義・選択UI・meta保持 | not migrated | 再設計 | ロードマップ記載済み。MetaBox と連動 |
| `WPCF/class.TermSearch.php` | taxonomy term の AND/OR 検索・絞り込みフォーム | not migrated | 再設計 | ロードマップ記載済み。WP_Query 連動 |
| `WPCF/class.SiteData.php` | サイト共通データ (HTML断片/オプション) 管理 | not migrated | 再設計 | ロードマップ記載済み。Parts/Include と統合 |
| `WP-Custom-Utility/WP_CustomUtility_Parts.php` | 再利用可能コンテンツ断片 (Parts) の投稿タイプ＋ショートコード | not migrated | 再設計 | Include shortcode / SiteData の基盤になる |
| `WPCF/class.EventSchedule.php` | イベント投稿タイプ＋カレンダー／スケジュール表示・日付範囲クエリ | partially migrated | Calendar Engine へ再設計 | date/start/end、複数event/day、start_of_week、date route 等を要求として継承。post_date と schedule time の結合は継承しない |

---

## Medium Priority Candidates

| Legacy | 概要 | 現状 | 推奨 | 理由 |
|---|---|---|---|---|
| `WP-Custom-Utility/WP_CustomUtility_Posts.php` | `[posts]` ショートコード。クエリ＋ページネーション＋サムネイル | not migrated | 再設計 | ロードマップ記載済み |
| `WP-Custom-Utility/WP_CustomUtility_Admin.php` | 管理画面の行アクションに ID 表示 | not migrated | 再設計 | 小規模。Admin UI フェーズに含める |
| `WPCF/class.ThemeImage.php` | テーマ内画像アセットのパス/URL解決 | not migrated | 再設計 | ロードマップ記載済み。小規模 |
| `WPCF/class.CustomOption.php` | サイト設定オプションの get/set ラッパー | not migrated | 再設計 | `get_option` / `update_option` の薄いラッパー。シンプルに実装可能 |
| `WPCF/class.ArchivePageParts.php` | アーカイブページのヘッダー/フッターコンテンツ管理 | not migrated | 再設計 | SiteData / Parts と統合可能 |

---

## Low Priority Candidates

| Legacy | 概要 | 現状 | 推奨 | 理由 |
|---|---|---|---|---|
| `WPCF/lib/class.PublicHoliday.php` | 日本の祝日判定 (静的データ＋Holiday クラス連携) | not migrated | Legacy に残す | 祝日データのメンテが重い。外部パッケージ推奨 |
| `WPCF/class.ImageInfo.php` | 画像サイズのキャッシュ・取得 | not migrated | docs/example扱い | WP の `wp_get_attachment_image_src` で代替可能 |
| `WPCF/class.PageHeader.php` | 投稿タイプ別ページヘッダー管理 (TinyMCE付き管理サブメニュー) | not migrated | 再設計 | SiteData / Parts で代替設計可能 |
| `WPCF/class.CustomCommentField.php` | コメントへのカスタムフィールド追加 | not migrated | Legacy に残す | コメント機能は対象外 |
| `WPCF/class.MediaBox.php` | メタボックス内メディア選択UI | partially migrated | Legacy に残す | MetaBox の `image` / `media` フィールドで代替 |
| `WPCF/lib/class.Kana2Romaji.php` | かな→ローマ字変換 | not migrated | Legacy に残す | 用途が限定的。必要なら独立パッケージ化 |

---

## Skip / Keep in Legacy

| Legacy | 概要 | 現状 | 推奨 | 理由 |
|---|---|---|---|---|
| `WPCF/class.WelcartUtility.php` | Welcart EC プラグイン連携 | intentionally skipped | Legacy に残す | サードパーティプラグイン依存。スコープ外 |
| `WPCF/class.RelocateUpload.php` | メディアアップロード先のカスタマイズ | intentionally skipped | Legacy に残す | 用途が非常に限定的 |
| `WPCF/class.RemoteSiteContentSummary.php` | 外部サイトのHTMLスクレイピング | intentionally skipped | 削除候補 | セキュリティリスク。外部ライブラリで代替 |
| `WP-Custom-Utility/Content_Services.php` | Google Fonts / Material Icons ショートコード | intentionally skipped | 削除候補 | 外部CDN依存。現行では不要 |
| `WPCF/class.Debug.php` | デバッグユーティリティ (空のプレースホルダー) | intentionally skipped | 削除候補 | 中身なし |

---

## Mapping

現行クラスへの対応表。

| Legacy feature | Current replacement | Status |
|---|---|---|
| `WP_CustomUtility_PostType_Object` | `PostTypeRegistrar::register()` | migrated |
| `WP_CustomUtility_Taxonomy` | `PostTypeRegistrar::registerTaxonomy()` | migrated |
| `WP_CustomUtility_MetaBox` | `MetaBox` | migrated |
| `WPCF/class.MetaBox.php` | `MetaBox` | migrated |
| `WPCF/class.PostType.php` | `PostTypeRegistrar` | migrated |
| `WPCF/class.Taxonomy.php` | `PostTypeRegistrar::registerTaxonomy()` | migrated |
| Post meta CRUD (各所) | `PostMetaManager` | migrated |
| `CustomUtility_HTML` | `Element` + `RawHtml` | migrated |
| `WP_CustomUtility_Template` (HTML生成部) | `DocumentRenderer` / `StartHtmlRenderer` 等 | migrated |
| Script/style 登録 | `ScriptStyleRegistrar` | migrated |
| Hook / shortcode 登録 | `HookRegistrar` / `ShortcodeRegistrar` | migrated |
| `CustomUtility_Date` (基本) | `Date` (Support) | migrated |
| `CustomUtility_Date::get_calendar_index()` | `Calendar::month()` | migrated |
| Preset data (zodiac / blood type / month-day names) | `Locale\WeekdayName` / `MonthName` / `BloodType` / `Zodiac` | migrated |
| `CustomUtility_HTTP` / `CustomUtility_HTTPCookie` | `HttpClient` / `HttpResponse` / `CookieJar` | migrated |
| `CustomUtility_URL` | `Url` | migrated |
| `WP_CustomUtility_Template` (TemplateFormatter) | `Support\TemplateFormatter` | migrated |
| `WP_CustomUtility_Template` (タイトル) | `TitleResolver` | migrated |
| `WP_CustomUtility_Template` (サイト情報) | `SiteInfo` | migrated |
| `WP_CustomUtility_Template` (画像) | `ImageTagRenderer` | migrated |
| `WP_CustomUtility_Template` (ページナビ) | `PageNavigationRenderer` | migrated |
| Post class / nav menu class | `PostClassEnhancer` / `NavMenuClassEnhancer` | migrated |
| Tax query | `TaxQueryParser` | migrated |
| `Accessor_Functions` (`html_*` 関数群) | `Element` 静的メソッド群 | migrated |
| `Accessor_Functions` (`is_user()`) | `Condition::isUser()` | migrated |
| `Accessor_Functions` (`is_specific_post_type()`) | `Condition::isPostType()` | migrated |
| `Accessor_Functions` (`my_print_r()`, `__p()`) | — | intentionally skipped |
| `WPCF/lib/class.Date.php` | `Date` (Support) + `Calendar` | migrated |
| `WP_CustomUtility_MetaBox` (radio / posts フィールド) | `MetaBox` (一部未実装) | partially migrated |
| `WP_CustomUtility_MetaBox` (multiplier) | `MetaBox` repeater フィールド | partially migrated |
| `ScheduleCalendar` (DEPRECATED) | `Calendar Engine` の legacy requirement source | partially migrated |
| `EventSchedule` | `Calendar Engine` + WordPress adapter（再設計中） | partially migrated |
| `WP_CustomUtility_Parts` | — (SiteData / Include shortcode として再設計予定) | not migrated |
| `BreadCrumb` | — | not migrated |
| `Relation` | — | not migrated |
| `TermSearch` | — | not migrated |
| `SiteData` | — | not migrated |
| `WP_CustomUtility_Posts` (posts shortcode) | — | not migrated |
| `CustomOption` | — | not migrated |
| `ThemeImage` | — | not migrated |
| `WelcartUtility` | — | intentionally skipped |
| `RelocateUpload` | — | intentionally skipped |
| Third-party libs (Mobile-Detect, SimplePie, phpQuery, Zend) | Composer 依存として切り出し | intentionally skipped |

---

## Notes

ロードマップ Phase 3 に追加すべき候補（優先度順）:

- **BreadCrumb** — Legacy `WPCF/class.BreadCrumb.php` を再設計。taxonomy / archive / page 階層に対応
- **Relation** — `WPCF/class.Relation.php` を再設計。MetaBox と PostMetaManager を基盤に使う
- **TermSearch** — `WPCF/class.TermSearch.php` を再設計。TaxQueryParser と連動
- **SiteData / Parts** — `WP_CustomUtility_Parts` + `WPCF/class.SiteData.php` を統合再設計。Include shortcode の基盤
- **Posts shortcode** — `WP_CustomUtility_Posts::sc_posts()` を再設計。WP_Query + Renderer ベースで
- **CustomOption** — `WPCF/class.CustomOption.php` のシンプルな再設計。`get_option`/`update_option` ラッパー
- **ThemeImage** — `WPCF/class.ThemeImage.php` を小規模再設計。テーマアセットパス解決
- **Calendar Engine** — `EventSchedule` / obsolete `ScheduleCalendar` を requirement source として参照し、time core / calendar projection / WordPress repository / display projection を分離して再実装
- **MetaBox radio / posts フィールド補完** — 現行 MetaBox の未実装フィールド型を追加
- **Admin UI** — `WP_CustomUtility_Admin` (ID表示) など管理画面ユーティリティを Phase 3 Admin UI に含める
- **PublicHoliday** — 必要な場合は独立パッケージとして切り出し（本体には含めない）
