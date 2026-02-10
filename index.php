<?php

require_once 'vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\NpbGameResultService;

/**
 * Cloud Functionsのエントリーポイント
 *
 * HTTP エンドポイントとして動作し、本日の阪神の試合情報を取得します
 *
 * @param Request $request HTTPリクエスト
 * @return Response HTTPレスポンス
 */
function handleTigersGame(Request $request): Response
{
    $year = date('Y');
    $month = date('m');
    $today = date('n/j'); // '6/29' のような形式

    $url = "https://npb.jp/games/{$year}/schedule_{$month}_detail.html";

    $service = new NpbGameResultService();
    $gameResult = $service->fetchGameResult($url, $today, '阪神');

    if (!$gameResult) {
        return new \GuzzleHttp\Psr7\Response(
            404,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(
                ['message' => '本日の阪神の試合は見つかりませんでした'],
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            )
        );
    }

    // スコアリンクを取得
    $score_link = $gameResult->getScoreLink();

    if (!$score_link) {
        return new \GuzzleHttp\Psr7\Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(
                [
                    'message' => '本日の阪神の試合情報が見つかりました',
                    'status' => '試合はまだ終了していません',
                    'opponent' => $gameResult->getOpponent(),
                    'date' => $today
                ],
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            )
        );
    }

    // 試合詳細ページへのURLを構築
    $play_by_play_url = "https://npb.jp" . dirname($score_link) . "/playbyplay.html";

    return new \GuzzleHttp\Psr7\Response(
        200,
        ['Content-Type' => 'application/json; charset=utf-8'],
        json_encode(
            [
                'message' => '本日の阪神の試合情報が見つかりました',
                'date' => $today,
                'opponent' => $gameResult->getOpponent(),
                'score' => [
                    'tigers' => $gameResult->getAllyScore(),
                    'opponent' => $gameResult->getOpponentScore()
                ],
                'playByPlayUrl' => $play_by_play_url
            ],
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        )
    );
}
