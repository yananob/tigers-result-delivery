<?php

namespace App;

use eftec\bladeone\BladeOne;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTP リクエストハンドラ
 *
 * 処理履歴閲覧画面などの HTTP リクエストを処理します。
 */
class HttpHandler
{
    private Logger $logger;
    private NotificationHistoryService $historyService;

    public function __construct(?NotificationHistoryService $historyService = null)
    {
        $this->logger = LoggerFactory::getLogger();
        $this->historyService = $historyService ?? new NotificationHistoryService();
    }

    /**
     * HTTP リクエストを処理し、PSR-7 レスポンスを返します。
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->logger->info('HTTP リクエストを処理開始', [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
        ]);

        try {
            $history = $this->historyService->getRecentHistory(7);

            $isTestOrDev = AppConfig::isDevelopment() || AppConfig::isTest();
            $host = $request->getUri()->getHost();
            if (empty($host)) {
                $host = $request->getHeaderLine('Host');
            }
            if (empty($host)) {
                $host = 'localhost';
            }

            $scheme = $request->getUri()->getScheme();
            if (empty($scheme)) {
                $scheme = 'http';
            }

            $basePath = "{$scheme}://{$host}";
            $requestPath = $request->getUri()->getPath() ?: '/';

            $views = __DIR__ . '/../views';
            $cache = sys_get_temp_dir() . '/blade_cache';
            if (!is_dir($cache)) {
                @mkdir($cache, 0777, true);
            }

            $blade = new BladeOne($views, $cache, BladeOne::MODE_AUTO);
            $html = $blade->run('history', [
                'history' => $history,
                'isTestOrDev' => $isTestOrDev,
                'basePath' => $basePath,
                'requestPath' => $requestPath,
                'env' => AppConfig::getEnvironment(),
            ]);

            return new Response(200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ], $html);
        } catch (\Exception $e) {
            $this->logger->error('HTTP リクエスト処理中にエラーが発生', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $errorHtml = '<h1>500 Internal Server Error</h1><p>処理中にエラーが発生しました。</p>';
            return new Response(500, [
                'Content-Type' => 'text/html; charset=utf-8',
            ], $errorHtml);
        }
    }
}
