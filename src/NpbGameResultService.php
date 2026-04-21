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
    private YahooScraper $scraper;
    private AiSummaryService $aiSummaryService;
    private Logger $logger;
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->scraper = new YahooScraper();
        $this->aiSummaryService = new AiSummaryService();
        $this->logger = LoggerFactory::getLogger();
        $this->client = $client ?? new Client([
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
            ],
        ]);
    }

    /**
     * スケジュール URL を構築する
     *
     * @param int $year 年
     * @param int $month 月
     * @param int|null $day 日
     * @return string スケジュール URL
     */
    public function buildScheduleUrl(int $year, int $month, ?int $day = null): string
    {
        if ($day !== null) {
            $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
            return "https://baseball.yahoo.co.jp/npb/schedule/?date={$date_str}";
        }
        return "https://baseball.yahoo.co.jp/npb/schedule/";
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

        // target_date (M/D) から日を抽出
        $day = null;
        if (preg_match('/\/(\d+)$/', $target_date, $matches)) {
            $day = (int)$matches[1];
        }

        // URL を構築
        $url = $this->buildScheduleUrl($year, $month, $day);
        $this->logger->debug('スケジュール URL を構築', ['url' => $url]);

        // URL から HTML を取得（Guzzle を使用）
        try {
            $response = $this->client->request('GET', $url, [
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
            $this->logger->info('試合ノードが見つかりません', [
                'date' => $target_date,
                'team' => $team_name,
            ]);
            return null;
        }
        $this->logger->debug('試合ノードを検出');

        // データを抽出
        $opponent = $this->scraper->getOpponentTeamName($game_node, $team_name);
        if ($opponent === null) {
            $this->logger->warning('対戦相手チーム名の取得に失敗');
            return null;
        }
        $this->logger->debug('対戦相手チーム名を取得', ['opponent' => $opponent]);

        $scoreLink = $this->scraper->getScoreLink($game_node);
        $allyScoreStr = $this->scraper->getAllyScore($game_node, $team_name);
        $opponentScoreStr = $this->scraper->getOpponentScore($game_node, $team_name);
        $isFinished = $this->scraper->isGameFinished($game_node);

        $this->logger->debug('スコア情報を取得', [
            'scoreLink' => $scoreLink,
            'allyScore' => $allyScoreStr,
            'opponentScore' => $opponentScoreStr,
            'isFinished' => $isFinished,
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
            $opponentScore,
            $isFinished
        );

        // 試合終了している場合、詳細ページから追加情報を取得
        if ($isFinished && $scoreLink) {
            $this->fetchAndFillDetailInfo($result);
        }

        $this->logger->info('ゲーム結果を取得完了', [
            'date' => $target_date,
            'ally' => $team_name,
            'opponent' => $opponent,
            'score' => "{$allyScore}-{$opponentScore}",
        ]);

        return $result;
    }

    /**
     * 詳細ページから情報を取得して GameResult にセットする
     */
    private function fetchAndFillDetailInfo(GameResult $result): void
    {
        $detailUrl = "https://baseball.yahoo.co.jp" . $result->getScoreLink();
        $this->logger->info('詳細ページの取得を開始', ['url' => $detailUrl]);

        try {
            $response = $this->client->request('GET', $detailUrl, [
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('詳細ページの取得に失敗', ['url' => $detailUrl, 'status' => $response->getStatusCode()]);
                return;
            }

            $html = (string)$response->getBody();
            $this->scraper->loadHtml($html);

            $review = $this->scraper->getGameReview();
            $scoringPlays = $this->scraper->getScoringPlays();
            $homeRuns = $this->scraper->getHomeRuns();

            $result->setScoringPlays($scoringPlays);
            $result->setHomeRuns($homeRuns);

            if ($review) {
                $summary = $this->aiSummaryService->summarize($review, $scoringPlays, $homeRuns);
                $result->setSummary($summary);
            }
        } catch (GuzzleException $e) {
            $this->logger->warning('詳細ページの取得に失敗（例外）', ['url' => $detailUrl, 'error' => $e->getMessage()]);
        }
    }
}
