<?php

namespace App;

use Monolog\Logger;

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
        return "https://npb.jp/games/{$year}/schedule_{$month}_detail.html";
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

        // URL から HTML を取得
        $html = @file_get_contents($url);
        if ($html === false) {
            $this->logger->warning('HTML の取得に失敗', ['url' => $url]);
            return null;
        }
        $this->logger->debug('HTML の取得に成功', ['url' => $url, 'size' => strlen($html)]);

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
        $opponent = $this->scraper->getOpponentTeamName($game_node);
        if ($opponent === null) {
            $this->logger->warning('対戦相手チーム名の取得に失敗');
            return null;
        }
        $this->logger->debug('対戦相手チーム名を取得', ['opponent' => $opponent]);

        $scoreLink = $this->scraper->getScoreLink($game_node);
        $allyScoreStr = $this->scraper->getAllyScore($game_node);
        $opponentScoreStr = $this->scraper->getOpponentScore($game_node);

        $this->logger->debug('スコア情報を取得', [
            'scoreLink' => $scoreLink,
            'allyScore' => $allyScoreStr,
            'opponentScore' => $opponentScoreStr,
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
