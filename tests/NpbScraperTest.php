<?php

namespace Tests;

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
        $this->assertEquals('/scores/2024/0329/g-t-01/index.html', $scoreLink, "スコアへのリンクが正しく取得できるべき");
    }
}
