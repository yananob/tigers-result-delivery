<?php

namespace App;

use Carbon\Carbon;
use Google\Cloud\Firestore\DocumentSnapshot;
use Monolog\Logger;

/**
 * 通知履歴サービス
 * 
 * Firestore に試合結果の通知済み状態を記録・確認します。
 * パス形式：/result-delivery-test/results/results/{YYYY-MM-DD}
 */
class NotificationHistoryService
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = LoggerFactory::getLogger();
    }

    private static function getCollectionPath(): string
    {
        return AppConfig::getFirestoreRootCollection() . '/results/results';
    }

    /**
     * 指定された日付の試合がLINE通知済みかどうかを確認
     * 
     * @param string $date 日付（YYYY-MM-DD 形式）
     * @return bool 通知済みの場合 true、未通知または存在しない場合 false
     */
    public function isNotified(string $date): bool
    {
        $this->logger->debug('通知済み状態を確認', ['date' => $date]);

        try {
            $firestoreClient = FirestoreClient::getInstance();
            $documentReference = $firestoreClient->collection(self::getCollectionPath())->document($date);
            $documentSnapshot = $documentReference->snapshot();

            if (!$documentSnapshot->exists()) {
                $this->logger->debug('ドキュメントが存在しません（初回の試合）', ['date' => $date]);
                return false;
            }

            $data = $documentSnapshot->data();
            $isNotified = $data['is_notified'] ?? false;

            $this->logger->debug('通知済み状態を確認完了', [
                'date' => $date,
                'is_notified' => $isNotified,
            ]);

            return $isNotified;
        } catch (\Exception $e) {
            $this->logger->error('Firestore から通知済み状態を確認中にエラーが発生', [
                'date' => $date,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            // エラー時は false を返す（通知を実行するが、ログに記録）
            return false;
        }
    }

    /**
     * 試合がLINE通知されたことを記録
     * 
     * @param string $date 日付（YYYY-MM-DD 形式）
     * @param Carbon $timestamp 通知送信時刻
     * @return bool 記録成功時 true、失敗時 false
     */
    public function recordNotification(string $date, Carbon $timestamp): bool
    {
        $this->logger->info('通知済み状態を記録', [
            'date' => $date,
            'timestamp' => $timestamp->toIso8601String(),
        ]);

        try {
            $firestoreClient = FirestoreClient::getInstance();
            $documentReference = $firestoreClient->collection(self::getCollectionPath())->document($date);

            $documentReference->set([
                'is_notified' => true,
                'timestamp' => $timestamp,
            ]);

            $this->logger->info('通知済み状態を記録完了', [
                'date' => $date,
                'timestamp' => $timestamp->toIso8601String(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Firestore に通知済み状態を記録中にエラーが発生', [
                'date' => $date,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            return false;
        }
    }
}
