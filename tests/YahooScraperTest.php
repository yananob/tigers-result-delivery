<?php

namespace Tests;

use App\YahooScraper;
use PHPUnit\Framework\TestCase;

class YahooScraperTest extends TestCase
{
    private YahooScraper $scraper;
    private static string $html;

    public static function setUpBeforeClass(): void
    {
        self::$html = file_get_contents(__DIR__ . '/fixture/yahoo_npb_schedule.html');
    }

    protected function setUp(): void
    {
        $this->scraper = new YahooScraper();
        $this->scraper->loadHtml(self::$html);
    }

    public function test指定日の試合情報が見つかること(): void
    {
        $gameNode = $this->scraper->findGameNode('3/31', '阪神');
        $this->assertNotNull($gameNode, "3/31の阪神の試合が見つかるべき");
    }

    public function test指定日の試合情報が見つかること_ヤクルト(): void
    {
        $gameNode = $this->scraper->findGameNode('3/31', 'ヤクルト');
        $this->assertNotNull($gameNode, "3/31のヤクルトの試合が見つかるべき");
    }

    public function test存在しない試合は見つからないこと(): void
    {
        $gameNode = $this->scraper->findGameNode('3/31', '存在しないチーム');
        $this->assertNull($gameNode, "存在しないチームの試合は見つからないはず");
    }

    public function testスコアへのリンクが正しく取得できること(): void
    {
        $gameNode = $this->scraper->findGameNode('3/31', '阪神');
        $this->assertNotNull($gameNode);

        $scoreLink = $this->scraper->getScoreLink($gameNode);
        $this->assertEquals('/npb/game/2021038642/index', $scoreLink, "スコアへのリンクが正しく取得できるべき");
    }

    public function test阪神の試合データが正しく取得できること(): void
    {
        $gameNode = $this->scraper->findGameNode('3/31', '阪神');
        $this->assertNotNull($gameNode);

        $opponentTeamName = $this->scraper->getOpponentTeamName($gameNode, '阪神');
        $this->assertEquals('DeNA', $opponentTeamName, "対戦相手が正しい");

        $allyScore = $this->scraper->getAllyScore($gameNode, '阪神');
        $this->assertEquals('4', $allyScore, "阪神のスコアが正しい");

        $opponentScore = $this->scraper->getOpponentScore($gameNode, '阪神');
        $this->assertEquals('1', $opponentScore, "相手チームのスコアが正しい");

        $this->assertTrue($this->scraper->isGameFinished($gameNode), "試合は終了しているはず");
    }

    public function testヤクルトの試合データが正しく取得できること(): void
    {
        // ヤクルトはホーム（bb-scoreList__homeName）
        $gameNode = $this->scraper->findGameNode('3/31', 'ヤクルト');
        $this->assertNotNull($gameNode);

        $opponentTeamName = $this->scraper->getOpponentTeamName($gameNode, 'ヤクルト');
        $this->assertEquals('広島', $opponentTeamName, "対戦相手が正しい");

        $allyScore = $this->scraper->getAllyScore($gameNode, 'ヤクルト');
        $this->assertEquals('6', $allyScore, "ヤクルトのスコアが正しい");

        $opponentScore = $this->scraper->getOpponentScore($gameNode, 'ヤクルト');
        $this->assertEquals('3', $opponentScore, "相手チームのスコアが正しい");

        $this->assertFalse($this->scraper->isGameFinished($gameNode), "ヤクルトの試合は終了していないはず（7回裏）");
    }

    public function test詳細ページから戦評などが取得できること(): void
    {
        $detailHtml = file_get_contents(__DIR__ . '/fixture/yahoo_game_detail.html');
        $this->scraper->loadHtml($detailHtml);

        $review = $this->scraper->getGameReview();
        $this->assertStringContainsString('巨人は0-1で迎えた7回裏', $review);

        $scoringPlays = $this->scraper->getScoringPlays();
        $this->assertCount(2, $scoringPlays);
        $this->assertStringContainsString('4回表：2アウト満塁の2-2からレフトへの先制タイムリーヒット！', $scoringPlays[0]);

        $homeRuns = $this->scraper->getHomeRuns();
        // 今回のフィクスチャでは本塁打なし
        $this->assertEmpty($homeRuns);
    }
}
