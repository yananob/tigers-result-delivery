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
 * NpbGameResultService のテスト
 */
class NpbGameResultServiceTest extends TestCase
{
    /**
     * スケジュール URL の構築をテスト
     */
    public function testBuildScheduleUrlWithValidParams(): void
    {
        $service = new NpbGameResultService();
        $url = $service->buildScheduleUrl(2026, 3);

        $this->assertEquals(
            'https://npb.jp/games/2026/',
            $url,
            "スケジュール URL が正しく構築されるべき"
        );
    }

    /**
     * 3/27（金）の試合データ取得をテスト
     * 2026.html: 読売ジャイアンツ 3-1 阪神タイガース（相手3点、自軍1点）
     */
    public function testFetchGameResult20260327(): void
    {
        // フィクスチャを読み込む
        $html = file_get_contents(__DIR__ . '/fixture/2026.html');

        // Guzzle のモックを作成
        $mock = new MockHandler([
            new Response(200, [], $html),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new NpbGameResultService($client);
        $result = $service->fetchGameResult(2026, 3, '3/27', '阪神');

        $this->assertNotNull($result, "3/27の試合データが取得できるべき");
        $this->assertEquals('読売ジャイアンツ', $result->getOpponent(), "対戦相手は読売ジャイアンツ");
        $this->assertEquals(1, $result->getAllyScore(), "自軍のスコアは1");
        $this->assertEquals(3, $result->getOpponentScore(), "相手のスコアは3");
        $this->assertEquals('/scores/2026/0327/g-t-01/', $result->getScoreLink());
    }

    /**
     * 存在しない試合の取得テスト
     */
    public function testFetchGameResultNotFound(): void
    {
        // フィクスチャを読み込む
        $html = file_get_contents(__DIR__ . '/fixture/2026.html');

        // Guzzle のモックを作成
        $mock = new MockHandler([
            new Response(200, [], $html),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new NpbGameResultService($client);
        $result = $service->fetchGameResult(2026, 3, '4/1', '阪神');
        $this->assertNull($result, "存在しない日付の試合は null を返すべき");
    }

    /**
     * GameResult オブジェクトが正しく作成されるかテスト
     */
    public function testGameResultConstruction(): void
    {
        $result = new GameResult(
            '3/27',
            '阪神',
            '読売ジャイアンツ',
            '/scores/2026/0327/g-t-01/',
            1,
            3
        );

        $this->assertEquals('3/27', $result->getDate(), "日付が正しく保持される");
        $this->assertEquals('阪神', $result->getTeam(), "チーム名が正しく保持される");
        $this->assertEquals('読売ジャイアンツ', $result->getOpponent(), "対戦相手が正しく保持される");
        $this->assertEquals('/scores/2026/0327/g-t-01/', $result->getScoreLink(), "スコアリンクが正しく保持される");
        $this->assertEquals(1, $result->getAllyScore(), "自チームのスコアが正しく保持される");
        $this->assertEquals(3, $result->getOpponentScore(), "対戦相手のスコアが正しく保持される");
    }
}
