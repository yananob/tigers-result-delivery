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
}
