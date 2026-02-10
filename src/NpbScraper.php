<?php

namespace App;

use DOMDocument;
use DOMXPath;
use DOMNode;

class NpbScraper
{
    private string $html;

    public function loadHtml(string $html): void
    {
        $this->html = $html;
    }

    public function loadFromUrl(string $url): bool
    {
        $html = @file_get_contents($url);
        if ($html === false) {
            return false;
        }
        $this->html = $html;
        return true;
    }

    public function findGameNode(string $target_date, string $team_name): ?DOMNode
    {
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
                $team_node = $xpath->query('.//div[contains(@class, "team") and normalize-space()="' . $team_name . '"]', $row)->item(0);
                if ($team_node) {
                    return $row;
                }
            }
        }
        return null;
    }

    public static function getScoreLink(DOMNode $game_node): ?string
    {
        $doc = $game_node->ownerDocument;
        $xpath = new DOMXPath($doc);
        $score_link_node = $xpath->query('.//a[@href]', $game_node)->item(0);

        if ($score_link_node instanceof \DOMElement) {
            return $score_link_node->getAttribute('href');
        }
        return null;
    }

    public static function getOpponentTeamName(DOMNode $game_node): ?string
    {
        $doc = $game_node->ownerDocument;
        $xpath = new DOMXPath($doc);
        $opponent_node = $xpath->query('.//div[contains(@class, "team1")]', $game_node)->item(0);

        if ($opponent_node) {
            return trim($opponent_node->nodeValue);
        }
        return null;
    }

    public static function getOpponentScore(DOMNode $game_node): ?string
    {
        $doc = $game_node->ownerDocument;
        $xpath = new DOMXPath($doc);
        $score_node = $xpath->query('.//div[contains(@class, "score1")]', $game_node)->item(0);

        if ($score_node) {
            return trim($score_node->nodeValue);
        }
        return null;
    }

    public static function getAllyScore(DOMNode $game_node): ?string
    {
        $doc = $game_node->ownerDocument;
        $xpath = new DOMXPath($doc);
        $score_node = $xpath->query('.//div[contains(@class, "score2")]', $game_node)->item(0);

        if ($score_node) {
            return trim($score_node->nodeValue);
        }
        return null;
    }
}
