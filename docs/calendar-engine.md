# Calendar Engine Specification

**Status:** Draft / architecture fixed  
**Date:** 2026-10-03  
**Scope:** WP-Kit Calendar / Scheduling foundation

## 1. Purpose

WP-Kit の Calendar Engine は、特定の暦・UI・保存先・外部カレンダーに依存しない時間／予定管理基盤とする。

既存のスケジュール表システムを MVP の利用実例として維持しつつ、内部エンジンは本仕様に沿って置き換える。

将来は以下へ拡張できることを前提とする。

- WordPress 上のスケジュール表
- Web カレンダー
- Google Calendar 連携
- iCalendar (`.ics`) 出力・購読
- 公開カレンダー
- 空き時間検索
- 定期予定
- 複数 Resource の予約
- 仮押さえ／本押さえ／確定を持つ予約システム
- AJAX / API によるリアルタイム予約

## 2. Core principle

### 2.1 時間軸と暦を分離する

システム内部の「時点」は UNIX timestamp を正規表現として扱う。

UNIX timestamp は UTC ベースの秒値として扱い、予定の比較、範囲判定、衝突判定等の基準とする。

Gregorian calendar を含む暦は timestamp 自体ではなく、時間軸上の値を人間が扱うための **projection** と位置付ける。

したがって、Core Domain が `year / month / day` を絶対値として保持する設計にはしない。

> 注: UNIX timestamp は leap second を表現しないため、仕様上は「物理的絶対時刻」ではなく「システム内正規時刻」と定義する。

### 2.2 Calendar は Adapter とする

暦変換は `CalendarAdapter` を通す。

MVP は Gregorian calendar のみ対応する。

将来的に別暦を追加しても Event / Schedule / Storage の基本モデルを変更しない。

想定 Adapter:

- `GregorianCalendarAdapter`
- Japanese calendar / era adapter
- Islamic calendar adapter
- Hebrew calendar adapter
- その他 ICU 等で扱える calendar system

### 2.3 Timezone は暦と別責務とする

timezone は IANA timezone ID を使用する。

例:

- `UTC`
- `Asia/Tokyo`
- `America/New_York`

`2026-10-03 10:00` のような local datetime は timestamp と同一概念ではない。

Calendar projection と timezone conversion を分離し、以下を明示的に変換する。

- `Instant -> LocalDateTime`
- `LocalDateTime + Timezone -> Instant`

## 3. Temporal primitives

### 3.1 Instant

時間軸上の一点を表す。

基本値:

- UNIX timestamp seconds
- integer
- UTC semantics

比較・sort・範囲判定の基準値とする。

### 3.2 TimeRange

開始と終了を持つ時間範囲。

原則として半開区間 `[start, end)` を採用する。

これにより、次の予定が直前予定の終了時刻と一致する場合を衝突と扱わない。

### 3.3 Duration

期間を表す。

固定秒数として扱える Duration と、calendar semantics を必要とする期間を混同しない。

例:

- 90 minutes: fixed duration
- 1 calendar month: calendar-dependent duration

### 3.4 LocalDateTime

timezone 適用前の「現地時計上の日時」を表す。

定期予定、ユーザー入力、calendar projection の境界で使用する。

### 3.5 CalendarDate / DateRange

終日予定は Instant ではなく日付意味論を持つため、CalendarDate / DateRange として扱う。

終日予定を UTC 0:00 の timestamp のみで表現してはいけない。

理由:

- timezone 変更で日付がずれる
- Google Calendar / iCalendar の all-day event は date semantics を持つ
- DST 境界で 1 日が常に 86400 秒とは限らない

必要に応じて保存時に境界 Instant を導出してもよいが、日付意味論を失わない。

## 4. Event model

基本 Event は次を持つ。

- internal event ID
- title
- description
- temporal definition
- timezone
- calendar system
- recurrence
- visibility / publication state
- external identifiers
- arbitrary metadata

temporal definition は少なくとも以下の二種類を区別する。

### Timed Event

- start Instant
- end Instant
- display / recurrence timezone

### All-day Event

- start CalendarDate
- end CalendarDate (exclusive)
- calendar system
- timezone context where required

## 5. Recurrence

定期予定は UNIX 秒の固定 interval として実装しない。

例:

「毎週月曜日 10:00」は、

- local wall time
- timezone
- recurrence rule

として保持する。

DST が存在する timezone では「604800 秒後」と「翌週月曜 10:00」は一致しない場合がある。

基本規格は iCalendar / RFC 5545 の RRULE に寄せる。

想定要素:

- RRULE
- RDATE
- EXDATE
- recurrence exception
- recurrence instance override

個別 occurrence を永続的に大量生成することを前提とせず、必要範囲を展開する。

## 6. Architecture

責務は以下に分離する。

### Time Core

WordPress 非依存。

- `Instant`
- `TimeRange`
- `Duration`
- timezone conversion primitives

### Calendar Projection

WordPress 非依存。

- `CalendarAdapterInterface`
- `GregorianCalendarAdapter`
- `CalendarDate`
- `LocalDateTime`

### Scheduling Domain

WordPress 非依存。

- `Event`
- `Recurrence`
- `Schedule`
- `Availability`
- `Resource`

### Persistence

interface を通して保存先を分離する。

- `ScheduleRepositoryInterface`
- `MetaScheduleRepository`
- future DB repository
- external-backed repository when appropriate

### External Calendar

外部サービス固有処理を Adapter として分離する。

- `GoogleCalendarAdapter`
- future Microsoft / CalDAV adapter

### Serialization / Publication

- `ICalendarSerializer`
- public `.ics` feed
- JSON / REST representation
- Web UI projection

## 7. WordPress integration

Calendar Engine の Domain / Support 層は WordPress 関数に依存しない。

WordPress 固有処理は Infrastructure 層へ置く。

MVP の保存先は WordPress meta を基本とする。

想定:

- post / CPT が Event entity を所有
- Event の canonical internal ID を保持
- temporal data / recurrence / external mapping を meta として保存
- WP_Query から Event projection を構成可能にする

既存 `Support\Calendar` / `CalendarDay` は現在 Gregorian month grid helper であり、Engine Core そのものとは扱わない。

既存 API は破壊せず、段階的に以下へ移行する。

1. 既存 Calendar API の挙動をテストで固定
2. 新しい Time / Calendar / Event domain を追加
3. `Support\Calendar` を新しい Gregorian projection の consumer へ移行
4. 既存スケジュール表 UI を新 Engine へ接続

## 8. Storage policy

### 8.1 Internal identity

Google Calendar event ID や WordPress post ID を Domain Event の唯一の ID にしない。

内部 canonical ID を持つ。

外部 ID は mapping として保持する。

例:

- internal event UUID
- WordPress object ID
- Google calendar ID
- Google event ID
- iCalendar UID

### 8.2 WordPress meta

MVP では meta を主要 persistence とする。

ただし Domain は meta schema に依存しない。

### 8.3 Google Calendar storage

Google Calendar を保存先として利用できる設計を許容する。

ただし以下を区別する。

- primary repository
- external synchronization target
- imported external source

MVP では WordPress meta を primary とする。

Google primary / bidirectional synchronization は Adapter と conflict policy を追加して実装する。

### 8.4 Synchronization

Google 等との同期では以下を考慮する。

- external ID mapping
- remote update token / version
- incremental sync
- deletion / cancellation
- duplicate prevention
- conflict detection
- sync origin
- retry / failure state

外部サービスを予約時の排他ロック機構として使用しない。

## 9. iCalendar / public calendar

iCalendar export を標準機能とする。

対応対象:

- single event export
- calendar feed
- recurring event
- exception
- stable UID
- timezone
- all-day event

公開カレンダーは Web 表示と `.ics` feed を分離できるようにする。

公開 policy は Event / Calendar 単位で定義できるものとする。

将来の access control を考慮し、URL を知っているだけで公開する方式を Core の前提にはしない。

## 10. Google Calendar

Google Calendar は Engine Core から直接呼ばない。

`GoogleCalendarAdapter` を通して接続する。

Adapter の責務候補:

- event create / update / delete
- calendar read
- event import
- external ID mapping
- recurrence mapping
- all-day mapping
- timezone mapping
- incremental sync
- webhook / push notification handling

OAuth / credential / token persistence は Application / Infrastructure responsibility とし、Calendar Domain から分離する。

## 11. Library policy

日時・暦・RRULE・iCalendar parser を独自再実装しない。

標準または実績ある library を優先する。

候補:

- PHP `DateTimeImmutable`
- PHP Intl / `IntlCalendar`
- `rlanvin/php-rrule` for RFC 5545 recurrence
- `sabre/vobject` for iCalendar / vCard
- Google official PHP API client for Google Calendar

採用時は WP-Kit が要求する PHP version、WordPress compatibility、package size、maintenance status を確認して version を決定する。

外部 library の型を Domain API として直接露出させず、必要に応じて Adapter で包む。

## 12. MVP

既存スケジュール表システムを UI / product MVP とする。

Engine MVP は以下までとする。

- Instant
- TimeRange
- timezone
- CalendarAdapterInterface
- GregorianCalendarAdapter
- timed event
- all-day event
- recurrence abstraction
- WordPress meta repository
- existing schedule table integration
- iCalendar export
- Google Calendar Adapter boundary

Google 双方向同期、予約 engine、多暦 UI は MVP 必須ではない。

## 13. Reservation extension

Calendar Event と Reservation は別 Domain とする。

Reservation は Event の特殊状態として実装しない。

基本モデル:

- `Resource`
- `Availability`
- `Hold`
- `Reservation`
- `Confirmation`

状態遷移の基本:

`available -> held -> reserved -> confirmed`

意味:

- `held`: 公開領域からの一時的な仮押さえ。TTL を持つ
- `reserved`: 利用者が本押さえした状態
- `confirmed`: システムまたは管理側が確定した状態

cancel / expire / reject 等の状態も将来追加可能とする。

### Multiple resources

一つの予約で複数 Resource を同時に確保できるようにする。

例:

- room A
- equipment B
- staff C

複数 Resource の確保は atomic でなければならない。

### Realtime booking

公開 UI からの仮押さえは AJAX / REST API 等でリアルタイムに処理する。

同一 slot への同時アクセス時に二重予約を発生させてはいけない。

このため Reservation persistence は transactional lock / unique constraint 等を使用できる repository を必要とする。

WordPress post meta のみを高競合予約の排他制御に使用することを前提としない。

予約機能では dedicated table / transactional repository を採用可能とする。

## 14. Boundary rules

Calendar Engine は以下を行わない。

- HTML UI を Domain 内で生成しない
- WordPress 関数を Domain / Support Core から直接呼ばない
- Google API object を Domain entity として扱わない
- Gregorian year/month/day を時間軸そのものと扱わない
- recurrence を単純な秒 interval に変換しない
- Google Calendar を排他ロック DB として使用しない
- booking state を Event publication state と混同しない

## 15. Implementation sequence

1. 既存 `Calendar` / `CalendarDay` の regression test を固定
2. Time Core (`Instant`, `TimeRange`, timezone) を追加
3. Calendar Adapter contract を定義
4. Gregorian Adapter を実装
5. Event temporal model を実装
6. recurrence contract を実装し既存 library を評価
7. Meta repository を実装
8. 既存スケジュール表を新 Engine へ接続
9. iCalendar serializer / feed を追加
10. Google Calendar Adapter を追加
11. availability / resource layer を追加
12. reservation domain / transactional persistence を追加
13. realtime hold / booking API を追加

## 16. Existing code migration

現在の `src/Support/Calendar.php` は `mktime()` / `date()` を利用した Gregorian calendar month grid 用 data helper である。

これは有用な既存 API なので即時削除しない。

ただし以下は新 Engine では暗黙利用しない。

- process default timezone
- Gregorian 固定
- year/month/day を primary temporal representation とすること
- `mktime()` を Core identity とすること

既存 API は compatibility layer として残し、新 Engine の projection を内部利用する形へ段階的に差し替える。


## 17. Legacy implementation findings

新 Engine は、以下の Legacy 実装を参照して再設計する。

- `src/Legacy/WPCF/class.EventSchedule.php`
- `src/Legacy/WPCF/class.ScheduleCalendar.php`（obsolete。EventSchedule の前身）
- `src/Legacy/WPCF/lib/class.Date.php`
- `src/Legacy/WPCF/lib/class.PublicHoliday.php`
- `src/Legacy/WP-Custom-Utility/CustomUtility/CustomUtility_Calendar.php`

### 17.1 Legacy から引き継ぐ概念

Legacy EventSchedule / ScheduleCalendar には、現在も有効な要求がすでに存在する。

- Event を WordPress post / CPT として扱える
- date / start time / end time を編集できる
- 旧 ScheduleCalendar には `open_time` もあり、start/end 以外の意味を持つ時刻を扱っていた
- 一日に複数 Event を保持できる
- month calendar と schedule/list の複数 projection を持つ
- `start_of_week` を変更できる
- month の previous / next navigation を持つ
- `/schedule/YYYY/MM/DD` 相当の date route を持つ
- current day / current week / weekday / previous-current-next month を表示上区別できる
- holiday を calendar decoration として扱う
- event fields / meta fields を追加できる
- arbitrary meta key で event を並び替えられる
- hook / filter により表示や挙動を拡張できる

これらは「Legacy UI を再現する」という意味ではなく、新 Domain / Projection / Adapter へ要求として取り込む。

### 17.2 Legacy から引き継がない結合

Legacy では schedule date/time と WordPress publication date が強く結合している。

EventSchedule は月範囲の取得を `wp_posts.post_date` で行い、編集画面では date / start_time から WordPress の `aa/mm/jj/hh/mn` を書き換えている。

その結果、未来の Event が WordPress の予約投稿 `future` と解釈されるため、`force_future_to_publish()` で強制的に `publish` へ戻す処理が必要になっている。

新 Engine では以下を禁止する。

- `post_date` を Event start の canonical value にする
- Event schedule と WordPress publication scheduling を同一状態として扱う
- `post_title` に date/time を埋め込んで schedule identity とする
- date meta / post_date / hidden timestamp の複数値を同時に正本とする

WordPress `post_date` は WordPress object 自体の publication semantics として扱う。

Event の日時は Calendar Domain の temporal definition を正本とする。

必要であれば Infrastructure 層で検索用の denormalized index を保持してよいが、それを Domain の正本にはしない。

### 17.3 Named temporal markers

旧 ScheduleCalendar の `open_time` は、Event が単純な start/end interval だけでは表現できない場合があることを示している。

そのため Event は将来的に optional な named temporal marker を持てる設計とする。

例:

- doors_open
- reception_start
- check_in
- last_entry

MVP で専用クラスを必須実装とはしないが、arbitrary metadata に文字列時刻を保存して意味を失わせる設計にはしない。

start/end は Event interval の意味を持ち、その他の時刻は別概念として拡張可能にする。

### 17.4 Calendar decoration

Legacy の PublicHoliday、today / this_week、weekday class 等は Event Domain ではなく Calendar Projection の decoration として扱う。

想定:

- HolidayProvider
- DayDecoration / CalendarDecoration
- locale weekday / month labels
- current-day marker

日本の祝日判定ロジック自体は Legacy の静的実装を移植せず、保守されている外部データ／ライブラリを優先する。

### 17.5 Legacy migration rule

既存 EventSchedule データを新 Engine に移行する場合、無条件変換は行わない。

Legacy は同一 Event の日時情報を複数箇所へ保持している可能性があるため、少なくとも以下を比較する。

- `post_date`
- date meta
- start_time meta
- end_time meta
- open_time meta（存在する場合）
- title 内に埋め込まれた date/time
- site timezone

変換時に値が一致しない場合は silent overwrite せず conflict として記録する。

基本 mapping 候補:

- date + start_time -> timed Event start
- date + end_time -> timed Event end
- open_time -> named temporal marker
- post_time -> audit / created metadata
- post content / event_title / additional fields -> Event content / metadata
- `post_date` -> migration verification source。新 Event の正本にはしない

時刻が欠落している Event を自動的に all-day と断定するかは migration policy で決定し、Core の暗黙仕様にはしない。
