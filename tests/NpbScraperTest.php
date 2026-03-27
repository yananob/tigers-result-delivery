<?php

namespace Tests;

use App\NpbScraper;
use PHPUnit\Framework\TestCase;

class NpbScraperTest extends TestCase
{
    private NpbScraper $scraper;
    private static string $html;

    public static function setUpBeforeClass(): void
    {
        // The fixture directory is now at the root
        self::$html = file_get_contents(__DIR__ . '/fixture/2026.html');
    }

    protected function setUp(): void
    {
        $this->scraper = new NpbScraper();
        $this->scraper->loadHtml(self::$html);
    }

    public function test指定日の試合情報が見つかること(): void
    {
        $gameNode = $this->scraper->findGameNode('3/27', '阪神');
        $this->assertNotNull($gameNode, "3/27の阪神の試合が見つかるべき");
    }

    public function test存在しない試合は見つからないこと(): void
    {
        $gameNode = $this->scraper->findGameNode('3/27', '存在しないチーム');
        $this->assertNull($gameNode, "存在しないチームの試合は見つからないはず");
    }

    public function testスコアへのリンクが正しく取得できること(): void
    {
        $gameNode = $this->scraper->findGameNode('3/27', '阪神');
        $this->assertNotNull($gameNode);

        $scoreLink = $this->scraper->getScoreLink($gameNode);
        $this->assertEquals('/scores/2026/0327/g-t-01/', $scoreLink, "スコアへのリンクが正しく取得できるべき");
    }

    /**
     * 3/27（金）の試合データをテスト
     */
    public function test2026年3月27日の試合データが正しく取得できること(): void
    {
        $gameNode = $this->scraper->findGameNode('3/27', '阪神');
        $this->assertNotNull($gameNode, "3/27の阪神の試合が見つかるべき");

        $scoreLink = $this->scraper->getScoreLink($gameNode);
        $this->assertEquals('/scores/2026/0327/g-t-01/', $scoreLink, "3/27のスコアリンクが正しい");

        $opponentTeamName = $this->scraper->getOpponentTeamName($gameNode);
        $this->assertEquals('読売ジャイアンツ', $opponentTeamName, "3/27の対戦相手が正しい");

        $opponentScore = $this->scraper->getOpponentScore($gameNode);
        $this->assertEquals('3', $opponentScore, "3/27の相手チームのスコアが正しい");

        $allyScore = $this->scraper->getAllyScore($gameNode);
        $this->assertEquals('1', $allyScore, "3/27の自チームのスコアが正しい");
    }
}
