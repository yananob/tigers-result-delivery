<?php

require_once 'vendor/autoload.php';

use Google\CloudFunctions\CloudEvent;
use App\NpbGameResultService;
use App\LineNotificationService;
use App\LoggerFactory;

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

    $logger->info('処理を開始', [
        'eventTime' => $cloudevent->getTime(),
        'eventId' => $cloudevent->getId(),
    ]);

    try {
        // DEBUG
        // $year = 2024;
        // $month = 3;
        // $today = '3/30';
        $year = (int)date('Y');
        $month = (int)date('m');
        $today = date('n/j'); // '6/29' のような形式

        $logger->debug('本日の日付を取得', [
            'year' => $year,
            'month' => $month,
            'today' => $today,
        ]);

        $service = new NpbGameResultService();
        $gameResult = $service->fetchGameResult($year, $month, $today, '阪神');

        if (!$gameResult) {
            // 試合が見つからない場合はログに記録し、通知なし
            $logger->info('本日の阪神の試合は見つかりませんでした');
            return;
        }

        // LINE で通知
        $logger->info('LINE 通知を送信します');
        $lineService = new LineNotificationService();
        $lineService->sendGameResult($gameResult);

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
