# WP-Kit Application Plugin

## Definition

**WP-Kit Application Plugin** は、WP-Kitを利用して、WordPress上で一つ以上の具体的なapplication responsibilityを完成させる独立Pluginです。

WP-Kit Application Pluginは **WP-Kitそのものではありません**。  
WP-Kitの再利用可能なLibrary/API/WordPress integration primitiveを利用するconsumerです。

依存方向は常に次です。

```text
Application Plugin
        ↓
      WP-Kit
```

WP-Kitが個別Application Pluginを知ったり依存したりしてはいけません。

## Responsibility Boundary

### WP-Kit

WP-Kitは再利用可能なPHP Framework / Libraryです。

WP-Kitに置くもの:

- domain model / value object
- reusable service / resolver
- generic WordPress adapter
- Hook接続primitive
- rendering / registration / context abstraction
- UIに依存しないAPI
- site/product固有policyに依存しない機能

WP-Kitに置かないもの:

- 特定サイト固有のbusiness rule
- 特定Plugin固有の運用policy
- 特定Application専用の管理画面
- 特定サービス固有のcredential/setup flow
- 「このroleならこのTheme」のようなproduct rule
- deploy targetやsite固有path等のenvironment policy

### Application Plugin

Application PluginはWordPress上の具体的なapplication responsibilityを担当します。

例となる責務:

- access control application
- payment application
- admin/operations tool
- integration application
- content workflow application
- diagnostic / maintenance application

必要に応じて以下を持てます。

- admin UI
- capability / nonce
- persistent settings
- REST / webhook endpoint
- activation / deactivation lifecycle
- migration
- cron / scheduled action
- external service integration
- site/application-specific policy

これらを持つこと自体がApplication Pluginの条件ではありません。  
判断基準は **「WordPress上で具体的なapplication responsibilityを完成させているか」** です。

## What Does Not Define an Application Plugin

以下は分類基準にしません。

- コード量
- 機能数
- 開発期間
- 成熟度
- 管理画面の有無
- 単独配布されているか
- WP-Kit APIを大量に使っているか

小さくてもapplication responsibilityを持てばApplication Pluginです。  
大きくても再利用可能なdomain/libraryであればApplication Pluginとは限りません。

## Application Plugin and Site Core

Application PluginとSite Core / MU Pluginは別概念です。

### Application Plugin

特定の機能/application responsibilityを実装します。

### Site Core / MU Plugin

そのWordPress Installationの成立条件・bootstrap・必須runtimeを構成します。

Application PluginをSite Coreから読み込むこともできますが、両者を同一概念にはしません。

```text
Site Core / MU Bootstrap
├─ WP-Kit
├─ Application Plugin A
└─ Application Plugin B
```

通常Pluginとして有効化するか、MU Plugin/bootstrapから常時ロードするかはdeployment/operationの判断です。  
Application Pluginという分類自体はload方式では決まりません。

## Application Plugin and Domain Library

外部domainを扱う場合、domain modelとWordPress application integrationを分離できることがあります。

```text
Domain Library
       ↑
Application Plugin
       ↓
     WP-Kit
```

Application PluginがWP-Kitと別domain libraryの両方を利用しても構いません。

この場合:

- domain library: WordPress非依存のbusiness/domain contract
- WP-Kit: WordPress向けframework primitive
- Application Plugin: WordPress上でdomainを実際に成立させるintegration/application

という責務になります。

## Examples Status

実例は定義を検証するために別途蓄積します。

現時点ではRampartやPayment Adapter等を候補として検討していますが、**この文書では分類を確定しません**。

実例を追加する際は最低でも次の3種類を揃えます。

1. 明確なApplication Plugin
2. 境界事例
3. Application PluginではないLibrary / Adapter

実例から定義を作るのではなく、責務境界に照らして実例を検証します。

## Design Rule

新しい機能をWP-Kitへ追加する前に、次を判断します。

1. 複数Applicationから再利用可能なframework/library primitiveか
2. WordPress上の具体的なapplication responsibilityか
3. site/product固有policyを含むか
4. UI / persistence / lifecycle / external integrationを誰が所有すべきか
5. WP-Kitからその機能を知らなくても成立するか

原則:

- 1が中心ならWP-Kit
- 2または3が中心ならApplication Plugin
- WordPress非依存domainなら別Domain Library
- site全体のbootstrap/成立条件ならSite Core / MU Plugin
