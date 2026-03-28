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

    public function findGameNode(string $target_date, string $team_name): ?DOMNode
    {
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $this->html);
        $xpath = new DOMXPath($doc);

        // 2026.html のようなメインページ形式を試みる
        // 日付は <h5>3月27日（金）</h5> のような形式
        $date_headers = $xpath->query('//h5');
        foreach ($date_headers as $header) {
            $header_text = trim($header->nodeValue);
            // "3月27日（金）" -> "3/27"
            if (preg_match('/(\d+)月(\d+)日/', $header_text, $matches)) {
                $date_str = (int)$matches[1] . '/' . (int)$matches[2];
                if ($date_str === $target_date) {
                    // 直後の div#score_live_basic もしくは sibling 内の .three_column_* を探す
                    // 実際には <h5> の後の div の中にある
                    $container = $xpath->query('./following-sibling::div', $header)->item(0);
                    if ($container) {
                        // チーム名を含む要素（img の alt/title やテキスト）を探す
                        $game_nodes = $xpath->query('.//div[contains(@class, "three_column_")]', $container);
                        foreach ($game_nodes as $game_node) {
                            if ($this->nodeContainsTeam($xpath, $game_node, $team_name)) {
                                return $game_node;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    private function nodeContainsTeam(DOMXPath $xpath, DOMNode $node, string $team_name): bool
    {
        // テキストノードにチーム名が含まれているか
        if (strpos($node->nodeValue, $team_name) !== false) {
            return true;
        }
        // img の alt または title にチーム名が含まれているか
        $imgs = $xpath->query('.//img', $node);
        foreach ($imgs as $img) {
            if ($img instanceof \DOMElement) {
                if (strpos($img->getAttribute('alt'), $team_name) !== false ||
                    strpos($img->getAttribute('title'), $team_name) !== false) {
                    return true;
                }
            }
        }
        return false;
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

        // 2026.html 形式
        $team1_node = $xpath->query('.//td[contains(@class, "team1")]', $game_node)->item(0);
        if ($team1_node) {
            $img = $xpath->query('.//img', $team1_node)->item(0);
            if ($img instanceof \DOMElement) {
                return $img->getAttribute('alt') ?: $img->getAttribute('title');
            }
            return trim($team1_node->nodeValue);
        }

        return null;
    }

    public function getOpponentScore(DOMNode $game_node): ?string
    {
        $doc = $game_node->ownerDocument;
        $xpath = new DOMXPath($doc);

        // 2026.html 形式
        $scores = $xpath->query('.//td[contains(@class, "score")]', $game_node);
        if ($scores->length >= 1) {
            return trim($scores->item(0)->nodeValue);
        }

        return null;
    }

    public function getAllyScore(DOMNode $game_node): ?string
    {
        $doc = $game_node->ownerDocument;
        $xpath = new DOMXPath($doc);

        // 2026.html 形式
        $scores = $xpath->query('.//td[contains(@class, "score")]', $game_node);
        if ($scores->length >= 2) {
            return trim($scores->item(1)->nodeValue);
        }

        return null;
    }
}
