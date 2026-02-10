<?php

namespace App;

/**
 * NPB ゲーム結果を取得するサービス
 * URL から HTML を取得し、GameResult Entity を返す
 */
class NpbGameResultService
{
    private NpbScraper $scraper;

    public function __construct()
    {
        $this->scraper = new NpbScraper();
    }

    /**
     * URL から HTML を取得し、指定された日付とチーム名のゲーム結果を返す
     *
     * @param string $url ゲーム情報を含む HTML ページの URL
     * @param string $target_date 検索対象の試合日（M/D 形式）
     * @param string $team_name 検索対象のチーム名
     * @return GameResult|null ゲーム結果、見つからない場合は null
     */
    public function fetchGameResult(string $url, string $target_date, string $team_name): ?GameResult
    {
        // URL から HTML を取得
        $html = @file_get_contents($url);
        if ($html === false) {
            return null;
        }

        // HTML を scraper に渡す
        $this->scraper->loadHtml($html);

        // 試合ノードを検索
        $game_node = $this->scraper->findGameNode($target_date, $team_name);
        if ($game_node === null) {
            return null;
        }

        // データを抽出
        $opponent = $this->scraper->getOpponentTeamName($game_node);
        if ($opponent === null) {
            return null;
        }

        $scoreLink = $this->scraper->getScoreLink($game_node);
        $allyScoreStr = $this->scraper->getAllyScore($game_node);
        $opponentScoreStr = $this->scraper->getOpponentScore($game_node);

        // スコアを整数に変換（数値でない場合は null）
        $allyScore = $allyScoreStr !== null && is_numeric($allyScoreStr) ? (int)$allyScoreStr : null;
        $opponentScore = $opponentScoreStr !== null && is_numeric($opponentScoreStr) ? (int)$opponentScoreStr : null;

        return new GameResult(
            $target_date,
            $team_name,
            $opponent,
            $scoreLink,
            $allyScore,
            $opponentScore
        );
    }
}
