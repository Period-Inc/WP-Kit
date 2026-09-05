# Media Reference Index

`MediaReferenceIndex` は、WordPress の投稿本文・post meta が参照する attachment を保存時に索引化するための汎用コンポーネントです。

目的は、メディアが現在どこから参照されているかを確認するたびに `wp_posts` / `wp_postmeta` 全体へ LIKE 検索を行う必要をなくすことです。

## 基本方針

- source post 側に forward index を保存します。
- attachment 側に reverse index を保存します。
- `save_post`、`added_post_meta`、`updated_post_meta`、`deleted_post_meta`、`before_delete_post` を監視します。
- 同じ attachment が本文と複数 meta から参照される場合は、参照位置を `locations` として保持します。
- 既存データは WP-CLI の rebuild で初期索引化します。
- 機能は default OFF です。

## 有効化

```php
add_filter('wp_kit_media_reference_index_enabled', '__return_true');

$index = pwk()->mediaReferenceIndex();
$index->boot();
```

## 設定

```php
$index = pwk()->mediaReferenceIndex([
    'reverse_meta_key' => '_my_media_references',
    'forward_meta_key' => '_my_media_reference_targets',
    'state_option' => 'my_media_reference_index_state',
    'enabled_filter' => 'my_media_reference_index_enabled',
    'ignored_meta_keys' => [
        '_my_archive_status',
        '_my_archive_manifest',
    ],
]);
$index->boot();
```

## 参照取得

```php
$references = $index->getReferences($attachmentId);
$isReferenced = $index->isReferenced($attachmentId);
```

reverse index は source post ID ごとに、post type / status / locations / updated_at を保持します。

## 初期 rebuild

有効化後、既存投稿を索引化します。

```bash
wp wp-kit media-reference rebuild --reset
wp wp-kit media-reference status
```

大量データでは `--batch-size` と `--after-id` を利用できます。

## 非責務

このコンポーネントは「現在の参照関係」を索引化します。削除済みデータに存在していた過去の参照関係を復元するものではありません。また、メディアを削除・アーカイブしてよいかという業務判断は呼び出し側の責務です。
