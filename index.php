<?php

require_once 'vendor/autoload.php';

use Google\CloudFunctions\CloudEvent;
use App\NpbGameResultService;
use App\LineNotificationService;
use App\NotificationHistoryService;
use App\LoggerFactory;
use Carbon\Carbon;

/**
 * Cloud Functionsのエントリーポイント
 *
 * Pub/Sub イベントをトリガーとして、本日の阪神の試合情報を取得し LINE で通知します
 *
 * @param CloudEvent $cloudevent CloudEvents イベント
 * @throws \Exception 処理に失敗した場合
 */
function main_event(CloudEvent $cloudevent): void
{
    $logger = LoggerFactory::getLogger();

    // debug用日付
    // Carbon::setTestNow(Carbon::create(2024, 3, 31, 15, 0, 0, 'Asia/Tokyo'));

    $logger->info('処理を開始', [
        'eventTime' => $cloudevent->getTime(),
        'eventId' => $cloudevent->getId(),
    ]);

    try {
        // 日本時間での現在日時を取得
        $now = Carbon::now('Asia/Tokyo');
        $year = $now->year;
        $month = $now->month;
        $today = $now->format('n/j'); // '6/29' のような形式

        $logger->debug('本日の日付を取得', [
            'year' => $year,
            'month' => $month,
            'today' => $today,
        ]);

        // 季節チェック：3月15日～10月31日
        $startDate = Carbon::create($year, 3, 15, 0, 0, 0, 'Asia/Tokyo');
        $endDate = Carbon::create($year, 10, 31, 23, 59, 59, 'Asia/Tokyo');

        if (!$now->isBetween($startDate, $endDate)) {
            $logger->info('季節外のため処理を終了します', [
                'now' => $now->format('Y-m-d H:i:s'),
                'season' => "3月15日～10月31日",
            ]);
            return;
        }

        // 通知済みチェック
        $historyService = new NotificationHistoryService();
        $dateString = $now->format('Y-m-d');
        if ($historyService->isNotified($dateString)) {
            $logger->info('既に LINE 通知済みのため処理を終了します', [
                'date' => $dateString,
            ]);
            return;
        }

        $service = new NpbGameResultService();
        $gameResult = $service->fetchGameResult($year, $month, $today, '阪神');

        if (!$gameResult) {
            // 試合が見つからない場合はログに記録し、通知なし
            $logger->info('本日の阪神の試合は見つかりませんでした（試合がない、またはまだ開始されていない可能性があります）');
            return;
        }

        $logger->info('試合データ取得完了', [
            'opponent' => $gameResult->getOpponent(),
            'scoreLink' => $gameResult->getScoreLink(),
            'allyScore' => $gameResult->getAllyScore(),
            'opponentScore' => $gameResult->getOpponentScore(),
        ]);

        // スコアの完全性をチェック
        // 試合が終わっていれば、敵・味方両方のスコアが取得できるはず
        if ($gameResult->getAllyScore() === null || $gameResult->getOpponentScore() === null) {
            $logger->info('試合が終了していないと判断しました（スコアが未確定です）', [
                'allyScore' => $gameResult->getAllyScore(),
                'opponentScore' => $gameResult->getOpponentScore(),
                'scoreLink' => $gameResult->getScoreLink(),
            ]);
            return;
        }

        // LINE で通知
        $logger->info('LINE 通知を送信します');
        $lineService = new LineNotificationService();
        $sendResult = $lineService->sendGameResult($gameResult);

        // LINE 送信成功時に、通知済み状態を Firestore に記録
        if ($sendResult) {
            $historyService->recordNotification($dateString, $now);
        }

        $logger->info('処理を完了しました');
    } catch (\Exception $e) {
        $logger->error('処理中にエラーが発生しました', [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        throw $e;
    }
}
