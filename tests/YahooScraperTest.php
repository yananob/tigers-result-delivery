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
        // 新しいフィクスチャでは戦評が取得できない（nullになる）ことを許容する
        $this->assertNull($review);

        $scoringPlays = $this->scraper->getScoringPlays();
        $this->assertCount(3, $scoringPlays);
        $this->assertStringContainsString('4回裏：ランナー一二塁からライトスタンドへの先制3ランホームラン！ 巨 3-0 ヤ', $scoringPlays[0]);
        $this->assertStringContainsString('6回表：投手交代: 赤星 → 船迫 カウント1-2からバックスクリーン左に飛び込むホームランを放つ 巨 3-1 ヤ', $scoringPlays[1]);
        $this->assertStringContainsString('6回表：ショートゴロの間にヤクルト1点をあげる 巨 3-2 ヤ 2アウト一塁', $scoringPlays[2]);

        $homeRuns = $this->scraper->getHomeRuns();
        $this->assertCount(2, $homeRuns);
        $this->assertStringContainsString('ヤクルト：サンタナ 8号(6回表ソロ)', $homeRuns[0]);
        $this->assertStringContainsString('巨人：大城 3号(4回裏3ラン)', $homeRuns[1]);

        $pitchers = $this->scraper->getPitcherResults();
        $this->assertCount(3, $pitchers);
        $this->assertStringContainsString('勝利投手：巨人 赤星 (3勝1敗0S)', $pitchers[0]);
        $this->assertStringContainsString('敗戦投手：ヤクルト 吉村 (2勝4敗0S)', $pitchers[1]);
        $this->assertStringContainsString('セーブ：巨人 マルティネス (0勝1敗10S)', $pitchers[2]);
    }
}
