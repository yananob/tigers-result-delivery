<?php

namespace Tests;

use App\GameResult;
use App\NpbGameResultService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * NpbGameResultService のテスト
 *
 * note.md の試合データを使用してテストを実施
 */
class NpbGameResultServiceTest extends TestCase
{
    private NpbGameResultService $service;
    private static string $html;

    public static function setUpBeforeClass(): void
    {
        // テストフィクスチャ（HTML）を読み込み
        self::$html = file_get_contents(__DIR__ . '/fixture/schedule_03_detail.html');
    }

    protected function setUp(): void
    {
        $this->service = new NpbGameResultService();
    }

    /**
     * スケジュール URL の構築をテスト
     */
    public function testBuildScheduleUrlWithValidParams(): void
    {
        $url = $this->service->buildScheduleUrl(2024, 3);

        $this->assertEquals(
            'https://npb.jp/games/2024/schedule_3_detail.html',
            $url,
            "スケジュール URL が正しく構築されるべき"
        );
    }

    /**
     * 異なる年月でのスケジュール URL の構築をテスト
     */
    public function testBuildScheduleUrlWithDifferentYearMonth(): void
    {
        $url = $this->service->buildScheduleUrl(2023, 8);

        $this->assertEquals(
            'https://npb.jp/games/2023/schedule_8_detail.html',
            $url,
            "異なる年月でも正しく URL が構築されるべき"
        );
    }

    /**
     * 3/29（金）の試合データ取得をテスト
     * note.md: 巨人 4-0 阪神（相手4点、自軍0点）
     */
    public function testFetchGameResult20240329(): void
    {
        // テストフィクスチャを使用した統合テスト
        // 実際のデータで検証
        $result = $this->fetchGameResultFromFixture('3/29', '阪神');

        $this->assertNotNull($result, "3/29の試合データが取得できるべき");
        $this->assertEquals('巨人', $result->getOpponent(), "対戦相手は巨人");
        $this->assertEquals(0, $result->getAllyScore(), "自軍のスコアは0");
        $this->assertEquals(4, $result->getOpponentScore(), "相手のスコアは4");
        $this->assertEquals('/scores/2024/0329/g-t-01/', $result->getScoreLink());
    }

    /**
     * 3/30（土）の試合データ取得をテスト
     * note.md: 巨人 5-0 阪神（相手5点、自軍0点）
     */
    public function testFetchGameResult20240330(): void
    {
        $result = $this->fetchGameResultFromFixture('3/30', '阪神');

        $this->assertNotNull($result, "3/30の試合データが取得できるべき");
        $this->assertEquals('巨人', $result->getOpponent(), "対戦相手は巨人");
        $this->assertEquals(0, $result->getAllyScore(), "自軍のスコアは0");
        $this->assertEquals(5, $result->getOpponentScore(), "相手のスコアは5");
        $this->assertEquals('/scores/2024/0330/g-t-02/', $result->getScoreLink());
    }

    /**
     * 3/31（日）の試合データ取得をテスト
     * note.md: 巨人 0-5 阪神（相手0点、自軍5点）
     */
    public function testFetchGameResult20240331(): void
    {
        $result = $this->fetchGameResultFromFixture('3/31', '阪神');

        $this->assertNotNull($result, "3/31の試合データが取得できるべき");
        $this->assertEquals('巨人', $result->getOpponent(), "対戦相手は巨人");
        $this->assertEquals(5, $result->getAllyScore(), "自軍のスコアは5");
        $this->assertEquals(0, $result->getOpponentScore(), "相手のスコアは0");
        $this->assertEquals('/scores/2024/0331/g-t-03/', $result->getScoreLink());
    }

    /**
     * 存在しない試合の取得テスト
     */
    public function testFetchGameResultNotFound(): void
    {
        $result = $this->fetchGameResultFromFixture('4/1', '阪神');
        $this->assertNull($result, "存在しない日付の試合は null を返すべき");
    }

    /**
     * テストフィクスチャから GameResult を取得するヘルパーメソッド
     *
     * @param string $date 日付（M/D 形式）
     * @param string $team チーム名
     * @return GameResult|null
     */
    private function fetchGameResultFromFixture(string $date, string $team): ?GameResult
    {
        // NpbScraper を直接使用して GameResult を構築
        $scraper = new \App\NpbScraper();
        $scraper->loadHtml(self::$html);

        $gameNode = $scraper->findGameNode($date, $team);
        if ($gameNode === null) {
            return null;
        }

        $opponent = $scraper->getOpponentTeamName($gameNode);
        if ($opponent === null) {
            return null;
        }

        $scoreLink = $scraper->getScoreLink($gameNode);
        $allyScoreStr = $scraper->getAllyScore($gameNode);
        $opponentScoreStr = $scraper->getOpponentScore($gameNode);

        $allyScore = $allyScoreStr !== null && is_numeric($allyScoreStr) ? (int)$allyScoreStr : null;
        $opponentScore = $opponentScoreStr !== null && is_numeric($opponentScoreStr) ? (int)$opponentScoreStr : null;

        return new GameResult(
            $date,
            $team,
            $opponent,
            $scoreLink,
            $allyScore,
            $opponentScore
        );
    }

    /**
     * GameResult オブジェクトが正しく作成されるかテスト
     */
    public function testGameResultConstruction(): void
    {
        $result = new GameResult(
            '3/29',
            '阪神',
            '巨人',
            '/scores/2024/0329/g-t-01/',
            0,
            4
        );

        $this->assertEquals('3/29', $result->getDate(), "日付が正しく保持される");
        $this->assertEquals('阪神', $result->getTeam(), "チーム名が正しく保持される");
        $this->assertEquals('巨人', $result->getOpponent(), "対戦相手が正しく保持される");
        $this->assertEquals('/scores/2024/0329/g-t-01/', $result->getScoreLink(), "スコアリンクが正しく保持される");
        $this->assertEquals(0, $result->getAllyScore(), "自チームのスコアが正しく保持される");
        $this->assertEquals(4, $result->getOpponentScore(), "対戦相手のスコアが正しく保持される");
    }

    /**
     * GameResult オブジェクトでスコアが null の場合をテスト
     */
    public function testGameResultWithNullScores(): void
    {
        $result = new GameResult(
            '3/29',
            '阪神',
            '巨人',
            null,
            null,
            null
        );

        $this->assertNull($result->getScoreLink(), "スコアリンクが null で保持される");
        $this->assertNull($result->getAllyScore(), "自チームのスコアが null で保持される");
        $this->assertNull($result->getOpponentScore(), "対戦相手のスコアが null で保持される");
    }
}
