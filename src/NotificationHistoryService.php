<?php

namespace App;

use Carbon\Carbon;
use Google\Cloud\Core\Timestamp as GoogleCoreTimestamp;
use Google\Cloud\Firestore\FirestoreClient as GoogleFirestoreClient;
use Monolog\Logger;

/**
 * 通知履歴サービス
 * 
 * Firestore に試合結果の通知済み状態を記録・確認します。
 * パス形式：/tigers-result-delivery-test/results/results/{YYYY-MM-DD}
 */
class NotificationHistoryService
{
    private Logger $logger;
    private ?GoogleFirestoreClient $firestoreClient;

    public function __construct(?GoogleFirestoreClient $firestoreClient = null)
    {
        $this->logger = LoggerFactory::getLogger();
        $this->firestoreClient = $firestoreClient;
    }

    private function getClient(): GoogleFirestoreClient
    {
        return $this->firestoreClient ?? FirestoreClient::getInstance();
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
            $firestoreClient = $this->getClient();
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
            $firestoreClient = $this->getClient();
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

    /**
     * 直近の処理履歴を取得
     *
     * @param int $days 取得日数（デフォルト7日）
     * @param Carbon|null $baseDate 基準日時（指定しない場合は現在日時 JST）
     * @return array<int, array{
     *     date: string,
     *     formatted_date: string,
     *     is_notified: bool,
     *     status_label: string,
     *     timestamp: string
     * }>
     */
    public function getRecentHistory(int $days = 7, ?Carbon $baseDate = null): array
    {
        $now = $baseDate !== null ? $baseDate->copy()->setTimezone('Asia/Tokyo') : Carbon::now('Asia/Tokyo');
        $weekDays = ['日', '月', '火', '水', '木', '金', '土'];
        $history = [];

        for ($i = 0; $i < $days; $i++) {
            $currentDate = $now->copy()->subDays($i);
            $dateString = $currentDate->format('Y-m-d');
            $dayOfWeek = $weekDays[$currentDate->dayOfWeek];
            $formattedDate = $currentDate->format('Y-m-d') . " ({$dayOfWeek})";

            $isNotified = false;
            $timestampString = '-';

            try {
                $firestoreClient = $this->getClient();
                $documentReference = $firestoreClient->collection(self::getCollectionPath())->document($dateString);
                $documentSnapshot = $documentReference->snapshot();

                if ($documentSnapshot->exists()) {
                    $data = $documentSnapshot->data();
                    $isNotified = (bool)($data['is_notified'] ?? false);
                    $timestampString = $this->formatTimestamp($data['timestamp'] ?? null);
                }
            } catch (\Exception $e) {
                $this->logger->error('Firestore から履歴取得中にエラーが発生', [
                    'date' => $dateString,
                    'error' => $e->getMessage(),
                ]);
            }

            $history[] = [
                'date' => $dateString,
                'formatted_date' => $formattedDate,
                'is_notified' => $isNotified,
                'status_label' => $isNotified ? '通知済み' : '未通知',
                'timestamp' => $timestampString,
            ];
        }

        return $history;
    }

    /**
     * タイムスタンプのデータ型に応じたフォーマット変換
     *
     * @param mixed $timestampData
     * @return string
     */
    private function formatTimestamp(mixed $timestampData): string
    {
        if ($timestampData === null) {
            return '-';
        }

        if ($timestampData instanceof GoogleCoreTimestamp) {
            return Carbon::instance($timestampData->get())->setTimezone('Asia/Tokyo')->format('Y-m-d H:i:s');
        }

        if ($timestampData instanceof \DateTimeInterface) {
            return Carbon::instance($timestampData)->setTimezone('Asia/Tokyo')->format('Y-m-d H:i:s');
        }

        if (is_string($timestampData)) {
            try {
                return Carbon::parse($timestampData)->setTimezone('Asia/Tokyo')->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return $timestampData;
            }
        }

        return '-';
    }
}
