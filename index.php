<?php

require_once 'vendor/autoload.php';

use Google\CloudFunctions\CloudEvent;
use App\NpbGameResultService;
use App\LineNotificationService;

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
    $year = (int)date('Y');
    $month = (int)date('m');
    $today = date('n/j'); // '6/29' のような形式

    $service = new NpbGameResultService();
    $gameResult = $service->fetchGameResult($year, $month, $today, '阪神');

    if (!$gameResult) {
        // 試合が見つからない場合はログに記録し、通知なし
        error_log('本日の阪神の試合は見つかりませんでした');
        return;
    }

    // LINE で通知
    $lineService = new LineNotificationService();
    $lineService->sendGameResult($gameResult);
}
