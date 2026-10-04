<?php

namespace App;

use DOMDocument;
use DOMNode;
use DOMXPath;

class YahooScraper
{
    private ?DOMXPath $xpath = null;

    public function loadHtml(string $html): void
    {
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $this->xpath = new DOMXPath($doc);
    }

    /**
     * 指定日の試合ノードを探す
     * Yahoo の日程ページでは span.bb-scoreList__date に "3/31（火）" のような形式で入っている
     */
    public function findGameNode(string $target_date, string $team_name): ?DOMNode
    {
        if ($this->xpath === null) {
            return null;
        }

        $game_items = $this->xpath->query('//li[contains(@class, "bb-scoreList__item")]');
        if ($game_items === false) {
            return null;
        }

        foreach ($game_items as $item) {
            // 日付のチェック
            $date_node = $this->xpath->query('.//span[contains(@class, "bb-scoreList__date")]', $item)->item(0);
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
            $home_team = $this->xpath->query('.//p[contains(@class, "bb-scoreList__homeName")]', $item)->item(0);
            $away_team = $this->xpath->query('.//p[contains(@class, "bb-scoreList__awayName")]', $item)->item(0);

            if (
                ($home_team && str_contains($home_team->nodeValue, $team_name)) ||
                ($away_team && str_contains($away_team->nodeValue, $team_name))
            ) {
                return $item;
            }
        }

        return null;
    }

    public function getScoreLink(DOMNode $game_node): ?string
    {
        $xpath = $this->getXpathForNode($game_node);
        $link_node = $xpath->query('.//a[contains(@class, "bb-scoreList__card")]', $game_node)->item(0);

        if ($link_node instanceof \DOMElement) {
            return $link_node->getAttribute('href');
        }
        return null;
    }

    public function getOpponentTeamName(DOMNode $game_node, string $ally_team_name): ?string
    {
        $xpath = $this->getXpathForNode($game_node);
        $home_team = $xpath->query('.//p[contains(@class, "bb-scoreList__homeName")]', $game_node)->item(0);
        $away_team = $xpath->query('.//p[contains(@class, "bb-scoreList__awayName")]', $game_node)->item(0);

        if ($home_team && str_contains($home_team->nodeValue, $ally_team_name)) {
            return $away_team ? trim($away_team->nodeValue) : null;
        }

        if ($away_team && str_contains($away_team->nodeValue, $ally_team_name)) {
            return $home_team ? trim($home_team->nodeValue) : null;
        }

        return null;
    }

    public function getAllyScore(DOMNode $game_node, string $ally_team_name): ?string
    {
        $xpath = $this->getXpathForNode($game_node);
        $home_team = $xpath->query('.//p[contains(@class, "bb-scoreList__homeName")]', $game_node)->item(0);

        if ($home_team && str_contains($home_team->nodeValue, $ally_team_name)) {
            $score_node = $xpath->query('.//span[contains(@class, "bb-scoreList__homeScore")]', $game_node)->item(0);
        } else {
            $score_node = $xpath->query('.//span[contains(@class, "bb-scoreList__awayScore")]', $game_node)->item(0);
        }

        return $score_node ? trim($score_node->nodeValue) : null;
    }

    public function getOpponentScore(DOMNode $game_node, string $ally_team_name): ?string
    {
        $xpath = $this->getXpathForNode($game_node);
        $home_team = $xpath->query('.//p[contains(@class, "bb-scoreList__homeName")]', $game_node)->item(0);

        if ($home_team && str_contains($home_team->nodeValue, $ally_team_name)) {
            $score_node = $xpath->query('.//span[contains(@class, "bb-scoreList__awayScore")]', $game_node)->item(0);
        } else {
            $score_node = $xpath->query('.//span[contains(@class, "bb-scoreList__homeScore")]', $game_node)->item(0);
        }

        return $score_node ? trim($score_node->nodeValue) : null;
    }

    public function isGameFinished(DOMNode $game_node): bool
    {
        $xpath = $this->getXpathForNode($game_node);
        $state_node = $xpath->query('.//p[contains(@class, "bb-scoreList__state")]', $game_node)->item(0);

        if ($state_node) {
            return str_contains($state_node->nodeValue, '試合終了');
        }

        return false;
    }

    /**
     * 戦評を取得する
     */
    public function getGameReview(): ?string
    {
        if ($this->xpath === null) {
            return null;
        }

        $nodes = $this->xpath->query('//h2[contains(text(), "戦評")]/following::p[contains(@class, "bb-paragraph")]');
        if ($nodes && $nodes->length > 0) {
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
        if ($this->xpath === null) {
            return [];
        }

        $plays = [];
        $items = $this->xpath->query('//li[contains(@class, "bb-scorePlay__item")]');
        if (!$items) {
            return [];
        }

        foreach ($items as $item) {
            $inningNode = $this->xpath->query('.//p[contains(@class, "bb-scorePlay__inning")]', $item)->item(0);
            if (!$inningNode) {
                continue;
            }

            $detailNodes = $this->xpath->query('.//div[contains(@class, "bb-scorePlay__detail")]', $item);
            $detailTexts = [];
            if ($detailNodes) {
                foreach ($detailNodes as $detailNode) {
                    $pNodes = $this->xpath->query('.//p', $detailNode);
                    if ($pNodes) {
                        foreach ($pNodes as $pNode) {
                            $text = trim(preg_replace('/\s+/', ' ', $pNode->nodeValue) ?? '');
                            if ($text !== '') {
                                $detailTexts[] = $text;
                            }
                        }
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
     * 責任投手情報を取得する
     * @return string[]
     */
    public function getPitcherResults(): array
    {
        if ($this->xpath === null) {
            return [];
        }

        $pitchers = [];
        $header = $this->xpath->query('//h2[contains(text(), "責任投手")]')->item(0);
        if ($header) {
            $table = $this->xpath->query('following::table[contains(@class, "bb-gameLeftTable")]', $header)->item(0);
            if ($table) {
                $rows = $this->xpath->query('.//tr', $table);
                if ($rows) {
                    foreach ($rows as $row) {
                        $role = $this->xpath->query('.//th', $row)->item(0);
                        $dataNode = $this->xpath->query('.//td', $row)->item(0);
                        if ($role && $dataNode) {
                            $roleText = trim($role->nodeValue);
                            $statsText = trim(preg_replace('/\s+/', ' ', $dataNode->nodeValue) ?? '');
                            if ($statsText !== '') {
                                $pitchers[] = "{$roleText}：{$statsText}";
                            }
                        }
                    }
                }
            }
        }
        return $pitchers;
    }

    /**
     * 本塁打情報を取得する
     * @return string[]
     */
    public function getHomeRuns(): array
    {
        if ($this->xpath === null) {
            return [];
        }

        $homeRuns = [];
        $header = $this->xpath->query('//h2[contains(text(), "本塁打")]')->item(0);
        if ($header) {
            $table = $this->xpath->query('following::table[contains(@class, "bb-gameLeftTable")]', $header)->item(0);
            if ($table) {
                $rows = $this->xpath->query('.//tr', $table);
                if ($rows) {
                    foreach ($rows as $row) {
                        $team = $this->xpath->query('.//th', $row)->item(0);
                        $hrItems = $this->xpath->query('.//li[contains(@class, "bb-gameLeftTable__homerun")]', $row);
                        if ($team && $hrItems && $hrItems->length > 0) {
                            $hrTexts = [];
                            foreach ($hrItems as $hrItem) {
                                $hrTexts[] = trim(preg_replace('/\s+/', ' ', $hrItem->nodeValue) ?? '');
                            }
                            $homeRuns[] = trim($team->nodeValue) . "：" . implode(', ', $hrTexts);
                        }
                    }
                }
            }
        }
        return $homeRuns;
    }

    private function getXpathForNode(DOMNode $node): DOMXPath
    {
        if ($this->xpath !== null) {
            return $this->xpath;
        }
        return new DOMXPath($node->ownerDocument ?? new DOMDocument());
    }
}
