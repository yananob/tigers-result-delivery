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
        $result = $service->summarize('test', [], []);
        $this->assertNull($result);

        // 元に戻す
        if ($originalKey !== false) {
            putenv("OPENAI_KEY_SMALL_CF_APPS=$originalKey");
        }
    }

    public function testNullの戦評を受け入れ可能であること(): void
    {
        // 環境変数を一時的にクリアして、APIリクエストが発生しないようにする
        $originalKey = getenv('OPENAI_KEY_SMALL_CF_APPS');
        putenv('OPENAI_KEY_SMALL_CF_APPS');

        $service = new AiSummaryService();
        // 実際のリクエストは送らないが、引数の型チェックとして
        $result = $service->summarize(null, ['play'], ['hr']);
        // APIキーがない環境では null が返るのが正しい（型エラーにならないことが重要）
        $this->assertNull($result);

        // 元に戻す
        if ($originalKey !== false) {
            putenv("OPENAI_KEY_SMALL_CF_APPS=$originalKey");
        }
    }
}
