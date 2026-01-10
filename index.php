<?php

require_once 'vendor/autoload.php';

use PHPHtmlParser\Dom;

function main($event, $context)
{
    echo "処理を開始します\n";

    $year = date('Y');
    $month = date('m');
    $today = date('n/j'); // '6/29' のような形式

    $url = "https://npb.jp/games/{$year}/schedule_{$month}_detail.html";
    echo "対象URL: {$url}\n";

    $dom = new Dom;
    try {
        $dom->loadFromUrl($url);
    } catch (Exception $e) {
        echo 'URLの読み込みに失敗しました: ',  $e->getMessage(), "\n";
        return 'Function failed.';
    }

    $game_row = null;
    $current_date = '';

    // 日程テーブルの全tr要素をループ
    foreach ($dom->find('tr') as $row) {
        // 日付が含まれるtd要素を取得
        $date_cell = $row->findOne('td[rowspan]');
        if ($date_cell) {
            // "6/29（土）"のような形式から日付部分を抽出
            $full_date_text = trim($date_cell->text);
            if (preg_match('/^(\d+\/\d+)/', $full_date_text, $matches)) {
                $current_date = $matches[1];
            }
        }

        // 今日の日付の行で、「阪神」が含まれるものを探す
        if ($current_date === $today && strpos($row->innerHtml, '阪神') !== false) {
            $game_row = $row;
            break;
        }
    }

    if ($game_row) {
        echo "本日の阪神の試合情報が見つかりました。\n";

        $score_link = $game_row->findOne('a');
        if ($score_link) {
            $game_url = $score_link->getAttribute('href');
            $play_by_play_url = "https://npb.jp" . dirname($game_url) . "/playbyplay.html";
            echo "試合詳細URL: {$play_by_play_url}\n";

            try {
                $dom->loadFromUrl($play_by_play_url);
                // ここにサマリー作成処理を追加
            } catch (Exception $e) {
                echo '試合詳細ページの読み込みに失敗しました: ',  $e->getMessage(), "\n";
                return 'Function failed.';
            }

        } else {
            echo "試合はまだ終了していません。\n";
        }
    } else {
        echo "本日の阪神の試合は見つかりませんでした。\n";
    }

    return 'Function finished.';
}
