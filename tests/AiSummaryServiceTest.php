<?php

namespace Tests;

use App\AiSummaryService;
use PHPUnit\Framework\TestCase;

class AiSummaryServiceTest extends TestCase
{
    private AiSummaryService $service;

    protected function setUp(): void
    {
        $this->service = new AiSummaryService();
    }

    public function testAPIキーがない場合にnullを返すこと(): void
    {
        $this->assertInstanceOf(AiSummaryService::class, $this->service);

        // 環境変数を一時的にクリア
        $originalKey = getenv('OPENAI_KEY_LINE_AI_BOT');
        putenv('OPENAI_KEY_LINE_AI_BOT');

        $service = new AiSummaryService();
        $result = $service->summarize('test', [], []);
        $this->assertNull($result);

        // 元に戻す
        if ($originalKey !== false) {
            putenv("OPENAI_KEY_LINE_AI_BOT=$originalKey");
        }
    }
}
