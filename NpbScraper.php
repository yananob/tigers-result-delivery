<?php

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

        $rows = $xpath->query('//table[contains(@class, "schedule_list")]//tr');
        $current_date = '';

        foreach ($rows as $row) {
            $date_node = $xpath->query('./td[@rowspan]', $row)->item(0);
            if ($date_node) {
                $full_date_text = trim($date_node->nodeValue);
                if (preg_match('/^(\d+\/\d+)/', $full_date_text, $matches)) {
                    $current_date = $matches[1];
                }
            }
            if ($current_date === $target_date) {
                $team_node = $xpath->query('.//div[contains(@class, "team_name") and normalize-space()="' . $team_name . '"]', $row)->item(0);
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
        $score_link_node = $xpath->query('.//td[contains(@class, "score")]/a', $game_node)->item(0);

        if ($score_link_node) {
            return $score_link_node->getAttribute('href');
        }
        return null;
    }
}
