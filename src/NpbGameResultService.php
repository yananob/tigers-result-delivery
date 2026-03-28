<?php

namespace App;

use Monolog\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * NPB ゲーム結果を取得するサービス
 * URL から HTML を取得し、GameResult Entity を返す
 */
class NpbGameResultService
{
    private NpbScraper $scraper;
    private Logger $logger;

    public function __construct()
    {
        $this->scraper = new NpbScraper();
        $this->logger = LoggerFactory::getLogger();
    }

    /**
     * スケジュール URL を構築する
     *
     * @param int $year 年
     * @param int $month 月
     * @return string スケジュール URL
     */
    public function buildScheduleUrl(int $year, int $month): string
    {
        return "https://npb.jp/games/{$year}/schedule_" . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . "_detail.html";
    }

    /**
     * 指定された日付とチーム名のゲーム結果を返す
     *
     * @param int $year 年
     * @param int $month 月
     * @param string $target_date 検索対象の試合日（M/D 形式）
     * @param string $team_name 検索対象のチーム名
     * @return GameResult|null ゲーム結果、見つからない場合は null
     */
    public function fetchGameResult(int $year, int $month, string $target_date, string $team_name): ?GameResult
    {
        $this->logger->info('ゲーム結果の取得を開始', [
            'year' => $year,
            'month' => $month,
            'date' => $target_date,
            'team' => $team_name,
        ]);

        // URL を構築
        $url = $this->buildScheduleUrl($year, $month);
        $this->logger->debug('スケジュール URL を構築', ['url' => $url]);

        // URL から HTML を取得（Guzzle を使用）
        $client = new Client(['timeout' => 10]);
        try {
            $response = $client->request('GET', $url, [
                'headers' => [
                    // Android Chrome の User-Agent
                    'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Mobile Safari/537.36',
                ],
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();
            if ($status !== 200) {
                $this->logger->warning('HTML の取得に失敗', ['url' => $url, 'status' => $status]);
                return null;
            }

            $html = (string)$response->getBody();
            $this->logger->debug('HTML の取得に成功', ['url' => $url, 'size' => strlen($html)]);
        } catch (GuzzleException $e) {
            $this->logger->warning('HTML の取得に失敗（例外）', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }

        // HTML を scraper に渡す
        $this->scraper->loadHtml($html);
        $this->logger->debug('HTML を scraper に読み込み完了');

        // 試合ノードを検索
        $game_node = $this->scraper->findGameNode($target_date, $team_name);
        if ($game_node === null) {
            $this->logger->info('指定された日付とチームの試合が見つかりませんでした', [
                'date' => $target_date,
                'team' => $team_name,
            ]);
            return null;
        }
        $this->logger->info('対象の試合ノードを検出しました');

        // データを抽出
        $opponent = $this->scraper->getOpponentTeamName($game_node);
        if ($opponent === null) {
            $this->logger->warning('対戦相手チーム名の取得に失敗しました');
            return null;
        }

        $scoreLink = $this->scraper->getScoreLink($game_node);
        $allyScoreStr = $this->scraper->getAllyScore($game_node);
        $opponentScoreStr = $this->scraper->getOpponentScore($game_node);

        $this->logger->info('生のスコア情報を抽出しました', [
            'opponent' => $opponent,
            'scoreLink' => $scoreLink,
            'allyScoreRaw' => $allyScoreStr,
            'opponentScoreRaw' => $opponentScoreStr,
        ]);

        // スコアを整数に変換（数値でない場合は null）
        $allyScore = $allyScoreStr !== null && is_numeric($allyScoreStr) ? (int)$allyScoreStr : null;
        $opponentScore = $opponentScoreStr !== null && is_numeric($opponentScoreStr) ? (int)$opponentScoreStr : null;

        $result = new GameResult(
            $target_date,
            $team_name,
            $opponent,
            $scoreLink,
            $allyScore,
            $opponentScore
        );

        $this->logger->info('ゲーム結果を取得完了', [
            'date' => $target_date,
            'ally' => $team_name,
            'opponent' => $opponent,
            'score' => "{$allyScore}-{$opponentScore}",
        ]);

        return $result;
    }
}
