<?php

namespace Tests;

use App\GameResult;
use App\LineNotificationService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class LineNotificationServiceTest extends TestCase
{
    private $originalTokens;
    private $originalEnv;

    protected function setUp(): void
    {
        $this->originalTokens = getenv('LINE_TOKENS_N_TARGETS');
        $this->originalEnv = getenv('APP_ENV');
        putenv('APP_ENV=test');
    }

    protected function tearDown(): void
    {
        if ($this->originalTokens !== false) {
            putenv("LINE_TOKENS_N_TARGETS={$this->originalTokens}");
        } else {
            putenv('LINE_TOKENS_N_TARGETS');
        }

        if ($this->originalEnv !== false) {
            putenv("APP_ENV={$this->originalEnv}");
        } else {
            putenv('APP_ENV');
        }
    }

    public function testMissingConfigThrowsException(): void
    {
        putenv('LINE_TOKENS_N_TARGETS');
        $this->expectException(\RuntimeException::class);
        new LineNotificationService();
    }

    public function testSendGameResultSuccess(): void
    {
        $jsonConfig = json_encode([
            'tokens' => ['nobu' => 'dummy_token'],
            'target_ids' => ['nobu' => 'dummy_target_id'],
        ]);
        putenv("LINE_TOKENS_N_TARGETS={$jsonConfig}");

        $mock = new MockHandler([
            new Response(200, [], (string)json_encode(['message' => 'ok'])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new LineNotificationService($client);

        $gameResult = new GameResult(
            '3/31',
            '阪神',
            'DeNA',
            '/npb/game/2021038642/index',
            4,
            1,
            true,
            '阪神が投打に圧倒した。'
        );

        $result = $service->sendGameResult($gameResult);
        $this->assertTrue($result);
    }
}
