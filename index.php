<?php

require_once 'vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\NpbScraper;

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

    $scraper = new NpbScraper();

    // NPB の日程ページを読み込む
    if (!$scraper->loadFromUrl($url)) {
        return new \GuzzleHttp\Psr7\Response(
            500,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(
                ['error' => 'URLの読み込みに失敗しました'],
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            )
        );
    }

    // 本日の阪神の試合を探す
    $game_node = $scraper->findGameNode($today, '阪神');

    if (!$game_node) {
        return new \GuzzleHttp\Psr7\Response(
            404,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(
                ['message' => '本日の阪神の試合は見つかりませんでした'],
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            )
        );
    }

    // 対戦相手を取得
    $opponent = NpbScraper::getOpponentTeamName($game_node);

    // スコアリンクを取得
    $score_link = NpbScraper::getScoreLink($game_node);

    if (!$score_link) {
        return new \GuzzleHttp\Psr7\Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(
                [
                    'message' => '本日の阪神の試合情報が見つかりました',
                    'status' => '試合はまだ終了していません',
                    'opponent' => $opponent,
                    'date' => $today
                ],
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            )
        );
    }

    // 試合詳細ページへのURLを構築
    $play_by_play_url = "https://npb.jp" . dirname($score_link) . "/playbyplay.html";

    // スコア情報を取得
    $opponent_score = NpbScraper::getOpponentScore($game_node);
    $ally_score = NpbScraper::getAllyScore($game_node);

    return new \GuzzleHttp\Psr7\Response(
        200,
        ['Content-Type' => 'application/json; charset=utf-8'],
        json_encode(
            [
                'message' => '本日の阪神の試合情報が見つかりました',
                'date' => $today,
                'opponent' => $opponent,
                'score' => [
                    'tigers' => $ally_score,
                    'opponent' => $opponent_score
                ],
                'playByPlayUrl' => $play_by_play_url
            ],
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        )
    );
}
