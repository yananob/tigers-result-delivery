<?php

namespace App;

use DOMDocument;
use DOMXPath;
use DOMNode;

class YahooScraper
{
    private string $html;

    public function loadHtml(string $html): void
    {
        $this->html = $html;
    }

    /**
     * 指定日の試合ノードを探す
     * Yahoo の日程ページでは span.bb-scoreList__date に "3/31（火）" のような形式で入っている
     */
    public function findGameNode(string $target_date, string $team_name): ?DOMNode
    {
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $this->html);
        $xpath = new DOMXPath($doc);

        $game_items = $xpath->query('//li[contains(@class, "bb-scoreList__item")]');
        foreach ($game_items as $item) {
            // 日付のチェック
            $date_node = $xpath->query('.//span[contains(@class, "bb-scoreList__date")]', $item)->item(0);
            if (!$date_node) {
                continue;
            }

            $date_text = trim($date_node->nodeValue);
            // "3/31（火）" から "3/31" を抽出
            if (preg_match('/^(\d+\/\d+)/', $date_text, $matches)) {
                $found_date = $matches[1];
                if ($found_date !== $target_date) {
                    continue;
                }
            } else {
                continue;
            }

            // チーム名のチェック
            $home_team = $xpath->query('.//p[contains(@class, "bb-scoreList__homeName")]', $item)->item(0);
            $away_team = $xpath->query('.//p[contains(@class, "bb-scoreList__awayName")]', $item)->item(0);

            if (
                ($home_team && strpos($home_team->nodeValue, $team_name) !== false) ||
                ($away_team && strpos($away_team->nodeValue, $team_name) !== false)
            ) {
                return $item;
            }
        }

        return null;
    }

    public function getScoreLink(DOMNode $game_node): ?string
    {
        $xpath = new DOMXPath($game_node->ownerDocument);
        $link_node = $xpath->query('.//a[contains(@class, "bb-scoreList__card")]', $game_node)->item(0);

        if ($link_node instanceof \DOMElement) {
            return $link_node->getAttribute('href');
        }
        return null;
    }

    public function getOpponentTeamName(DOMNode $game_node, string $ally_team_name): ?string
    {
        $xpath = new DOMXPath($game_node->ownerDocument);
        $home_team = $xpath->query('.//p[contains(@class, "bb-scoreList__homeName")]', $game_node)->item(0);
        $away_team = $xpath->query('.//p[contains(@class, "bb-scoreList__awayName")]', $game_node)->item(0);

        if ($home_team && strpos($home_team->nodeValue, $ally_team_name) !== false) {
            return $away_team ? trim($away_team->nodeValue) : null;
        }

        if ($away_team && strpos($away_team->nodeValue, $ally_team_name) !== false) {
            return $home_team ? trim($home_team->nodeValue) : null;
        }

        return null;
    }

    public function getAllyScore(DOMNode $game_node, string $ally_team_name): ?string
    {
        $xpath = new DOMXPath($game_node->ownerDocument);
        $home_team = $xpath->query('.//p[contains(@class, "bb-scoreList__homeName")]', $game_node)->item(0);

        if ($home_team && strpos($home_team->nodeValue, $ally_team_name) !== false) {
            $score_node = $xpath->query('.//span[contains(@class, "bb-scoreList__homeScore")]', $game_node)->item(0);
        } else {
            $score_node = $xpath->query('.//span[contains(@class, "bb-scoreList__awayScore")]', $game_node)->item(0);
        }

        return $score_node ? trim($score_node->nodeValue) : null;
    }

    public function getOpponentScore(DOMNode $game_node, string $ally_team_name): ?string
    {
        $xpath = new DOMXPath($game_node->ownerDocument);
        $home_team = $xpath->query('.//p[contains(@class, "bb-scoreList__homeName")]', $game_node)->item(0);

        if ($home_team && strpos($home_team->nodeValue, $ally_team_name) !== false) {
            $score_node = $xpath->query('.//span[contains(@class, "bb-scoreList__awayScore")]', $game_node)->item(0);
        } else {
            $score_node = $xpath->query('.//span[contains(@class, "bb-scoreList__homeScore")]', $game_node)->item(0);
        }

        return $score_node ? trim($score_node->nodeValue) : null;
    }

    public function isGameFinished(DOMNode $game_node): bool
    {
        $xpath = new DOMXPath($game_node->ownerDocument);
        $state_node = $xpath->query('.//p[contains(@class, "bb-scoreList__state")]', $game_node)->item(0);

        if ($state_node) {
            return strpos($state_node->nodeValue, '試合終了') !== false;
        }

        return false;
    }

    /**
     * 戦評を取得する
     */
    public function getGameReview(): ?string
    {
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $this->html);
        $xpath = new DOMXPath($doc);

        $nodes = $xpath->query('//h2[contains(text(), "戦評")]/following::p[contains(@class, "bb-paragraph")]');
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->nodeValue);
        }
        return null;
    }

    /**
     * スコアプレーを取得する
     * @return string[]
     */
    public function getScoringPlays(): array
    {
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $this->html);
        $xpath = new DOMXPath($doc);

        $plays = [];
        $items = $xpath->query('//li[contains(@class, "bb-scorePlay__item")]');
        foreach ($items as $item) {
            $inningNode = $xpath->query('.//p[contains(@class, "bb-scorePlay__inning")]', $item)->item(0);
            if (!$inningNode) {
                continue;
            }

            $detailNodes = $xpath->query('.//div[contains(@class, "bb-scorePlay__detail")]', $item);
            $detailTexts = [];
            foreach ($detailNodes as $detailNode) {
                $pNodes = $xpath->query('.//p', $detailNode);
                foreach ($pNodes as $pNode) {
                    $text = trim(preg_replace('/\s+/', ' ', $pNode->nodeValue));
                    if ($text !== '') {
                        $detailTexts[] = $text;
                    }
                }
            }

            if (!empty($detailTexts)) {
                $inningText = trim($inningNode->nodeValue);
                $plays[] = "{$inningText}：" . implode(' ', $detailTexts);
            }
        }
        return $plays;
    }

    /**
     * 本塁打情報を取得する
     * @return string[]
     */
    public function getHomeRuns(): array
    {
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $this->html);
        $xpath = new DOMXPath($doc);

        $homeRuns = [];
        $header = $xpath->query('//h2[contains(text(), "本塁打")]')->item(0);
        if ($header) {
            $table = $xpath->query('following::table[contains(@class, "bb-gameLeftTable")]', $header)->item(0);
            if ($table) {
                $rows = $xpath->query('.//tr', $table);
                foreach ($rows as $row) {
                    $team = $xpath->query('.//th', $row)->item(0);
                    $hrItems = $xpath->query('.//li[contains(@class, "bb-gameLeftTable__homerun")]', $row);
                    if ($team && $hrItems->length > 0) {
                        $hrTexts = [];
                        foreach ($hrItems as $hrItem) {
                            $hrTexts[] = trim(preg_replace('/\s+/', ' ', $hrItem->nodeValue));
                        }
                        $homeRuns[] = trim($team->nodeValue) . "：" . implode(', ', $hrTexts);
                    }
                }
            }
        }
        return $homeRuns;
    }
}
