# Mail Queue Architecture

## Purpose

WP-Kit Mail は、WordPress 上のメール送信を特定の配信事業者に依存させず、永続 Queue・再試行・配送履歴・Transport 差し替えを可能にするためのメール基盤です。

この設計では次を分離します。

```text
Producer / Domain
  ├─ Fanika
  ├─ WooCommerce integration
  ├─ WordPress Core
  └─ Other Plugins
        ↓
WP-Kit Mail Application
        ↓
WP-Kit Mail Core contracts
        ↓
Mail Transport
  ├─ wp_mail
  ├─ Postmark
  ├─ Amazon SES
  └─ SendGrid
```

Fanika、WooCommerce、Postmark 等の固有仕様を WP-Kit Core に持ち込みません。

---

## Responsibility Boundary

### WP-Kit Core

WP-Kit Core は再利用可能な Mail primitive / contract / WordPress adapter を提供します。

Core に置くもの:

- `MailMessage`
- address / header / attachment value object
- Queue / Repository / Scheduler / Transport の interface
- `TransportResult`
- retry policy の汎用契約
- delivery status / queue status の値
- idempotency / deduplication primitive
- WordPress `wp_mail()` との adapter
- WordPress hook 接続 primitive
- WordPress が存在しない環境でも読み込める境界

Core に置かないもの:

- DB table migration
- Action Scheduler の実運用設定
- 管理画面
- credential / provider setup
- Postmark / SES / SendGrid 固有設定
- provider webhook endpoint
- Fanika / WooCommerce 固有 policy
- site-specific retention policy

依存方向は既存の Application Plugin 方針に従います。

```text
WP-Kit Mail Application Plugin
             ↓
          WP-Kit
```

WP-Kit Core が WP-Kit Mail Application Plugin を知ることはありません。

### WP-Kit Mail Application Plugin

具体的な「WordPress 上でメールを永続 Queue に受け付け、非同期送信する」という application responsibility を完成させます。

所有するもの:

- Queue persistence
- migration
- Action Scheduler integration
- worker / lock / stale-job recovery
- retry execution
- `wp_mail()` interception runtime
- attachment spool runtime
- admin / CLI operations
- retention / purge
- health check
- provider integration configuration
- provider webhook integration

---

## Core Model

### MailMessage

最低限、以下を保持します。

```text
to
cc
bcc
subject
html
text
headers
attachments
embeds
from
reply_to
category
source
source_id
idempotency_key
metadata
```

`category` は provider 固有値にしません。

初期値候補:

- `transactional`
- `notification`
- `broadcast`
- `system`

Postmark Message Stream 等への変換は Transport / provider integration 側の責務です。

### Queue status と Delivery status を分離する

Queue の処理状態と、外部配送結果を同じ status に混在させません。

Queue status:

```text
queued
processing
retry_wait
submitted
dead
cancelled
```

Delivery status:

```text
unknown
delivered
bounced
complained
```

`submitted` は Transport が送信要求を受理した状態です。
受信者の mailbox への到達完了を意味しません。

---

## Persistence Model

Queue runtime は最低 2 table を持ちます。

### mail_messages

推奨 prefix:

```text
{wp_prefix}pwk_mail_messages
```

主な列:

```text
id
queue_status
delivery_status
message_payload
transport
category
source
source_id
idempotency_key
attempt_count
scheduled_at
processing_at
submitted_at
provider_message_id
last_error_code
last_error_message
created_at
updated_at
```

index:

- `queue_status + scheduled_at`
- `source + source_id`
- idempotency 用 index

### mail_attempts

```text
{wp_prefix}pwk_mail_attempts
```

送信試行ごとの履歴を保持します。

```text
id
mail_id
attempt_no
transport
started_at
finished_at
accepted
provider_message_id
error_code
error_message
metadata
```

`attempt_count` と `last_error` だけで履歴を上書きしません。

---

## Scheduler

Action Scheduler は Queue の正本ではなく、worker を起動する execution engine として扱います。

```text
Mail Queue DB
    ↓
Action Scheduler
    ↓
Mail Worker
    ↓
Transport
```

Action Scheduler の action args には message 全体を保存せず、原則 `mail_id` のみを渡します。

group:

```text
wp-kit-mail
```

即時処理には async action、予約送信には single action を使用します。

Action Scheduler が重複実行や再実行を起こしても、Queue row 側で claim を行い二重 worker 実行を防ぎます。

---

## Delivery Semantics

「exactly once delivery」は保証しません。

外部 provider がメールを受理した直後、DB 更新前に PHP process が停止した場合、再試行により二重送信が発生する可能性があります。

したがって保証は次とします。

- Queue enqueue: idempotency key により重複登録を抑止可能
- Worker execution: row claim により同時実行を抑止
- Delivery: at-least-once になり得る
- provider message id / WP-Kit mail id を保持して追跡可能にする

Transport が provider-side idempotency を提供する場合は、その機能を利用できます。

---

## Retry

Retry policy は attempt failure と Queue row を分離します。

初期 default 候補:

```text
1 minute
5 minutes
30 minutes
2 hours
6 hours
```

5 回失敗後は `dead`。

ただし Transport が恒久エラーと判断できる場合は即 `dead` とします。

例:

- invalid recipient → permanent
- authentication error → configuration / permanent until fixed
- timeout / temporary network error → retryable
- `wp_mail() === false` → 原因特定不能のため retryable を default とする

manual retry は新しい Queue row を作らず、同じ mail id を再利用します。

---

## wp_mail Compatibility

### Interception

既存 code を書き換えず Queue 化する互換入口として `pre_wp_mail` を使用します。

```text
existing plugin
    ↓
wp_mail()
    ↓
pre_wp_mail
    ↓
WP-Kit Mail Queue
```

Queue への永続保存と scheduler 登録に成功した場合のみ、interceptor は `true` を返します。

この `true` は「配送済み」ではなく「Queue 受付成功」を意味します。

Queue への保存に失敗した場合は `false`。

### Recursion guard

Worker から `WpMailTransport` が `wp_mail()` を呼ぶと再度 interceptor に入るため、内部送信 context では interception を bypass します。

```text
Interceptor
   ↓
Queue
   ↓
Worker
   ↓
WpMailTransport
   ↓
wp_mail()
   ↓
bypass interception
```

### Compatibility limitation

`pre_wp_mail` は WordPress の `wp_mail` argument filter 後に実行されますが、PHPMailer 初期化時に行われる全ての runtime customization を enqueue 時点で完全に snapshot できるとは限りません。

そのため:

- explicit WP-Kit Mail API を正規経路とする
- `wp_mail` interception は compatibility layer とする
- WooCommerce / WordPress Core /主要 plugin は integration test で互換性を確認する

---

## Attachments and Embeds

非同期化では、元の attachment path を Queue に保存するだけでは不十分です。

temporary file が worker 実行前に消える可能性があるため、enqueue 時点で snapshot します。

```text
source file
   ↓ enqueue
AttachmentStore
   ↓
private spool
   ↓ worker
Transport
```

Core contract:

```text
AttachmentStoreInterface
```

Application Plugin runtime が実 storage を所有します。

要件:

- web 公開領域へ無条件に置かない
- original filename を保持
- checksum を保持
- attachment / embed の両方を扱う
- Queue 完了後も retention 期間までは追跡可能
- retention 終了後 purge
- purge failure を検出可能

WP-Kit の既存 Asset Access Control / private storage abstraction と接続可能にしますが、Mail Core がそれへ直接依存しない契約にします。

---

## Transport

Core contract の責務は小さく保ちます。

```php
interface MailTransportInterface
{
    public function name(): string;

    public function isAvailable(): bool;

    public function send(MailMessage $message): TransportResult;
}
```

`TransportResult`:

```text
accepted
provider_message_id
error_code
error_message
retryable
metadata
```

初期 Transport は `WpMailTransport` のみでよいものとします。

Postmark / SES / SendGrid は Queue foundation 完成後に追加します。

---

## Public API

WP-Kit の既存 entry point `pwk()` に合わせます。

最終形候補:

```php
$mailId = pwk()->mail()->enqueue($message);
```

予約送信:

```php
$mailId = pwk()->mail()->enqueue(
    $message,
    sendAt: $dateTime
);
```

同期送信が必要な場合:

```php
$result = pwk()->mail()->sendNow($message);
```

ただし persistent Queue / scheduler が必要な API の実体は Application Plugin が構成します。

Core 単体では「Queue runtime が存在する」と仮定しません。

---

## Suggested Core Directory

```text
src/
└─ Mail/
   ├─ MailMessage.php
   ├─ MailAddress.php
   ├─ MailAttachment.php
   ├─ MailCategory.php
   ├─ QueueStatus.php
   ├─ DeliveryStatus.php
   ├─ TransportResult.php
   ├─ Contract/
   │  ├─ MailQueueInterface.php
   │  ├─ MailRepositoryInterface.php
   │  ├─ MailSchedulerInterface.php
   │  ├─ MailTransportInterface.php
   │  ├─ AttachmentStoreInterface.php
   │  └─ RetryPolicyInterface.php
   └─ Retry/
      └─ ExponentialRetryPolicy.php

src/
└─ WordPress/
   └─ Mail/
      ├─ WpMailTransport.php
      ├─ WpMailInterceptor.php
      └─ WpMailContext.php
```

WordPress function call は既存方針に従い WordPress 層へ閉じます。

---

## Application Plugin Runtime Layout

WP-Kit Mail Application Plugin 側の想定:

```text
src/
├─ Queue/
│  ├─ WpdbMailRepository.php
│  ├─ MailWorker.php
│  ├─ QueueClaim.php
│  └─ StaleJobRecovery.php
├─ Scheduler/
│  └─ ActionSchedulerDriver.php
├─ Storage/
│  └─ FilesystemAttachmentStore.php
├─ Migration/
├─ Admin/
├─ Cli/
├─ Health/
└─ Integration/
   └─ Provider/
```

これは WP-Kit Core には配置しません。

---

## Security and Retention

メール本文には個人情報、注文情報、password reset URL 等が含まれる可能性があります。

必須方針:

- full body を無期限保存しない
- Queue / attempts / attachment spool に retention policy を持つ
- 管理画面では本文を default 展開しない
- capability check なしに本文・宛先を表示しない
- secrets / provider credentials を Queue metadata に保存しない
- logs へ body 全文を出さない

retention の具体値は site/application policy のため Core では決めません。

---

## Health Checks

Application Plugin は最低限次を診断できるようにします。

- Action Scheduler initialized
- pending / failed / dead count
- oldest queued age
- stale processing row
- attachment spool writable
- attachment spool direct-public exposure
- default Transport availability
- recurring cleanup action existence

---

## Implementation Order

### Phase 1 — Core contracts

- [ ] MailMessage / value objects
- [ ] status values
- [ ] Queue / Repository / Scheduler / Transport contracts
- [ ] TransportResult
- [ ] RetryPolicy
- [ ] unit tests
- [ ] WordPress-free load test

### Phase 2 — WordPress compatibility primitives

- [ ] WpMailTransport
- [ ] WpMailContext recursion guard
- [ ] WpMailInterceptor
- [ ] attachments / embeds normalization
- [ ] WordPress function absence guard
- [ ] interception unit/integration tests

### Phase 3 — Queue Application runtime

- [ ] DB schema / migration
- [ ] WpdbMailRepository
- [ ] Action Scheduler integration
- [ ] worker claim
- [ ] attempt log
- [ ] retry scheduling
- [ ] stale worker recovery
- [ ] attachment spool
- [ ] purge
- [ ] CLI / health

### Phase 4 — Operations

- [ ] admin Queue viewer
- [ ] dead mail retry
- [ ] cancellation
- [ ] retention settings
- [ ] monitoring hooks

### Phase 5 — Provider transports

- [ ] Postmark
- [ ] Amazon SES
- [ ] SendGrid
- [ ] provider webhook delivery event mapping

Provider の採用順位はこの architecture の責務外です。

---

## Tests Required Before wp_mail Interception Is Enabled

- [ ] plain text mail
- [ ] HTML mail
- [ ] To / Cc / Bcc
- [ ] Reply-To / From
- [ ] custom headers
- [ ] attachment
- [ ] temporary attachment snapshot
- [ ] embedded image
- [ ] `wp_mail() === false`
- [ ] retry
- [ ] duplicate Action Scheduler execution
- [ ] worker crash / stale processing recovery
- [ ] interception recursion
- [ ] idempotency key duplicate
- [ ] WordPress Core password reset mail
- [ ] WooCommerce transactional mail
- [ ] mail plugin using `phpmailer_init`
- [ ] WordPress without Action Scheduler runtime
- [ ] multisite prefix behavior

Global `wp_mail` interception はこれらの compatibility test が通るまでは default off とします。

---

## Non-goals

初期実装では以下を行いません。

- newsletter editor
- campaign management
- recipient list management
- marketing automation
- provider selection UI
- delivery rate analytics
- open / click tracking
- Fanika固有 notification rule
- WooCommerce固有 mail template generation

WP-Kit Mail の責務は「メール配送 application」であり、marketing system や domain notification system にはしません。
