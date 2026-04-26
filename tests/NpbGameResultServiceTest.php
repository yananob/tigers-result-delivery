<?php

namespace Tests;

use App\GameResult;
use App\NpbGameResultService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * NpbGameResultService のテスト (Yahoo 版)
 */
class NpbGameResultServiceTest extends TestCase
{
    /**
     * スケジュール URL の構築をテスト
     */
    public function testBuildScheduleUrlWithValidParams(): void
    {
        $service = new NpbGameResultService();
        $url = $service->buildScheduleUrl(2021, 3, 31);

        $this->assertEquals(
            'https://baseball.yahoo.co.jp/npb/schedule/?date=2021-03-31',
            $url,
            "スケジュール URL が正しく構築されるべき"
        );
    }

    /**
     * 3/31（火）の試合データ取得をテスト
     */
    public function testFetchGameResultYahoo331(): void
    {
        // フィクスチャを読み込む
        $html = file_get_contents(__DIR__ . '/fixture/yahoo_npb_schedule.html');
        $detailHtml = file_get_contents(__DIR__ . '/fixture/yahoo_game_detail.html');

        // Guzzle のモックを作成
        $mock = new MockHandler([
            new Response(200, [], $html),
            new Response(200, [], $detailHtml),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new NpbGameResultService($client);
        $result = $service->fetchGameResult(2021, 3, '3/31', '阪神');

        $this->assertNotNull($result, "3/31の試合データが取得できるべき");
        $this->assertEquals('DeNA', $result->getOpponent(), "対戦相手はDeNA");
        $this->assertEquals(4, $result->getAllyScore(), "自軍のスコアは4");
        $this->assertEquals(1, $result->getOpponentScore(), "相手のスコアは1");
        $this->assertEquals('/npb/game/2021038642/index', $result->getScoreLink());
        $this->assertTrue($result->isFinished(), "3/31の試合は終了しているべき");
    }

    /**
     * 存在しない試合の取得テスト
     */
    public function testFetchGameResultNotFound(): void
    {
        // フィクスチャを読み込む
        $html = file_get_contents(__DIR__ . '/fixture/yahoo_npb_schedule.html');

        // Guzzle のモックを作成
        $mock = new MockHandler([
            new Response(200, [], $html),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new NpbGameResultService($client);
        $result = $service->fetchGameResult(2021, 3, '4/1', '阪神');
        $this->assertNull($result, "存在しない日付の試合は null を返すべき");
    }

    /**
     * GameResult オブジェクトが正しく作成されるかテスト
     */
    public function testGameResultConstruction(): void
    {
        $result = new GameResult(
            '3/31',
            '阪神',
            'DeNA',
            '/npb/game/2021038642/index',
            4,
            1,
            true
        );

        $this->assertEquals('3/31', $result->getDate(), "日付が正しく保持される");
        $this->assertEquals('阪神', $result->getTeam(), "チーム名が正しく保持される");
        $this->assertEquals('DeNA', $result->getOpponent(), "対戦相手が正しく保持される");
        $this->assertEquals('/npb/game/2021038642/index', $result->getScoreLink(), "スコアリンクが正しく保持される");
        $this->assertEquals(4, $result->getAllyScore(), "自チームのスコアが正しく保持される");
        $this->assertEquals(1, $result->getOpponentScore(), "対戦相手のスコアが正しく保持される");
        $this->assertTrue($result->isFinished(), "終了フラグが正しく保持される");
    }
}
