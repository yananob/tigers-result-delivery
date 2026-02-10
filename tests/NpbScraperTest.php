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
        self::$html = file_get_contents(__DIR__ . '/fixture/schedule_03_detail.html');
    }

    protected function setUp(): void
    {
        $this->scraper = new NpbScraper();
        $this->scraper->loadHtml(self::$html);
    }

    public function testFindGameNodeSuccessfully(): void
    {
        $gameNode = $this->scraper->findGameNode('3/29', '阪神');
        $this->assertNotNull($gameNode, "3/29の阪神の試合が見つかるべき");
    }

    public function testFindGameNodeForNonExistentGame(): void
    {
        $gameNode = $this->scraper->findGameNode('3/29', '存在しないチーム');
        $this->assertNull($gameNode, "存在しないチームの試合は見つからないはず");
    }

    public function testGetScoreLink(): void
    {
        $gameNode = $this->scraper->findGameNode('3/29', '阪神');
        $this->assertNotNull($gameNode);

        $scoreLink = NpbScraper::getScoreLink($gameNode);
        $this->assertEquals('/scores/2024/0329/g-t-01/', $scoreLink, "スコアへのリンクが正しく取得できるべき");
    }

    /**
     * 3/29（金）の試合データをテスト
     */
    public function testMatch20240329(): void
    {
        $gameNode = $this->scraper->findGameNode('3/29', '阪神');
        $this->assertNotNull($gameNode, "3/29の阪神の試合が見つかるべき");

        $scoreLink = NpbScraper::getScoreLink($gameNode);
        $this->assertEquals('/scores/2024/0329/g-t-01/', $scoreLink, "3/29のスコアリンクが正しい");
    }

    /**
     * 3/30（土）の試合データをテスト
     */
    public function testMatch20240330(): void
    {
        $gameNode = $this->scraper->findGameNode('3/30', '阪神');
        $this->assertNotNull($gameNode, "3/30の阪神の試合が見つかるべき");

        $scoreLink = NpbScraper::getScoreLink($gameNode);
        $this->assertEquals('/scores/2024/0330/g-t-02/', $scoreLink, "3/30のスコアリンクが正しい");
    }

    /**
     * 3/31（日）の試合データをテスト
     */
    public function testMatch20240331(): void
    {
        $gameNode = $this->scraper->findGameNode('3/31', '阪神');
        $this->assertNotNull($gameNode, "3/31の阪神の試合が見つかるべき");

        $scoreLink = NpbScraper::getScoreLink($gameNode);
        $this->assertEquals('/scores/2024/0331/g-t-03/', $scoreLink, "3/31のスコアリンクが正しい");
    }
}
