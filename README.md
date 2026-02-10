
## 機能概要

### LINE 重複送信防止

試合結果を LINE 通知した際に、Firestore に「通知済み」状態を記録し、重複送信を防ぎます。

**処理フロー：**
1. 季節チェック（3/15～10/31）
2. 時間チェック（14:00～23:59）
3. **通知済みチェック** ← Firestore で確認
4. 試合結果取得（NPB スクレイピング）
5. スコア完全性チェック
6. LINE 送信
7. **通知履歴記録** ← Firestore に記録

**Firestore 保存先：**
- パス：`/result-delivery-test/results/results/{YYYY-MM-DD}`
- ドキュメント内容：
  ```json
  {
    "is_notified": true,
    "timestamp": "2026-02-10T14:30:00Z"
  }
  ```

### ファイル構成

| ファイル | 役割 |
|---------|------|
| `index.php` | Cloud Functions エントリーポイント |
| `src/GameResult.php` | 試合結果 Entity |
| `src/NpbGameResultService.php` | NPB 試合結果取得サービス |
| `src/NpbScraper.php` | HTML スクレイピング |
| `src/LineNotificationService.php` | LINE 送信サービス |
| `src/NotificationHistoryService.php` | 通知履歴管理（Firestore） |
| `src/FirestoreClient.php` | Firestore クライアント（シングルトン） |
| `src/AppConfig.php` | 設定管理 |
| `src/LoggerFactory.php` | ロガー ファクトリー |

