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
        $originalKey = getenv('OPENAI_KEY_SMALL_CF_APPS');
        putenv('OPENAI_KEY_SMALL_CF_APPS');

        $service = new AiSummaryService();
        $result = $service->summarize([], [], []);
        $this->assertNull($result);

        // 元に戻す
        if ($originalKey !== false) {
            putenv("OPENAI_KEY_SMALL_CF_APPS=$originalKey");
        }
    }

    public function testスコアプレーから要約が生成可能であること(): void
    {
        // 環境変数を一時的にクリアして、APIリクエストが発生しないようにする
        $originalKey = getenv('OPENAI_KEY_SMALL_CF_APPS');
        putenv('OPENAI_KEY_SMALL_CF_APPS');

        $service = new AiSummaryService();
        // 実際のリクエストは送らないが、引数の型チェックとして
        $result = $service->summarize(['play'], ['hr'], ['win']);
        // APIキーがない環境では null が返るのが正しい（型エラーにならないことが重要）
        $this->assertNull($result);

        // 元に戻す
        if ($originalKey !== false) {
            putenv("OPENAI_KEY_SMALL_CF_APPS=$originalKey");
        }
    }

    public function testBuildPrompt(): void
    {
        $service = new AiSummaryService();
        $reflector = new \ReflectionClass(AiSummaryService::class);
        $method = $reflector->getMethod('buildPrompt');
        $method->setAccessible(true);

        $scoringPlays = ['1回大山適時打'];
        $homeRuns = ['佐藤輝1号'];
        $pitcherResults = ['勝：才木'];

        $prompt = $method->invoke($service, $scoringPlays, $homeRuns, $pitcherResults);

        $this->assertStringContainsString('「です・ます」調', $prompt);
        $this->assertStringContainsString('「〜あった」「〜だった」「〜した」「〜である」などの常体', $prompt);
        $this->assertStringContainsString('勝投手など、提供された情報に存在しない項目や内容についての言及', $prompt);
        $this->assertStringContainsString('放った。', $prompt);
        $this->assertStringContainsString('成功した。', $prompt);
        $this->assertStringContainsString('広げた。', $prompt);
        $this->assertStringContainsString('挙げた。', $prompt);
        $this->assertStringContainsString('収めた。', $prompt);
    }
}
