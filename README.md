# Tigers Result Delivery

阪神タイガースの試合結果を自動で取得し、LINE に通知する Cloud Functions アプリケーションです。

## 機能概要

### 主な機能

1. **試合結果の自動取得**
   - Yahoo! JAPAN スポーツナビ（NPB 日程・結果）をスクレイピングし、当日の阪神タイガースの試合結果（スコア、対戦相手、スコアページリンク）を取得します。
   - 試合詳細ページからスコアプレー（得点経過）、本塁打、責任投手情報を抽出し、OpenAI API を使用してニュースキャスター風のハイライト要約を生成します。

2. **LINE 通知**
   - 試合終了（スコア確定）後、LINE Messaging API 経由で対象のユーザー／グループに試合結果（スコア、AI要約、詳細リンク）をプッシュ送信します。

3. **重複通知の防止**
   - Google Cloud Firestore を利用して通知状態を管理します。
   - 一度通知した試合（日付）については、再実行されても重複して通知を行いません。

### 処理フロー

定期実行される Cloud Functions (`main_event`) の処理フローは以下の通りです：

1. **実行条件チェック**:
   - シーズン中（3月15日～10月31日）であるかを確認します。季節外の場合は処理を終了します。

2. **通知済みチェック**:
   - Firestore を参照し、当日の試合が既に通知済みでないか確認します。
   - 既に通知済みの場合は処理を終了します。

3. **試合結果・詳細情報の取得**:
   - Yahoo! JAPAN スポーツナビから当日の試合結果を取得します。
   - 試合が終了している場合、詳細ページよりスコアプレー・本塁打・責任投手情報を取得し、OpenAI API で AI 要約を生成します。

4. **スコア確定確認**:
   - 試合が終了し、スコアが確定していることを確認します。

5. **LINE 送信**:
   - 試合結果および AI 要約をメッセージフォーマットに整え、LINE Messaging API 経由で送信します。

6. **履歴保存**:
   - 送信成功後、Firestore に「通知済み」フラグとタイムスタンプを保存し、次回の重複実行を防ぎます。

### 環境変数

| 変数名 | 説明 | 備考 |
| :--- | :--- | :--- |
| `APP_ENV` | 実行環境の指定（`production`, `test`, `development`）。 | 未設定時のデフォルトは `development` |
| `LINE_TOKENS_N_TARGETS` | LINE Bot のアクセストークンおよび送信先ターゲット ID を保持する JSON 文字列。 | 各環境（`bball`, `nobu` 等）に対応するトークン／ターゲット ID を格納 |
| `OPENAI_KEY_SMALL_CF_APPS` | OpenAI API のシークレットキー。 | AI 要約生成で使用 |

### データ管理 (Firestore)

通知履歴は以下の構成で Firestore に保存されます：

- **コレクションパス**:
  - 本番環境 (`APP_ENV=production`): `/tigers-result-delivery/results/results/{YYYY-MM-DD}`
  - テスト／開発環境 (`APP_ENV=test` / `development`): `/tigers-result-delivery-test/results/results/{YYYY-MM-DD}`
- **ドキュメント内容**:
  ```json
  {
    "is_notified": true,
    "timestamp": "2026-10-04T15:00:00Z"
  }
  ```
