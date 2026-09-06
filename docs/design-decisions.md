# 設計判断の記録

このドキュメントは、コードを読んでもわかりにくい「なぜそうしたか」を記録する。

---

## `$_POST` 依存を分離した理由

**結論:** `save()` に `$postData` 引数を追加し、渡されたデータを優先する。

`$_POST` をそのまま読むと、テストがグローバル状態の書き換えに依存する。引数で注入できれば `$_POST` を汚さずに `save()` の挙動を検証できる。実運用では引数を省略すれば `$_POST` にフォールバックするため、既存コードへの影響はない。

---

## MetaBox は WordPress 依存のままでよい理由

**結論:** WordPress 関数を抽象化しない。`function_exists()` ガードで十分。

MetaBox の責務は WordPress の `add_meta_box` / `save_post` / `update_post_meta` と直接連携することであり、これを抽象化しても得るものが少ない。テスト用のモックは `tests/bootstrap.php` に定義した関数スタブで対応できる。インターフェースや DI コンテナを導入するコストに見合わない。

---

## JS を PHP から切り離した理由

**結論:** `assets/js/period-wp-metabox.js` に移動し、PHP ヒアドキュメントを削除した。

PHP ファイル内の JS はエディタ補完・構文チェック・差分管理のすべてで不利。外部ファイルにすることでそれぞれのツールチェーンが正常に機能する。JS の変更だけでも PHP キャッシュが無効化される問題も解消する。

---

## URL を内部で決めない理由

**結論:** `apply_filters('period_wp_metabox_js_url', null)` で呼び出し側に委ねる。

ライブラリはプラグインではなくテーマや任意ディレクトリから使われる。`plugins_url()` はプラグインディレクトリ前提のため使えない。`get_stylesheet_directory_uri()` はテーマ固有。いずれも汎用的に正しい URL を返せないため、URL の解決責任を呼び出し側に移す。URL が未設定なら enqueue をスキップし、エラーは出さない。

---

## `currentPostType` の問題を即修正しなかった理由

**結論:** 挙動をテストで固定し、修正は保留した。

`PostTypeRegistrar` は `metaBox()` が `post_type` を省略した場合に `register()` で最後に登録した投稿タイプを引き継ぐ。この「暗黙の状態引き継ぎ」はわかりにくいが、既存の利用コードに依存している可能性がある。挙動を変えると破壊的変更になるリスクがあるため、まず現在の挙動をテストで文書化した。テストで固定することで将来の変更時に意図せぬリグレッションを検知できる。

---

## 「直すのではなくテストで固定した」判断

**結論:** 挙動が正しいか判断できない箇所はまずテストで現状を記録する。

コードを直すと「現状が正しかった」場合に既存動作を壊す。テストで現状を記録しておけば、将来「直す」判断をしたときに差分が明確になり、意図しない変更を防げる。これは暫定回避ではなく、影響範囲が不明な変更を安全に扱う標準的な手順。

---

## SiteInfo 導入の意図

**結論:** WordPress の `bloginfo` / `home_url` などの取得処理を直接呼び出さず、Infrastructure 層にラップして統一的に扱う。

- WordPress 依存は Infrastructure 層に閉じる。Support 層には WordPress 依存を入れない
- HTML 生成は行わず、値の取得のみを責務とする
- エスケープは呼び出し側に委ねる
- WordPress 関数が存在しない環境でも安全に動作する（`function_exists()` ガード）

**fallback ポリシー:**

| メソッド | fallback |
|---------|---------|
| `name` / `description` / `url` / `themeUri` | `''` |
| `charset` | `'UTF-8'` |
| `language` | `'en'` |

**分離の理由:** `SiteInfo` と将来の `TitleResolver` を分離することで、サイト固有情報（静的）とリクエスト依存情報（動的）の責務を明確に分ける。

**今後の拡張候補:** `TitleResolver` の導入 / `TemplateFormatter` によるテンプレート整形 / `apply_filters` による最終出力のフック

---

## サイトコア連携に MU Plugin bootstrap を使う案

**状態:** 採用確度の高い候補。現時点では確定方針ではない。

WP-Kit が将来、個別サイトの成立条件に近い機能を担う場合、WP-Kit 全体をそのまま MU Plugin 化するのではなく、**サイト側の必須 bootstrap を MU Plugin とし、WP-Kit Core をそこから読み込む構成**を有力候補とする。

想定する責務分離は以下のとおり。

- **WP-Kit Core / reusable package**
  - 再利用可能な共通機能
  - サイトや有効化状態に依存しないライブラリ層
- **Site Core Bootstrap / MU Plugin**
  - サイトの成立に必須な初期化
  - 必須 hook、共通 API、権限・認証補助など
  - WP-Kit Core や必須 module のロード
- **Optional Feature / 通常 Plugin**
  - サイトごとに有無が変わる機能
  - 管理者が有効化・無効化できるべき機能
  - 外部連携や実験的機能
- **Theme**
  - template / view / UI / CSS / JS など表示責務

### この候補を有力と考える理由

通常 Plugin は管理画面から無効化できるため、無効化するとサイト自体の前提が崩れる機能を置く場所としては不安定である。一方 MU Plugin は常時ロードされるため、サイト固有の必須 runtime の bootstrap と相性がよい。

ただし、MU Plugin には通常 Plugin の activation / deactivation lifecycle がない。そのため migration やセットアップ処理まで無条件に MU Plugin へ寄せるのではなく、**「常時ロードされるべきもの」と「任意に導入・切替できるもの」を分離する**ことを前提とする。

### 採用判断の目安

以下が明確になった場合、この構成を正式採用する可能性が高い。

- WP-Kit がサイト固有の application runtime を継続的に担う
- 無効化されるとログイン、権限、注文、配信、共通 API 等の主要機能が成立しない
- Theme から業務ロジックを分離したい
- 複数サイトで WP-Kit Core 自体の再利用性を維持したい

逆に、WP-Kit が引き続き任意導入可能なライブラリとして完結する場合は、MU Plugin 化を前提としない。
