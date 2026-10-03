# MetaBox / Relation 設計仕様

## MetaBox 値の保存仕様

すべてのフィールドは配列として保存する。

```php
'value'   => ['value']
['a','b'] => ['a','b']
null      => [null]
''        => ['']
```

未定義と空文字は分ける。

```text
未定義 → default または null
空文字 → '' のまま保持
```

空文字を null に変換しない。

## normalizeFieldValue

```php
private function normalizeFieldValue(mixed $value): array
{
    if ($value === null) {
        return [null];
    }

    return (array) $value;
}
```

## Repeater 保存仕様

既存行の空値は保存し、新規末尾の空行だけ無視する。

```text
count(values) > existingCount
かつ
最後の行が空
```

行配列の場合は、行内のすべての値が `''` または `null` なら空行とみなす。

---

# Relation

## 目的

WordPress の Post 同士に、PostType の組み合わせで定義された意味のある親子関係を持たせる。

Legacy `WPCF/class.Relation.php` の設計を基礎とし、保存モデルは単一正本とする。

典型例:

```text
Facility
  └ Job

Customer
  └ Purchase

Menu
  └ MenuItem
```

Relation は表示機能ではなくモデルである。管理画面上のリンク、一覧、選択 UI は Relation を操作するための View / Adapter として分離する。

## 基本原則

### 1. 子側 meta を唯一の正本とする

Relation の永続データは子 Post 側だけに保存する。

```text
Child.postmeta[relation_parent] = Parent ID
```

例:

```text
Post #34 (job)
relation_parent = 12
```

親 Post に `relation_children` は保存しない。

```text
Parent #12
children = [34, 56]  ← 永続化しない
```

親から子への一覧は、子側 meta から導出する Projection とする。

これにより、親側と子側を二重保存した場合に発生する同期不整合を避ける。

### 2. Relation の基本 cardinality は 1:N

MVP の Relation は次を表現する。

```text
Parent 1
  ↓
Child N
```

1つの Relation Definition 内では、1 Child は最大1 Parentを持つ。

```text
Parent A ─┬─ Child 1
          ├─ Child 2
          └─ Child 3
```

同一 Child が複数種類の Parent Relation に参加することは許可する。

```text
Facility ──> Job
Company  ──> Job
```

この場合、それぞれ別の Relation Definition と別の meta key を使う。

M:N が必要な場合は、単純 Relation を拡張せず Relation Entity を使う。

### 3. 同一 PostType 同士を許可する

```text
page → page
person → person
category-like-post → category-like-post
```

parentPostType と childPostType が同一でも Relation Definition として有効とする。

循環参照の禁止や深さ制限は Relation Core の責務には含めない。必要な場合は application policy として追加する。

### 4. Relation の属性は持たせない

単純 Relation 自体に次のような値を持たせない。

```text
price
sort_order
status
valid_from
valid_to
quantity
role
```

これらが「Post 自身」ではなく「A と B の関係」に属する場合、Relation Entity に昇格する。

例:

```text
Menu
  ↓
MenuEntry
  ├ menu_id
  ├ menu_item_id
  ├ price
  ├ sort_order
  └ available
  ↓
MenuItem
```

## Taxonomy / Relation / Relation Entity の境界

### Taxonomy

分類を表す。

```text
MenuItem → Category
Post     → Topic
Product  → Genre
```

Term は分類軸であり、Post 同士の業務上の関係ではない。

### Relation

独立した Post 同士の関係を表す。

```text
Customer → Purchase
Facility → Job
Company  → Staff
```

Relation そのものに属性が不要な場合に使う。

### Relation Entity

関係そのものが独立した状態・属性・履歴を持つ場合に使う。

```text
Customer
  ↓
Purchase
  ├ product_id
  ├ quantity
  ├ price
  └ purchased_at
  ↓
Product
```

判断基準:

```text
分類したい                       → Taxonomy
Post A と Post B を結びたい      → Relation
A-B 間に属性・状態・履歴がある   → Relation Entity
```

## RelationDefinition

MVP の Definition は次を正本とする。

```php
RelationDefinition::make(
    name: 'facility_job',
    parentPostType: 'facility',
    childPostType: 'job',
    parentMetaKey: 'relation_parent'
);
```

概念上のフィールド:

```text
name             string
parentPostType   string
childPostType    string
parentMetaKey    string
```

### name

Relation Definition 自体の安定した識別子。

PostType 名とは独立させる。

用途:

- Registry のキー
- API / hook / debug の識別
- 同じ Child PostType が複数 Relation に参加する場合の区別
- 将来の migration / REST adapter / UI 設定

例:

```text
facility_job
company_job
customer_purchase
menu_item
```

### parentMetaKey

子側 Post に保存する Parent ID の meta key。

デフォルト値は互換性のため `relation_parent` とするが、複数 Relation が同じ Child PostType に存在する場合は明示指定を推奨する。

例:

```text
facility_parent_id
company_parent_id
menu_parent_id
```

自動的な key 改名は migration を不透明にするため、MVP では行わない。

Registry は同じ childPostType に対して parentMetaKey が衝突した場合、登録エラーとする。

### childrenMetaKey

廃止する。

旧仕様にあった `relation_children` は永続化しない。親→子は Projection で解決する。

既存 `RelationDefinition::$childrenMetaKey` は実装移行時に deprecated とし、互換期間後に削除する。

## 保存形式

MVP の保存値は Parent Post ID の単一整数とする。

```text
relation_parent = 12
```

未設定は meta 自体を削除することを基本とする。

```text
0
''
null
[]
```

を「親なし」の永続値として積極的に保存しない。

Legacy データで serialized array 等が存在する場合は Migration / Legacy Reader が吸収し、新規保存形式には持ち込まない。

## RelationRepository / Storage

Relation の永続化と取得は UI から分離する。

想定インターフェース:

```php
interface RelationRepositoryInterface
{
    public function getParent(RelationDefinition $relation, int $childId): ?int;

    /** @return int[] */
    public function getChildren(RelationDefinition $relation, int $parentId): array;

    public function attach(RelationDefinition $relation, int $parentId, int $childId): void;

    public function detach(RelationDefinition $relation, int $childId): void;
}
```

MVP 実装:

```text
PostMetaRelationRepository
```

責務:

- child meta への parent ID 保存
- child meta から parent ID 取得
- meta query による parent → children Projection
- PostType 整合性の検証
- 不正 ID / 存在しない Post の拒否または空返却

## RelationRegistry

Relation Definition を一元管理する。

想定 API:

```php
$registry->register(
    RelationDefinition::make(
        name: 'facility_job',
        parentPostType: 'facility',
        childPostType: 'job',
        parentMetaKey: 'facility_parent_id',
    )
);

$registry->get('facility_job');
$registry->all();
```

登録時に検証する。

- name が一意
- parentPostType / childPostType が空でない
- parentMetaKey が空でない
- 同じ childPostType + parentMetaKey の組み合わせが重複しない
- 同一 parentPostType + childPostType の Relation を複数定義する場合も name / key が衝突しない

PostType が WordPress にまだ register されていないこと自体は Definition のエラーにしない。登録順序への依存を避ける。

## RelationService

Application から使う操作窓口。

想定 API:

```php
$relation->parent('facility_job', $childId);
$relation->children('facility_job', $parentId);

$relation->attach('facility_job', $parentId, $childId);
$relation->detach('facility_job', $childId);
```

Service は Registry から Definition を取得し、Repository に処理を委譲する。

View / MetaBox / REST API が直接 postmeta を操作しない。

## 検証ルール

attach 時に最低限次を検証する。

```text
Parent が存在する
Parent.post_type === parentPostType

Child が存在する
Child.post_type === childPostType

Parent ID !== Child ID
  ※ 同一 PostType Relation でも自己参照はMVPでは禁止
```

自己参照を必要とする application は後続で policy により明示許可する。

既存の親がある Child に attach した場合は、新 Parent に付け替える。

```text
Child → Parent A
attach(Parent B, Child)
↓
Child → Parent B
```

これは 1:N Relation の基本動作とする。

## Query / Projection

親→子は保存値ではなく query 結果である。

基本:

```text
post_type = childPostType
meta_key  = parentMetaKey
meta_value = parentId
```

返却順は Relation Core では意味を持たない。

MVP のデフォルトは Post ID 昇順または WordPress query の安定した既定順とし、業務上の順番が必要な場合は application query または Relation Entity を使う。

Relation Core に `sort_order` を追加しない。

## Cache

Legacy の `RELATION` / `RELATION_REV` の全件起動時キャッシュは、そのまま移植しない。

MVP は WordPress の meta query と object cache に委ねる。

性能上必要になった場合のみ、Repository の内部実装として cache を追加する。

Cache は正本ではなく、常に再構築可能であること。

## Admin UI

Legacy の操作性は継承するが、Core と分離する。

親編集画面:

- 関連 Child 一覧
- Child 編集リンク
- Child 表示リンク
- 親を引き継いだ Child 新規作成リンク

子編集画面:

- Parent へのリンク
- Parent の変更 UI
- 同一 Parent の sibling 一覧
- Parent 未設定状態の表示

実装候補:

```text
RelationMetaBox
RelationAdminLinks
```

UI は RelationService を通じて操作し、meta key を直接扱わない。

## 新規 Child 作成時の Parent 引き継ぎ

Legacy の query parameter による導線は維持可能とする。

例:

```text
/post-new.php?post_type=job&relation=facility_job&parent=12
```

旧 `custom_parent` のように meta key 自体を URL parameter 名として公開する方式は採用しない。

Relation name と Parent ID を受け取り、RelationRegistry で解決する。

Nonce / capability / PostType 検証は Admin Adapter の責務とする。

## REST / Headless

REST では保存形式をそのまま露出させず、Relation name を API 契約とする。

例:

```json
{
  "relations": {
    "facility_job": {
      "parent": 12
    }
  }
}
```

親側では Projection を展開可能にする。

```json
{
  "relations": {
    "facility_job": {
      "children": [34, 56]
    }
  }
}
```

`children` が API に存在しても、DB に保存されていることを意味しない。

## Legacy migration

Legacy `WPCF/class.Relation.php` では子側 meta が正本であるため、基本思想はそのまま移行できる。

対象 Legacy:

```text
custom_parent
custom_parent_<parent>-<child>
任意指定 parent_id_key
```

Migration 方針:

1. Relation Definition を明示的に登録する
2. Legacy meta key を parentMetaKey としてそのまま使うか、新 key へ移行する
3. serialized array 形式等を単一 int へ normalize する
4. 親側 children meta は新規生成しない
5. 旧 UI と新 UI の併用期間を設ける場合も、正本は同じ child meta とする

自動 migration は Relation Core の起動時には行わない。明示的な migration command / tool として実行する。

## 責務分離

```text
RelationDefinition
  → 関係の意味と型を定義

RelationRegistry
  → Definition を管理・検証

RelationRepository
  → 保存・取得・Projection

RelationService
  → Application API

RelationMetaBox / Admin Adapter
  → WordPress 管理UI

REST Adapter
  → API 表現

Relation Entity
  → 属性を持つ関係を別モデルとして表現
```

MetaBox は Relation の正本ではない。

```text
PostMetaManager → 汎用 meta 保存
MetaBox         → 入力 UI / normalization
Relation        → Post 間の意味構造
```

## MVP 実装範囲

実装する:

- RelationDefinition の更新
- RelationRegistry
- RelationRepositoryInterface
- PostMetaRelationRepository
- RelationService
- parent 取得
- children Projection
- attach / detach
- 同一 PostType Relation
- 複数 Relation Definition
- Definition / Repository / Service の unit test
- Legacy compatible meta key の利用
- 最小 Admin UI
  - parent link
  - children list
  - parent を引き継いだ child 新規作成

MVP では実装しない:

- M:N Relation
- Relation 自体への meta
- Relation Entity の自動生成
- 起動時全 Relation cache
- 自動 migration
- drag & drop sort
- Relation 履歴
- graph traversal
- cascade delete
- 循環検出
- 複雑な権限 policy
- taxonomy との API 統合

## 実装順

```text
1. RelationDefinition を single-source モデルへ修正
2. RelationRegistry
3. RelationRepositoryInterface
4. PostMetaRelationRepository
5. RelationService
6. unit tests
7. Legacy fixture / compatibility tests
8. RelationMetaBox
9. Admin link / create-child flow
10. REST adapter（Phase 4）
11. Relation Entity（別仕様）
```

## 設計上の不変条件

```text
Relation の正本は一箇所だけ
Parent → Children は Projection
View は Relation を所有しない
Relation Core は表示順を所有しない
Relation に属性が必要なら Entity に昇格
Taxonomy を Relation の代用品にしない
Relation Entity を単純 Relation に押し込まない
```
