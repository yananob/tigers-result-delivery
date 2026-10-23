<?php

namespace Tests;

use App\HttpHandler;
use App\NotificationHistoryService;
use Carbon\Carbon;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class HttpHandlerTest extends TestCase
{
    private $originalAppEnv;

    protected function setUp(): void
    {
        $this->originalAppEnv = getenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        if ($this->originalAppEnv !== false) {
            putenv("APP_ENV={$this->originalAppEnv}");
        } else {
            putenv('APP_ENV');
        }
    }

    public function testHandleReturnsHtmlResponse(): void
    {
        putenv('APP_ENV=test');

        $mockHistoryService = $this->createMock(NotificationHistoryService::class);
        $mockHistoryService->method('getRecentHistory')->willReturn([
            [
                'date' => '2026-10-23',
                'formatted_date' => '2026-10-23 (金)',
                'is_notified' => true,
                'status_label' => '通知済み',
                'timestamp' => '2026-10-23 18:00:00',
            ],
            [
                'date' => '2026-10-22',
                'formatted_date' => '2026-10-22 (木)',
                'is_notified' => false,
                'status_label' => '未通知',
                'timestamp' => '-',
            ],
        ]);

        $handler = new HttpHandler($mockHistoryService);
        $request = new ServerRequest('GET', 'http://example.com/history');

        $response = $handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $this->assertStringContainsString('阪神タイガース', $body);
        $this->assertStringContainsString('2026-10-23 (金)', $body);
        $this->assertStringContainsString('通知済み', $body);
        $this->assertStringContainsString('2026-10-22 (木)', $body);
        $this->assertStringContainsString('未通知', $body);

        // Verify overlay banner in test environment
        $this->assertStringContainsString('テスト/開発環境: TEST', $body);
        $this->assertStringContainsString('http://example.com', $body);
        $this->assertStringContainsString('/history', $body);
    }
}
