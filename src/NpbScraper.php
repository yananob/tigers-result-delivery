<?php

namespace App;

use DOMDocument;
use DOMXPath;
use DOMNode;

class NpbScraper
{
    private string $html;
    private int $targetTeamPosition = 0; // 1 or 2, 0 means not found

    public function loadHtml(string $html): void
    {
        $this->html = $html;
    }

    public function findGameNode(string $target_date, string $team_name): ?DOMNode
    {
        $this->targetTeamPosition = 0;

        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $this->html);
        $xpath = new DOMXPath($doc);

        $rows = $xpath->query('//table//tr');
        $current_date = '';

        foreach ($rows as $row) {
            $date_node = $xpath->query('./th[@rowspan]', $row)->item(0);
            if ($date_node) {
                $full_date_text = trim($date_node->nodeValue);
                if (preg_match('/^(\d+\/\d+)/', $full_date_text, $matches)) {
                    $current_date = $matches[1];
                }
            }
            if ($current_date === $target_date) {
                // team1 か team2 のいずれかにチーム名が含まれているか確認
                $team1_node = $xpath->query('.//div[contains(@class, "team1") and normalize-space()="' . $team_name . '"]', $row)->item(0);
                if ($team1_node) {
                    $this->targetTeamPosition = 1;
                    return $row;
                }

                $team2_node = $xpath->query('.//div[contains(@class, "team2") and normalize-space()="' . $team_name . '"]', $row)->item(0);
                if ($team2_node) {
                    $this->targetTeamPosition = 2;
                    return $row;
                }
            }
        }
        return null;
    }

    public function getScoreLink(DOMNode $game_node): ?string
    {
        $doc = $game_node->ownerDocument;
        $xpath = new DOMXPath($doc);
        $score_link_node = $xpath->query('.//a[@href]', $game_node)->item(0);

        if ($score_link_node instanceof \DOMElement) {
            return $score_link_node->getAttribute('href');
        }
        return null;
    }

    public function getOpponentTeamName(DOMNode $game_node): ?string
    {
        $doc = $game_node->ownerDocument;
        $xpath = new DOMXPath($doc);
        
        // ターゲットチームが team1 なら opponent は team2、逆も然り
        $opponentClass = ($this->targetTeamPosition === 1) ? 'team2' : 'team1';
        $opponent_node = $xpath->query('.//div[contains(@class, "' . $opponentClass . '")]', $game_node)->item(0);

        if ($opponent_node) {
            return trim($opponent_node->nodeValue);
        }
        return null;
    }

    public function getOpponentScore(DOMNode $game_node): ?string
    {
        $doc = $game_node->ownerDocument;
        $xpath = new DOMXPath($doc);
        
        // ターゲットチームが team1 なら opponent score は score2
        $scoreClass = ($this->targetTeamPosition === 1) ? 'score2' : 'score1';
        $score_node = $xpath->query('.//div[contains(@class, "' . $scoreClass . '")]', $game_node)->item(0);

        if ($score_node) {
            return trim($score_node->nodeValue);
        }
        return null;
    }

    public function getAllyScore(DOMNode $game_node): ?string
    {
        $doc = $game_node->ownerDocument;
        $xpath = new DOMXPath($doc);
        
        // ターゲットチームが team1 なら ally score は score1
        $scoreClass = ($this->targetTeamPosition === 1) ? 'score1' : 'score2';
        $score_node = $xpath->query('.//div[contains(@class, "' . $scoreClass . '")]', $game_node)->item(0);

        if ($score_node) {
            return trim($score_node->nodeValue);
        }
        return null;
    }
}
