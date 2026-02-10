<?php

namespace App;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

/**
 * ロガーファクトリー
 * Cloud Functions 環境用にロガーを生成します
 */
class LoggerFactory
{
    private static ?Logger $instance = null;

    /**
     * ロガーのシングルトンインスタンスを取得
     *
     * @return Logger ロガーインスタンス
     */
    public static function getLogger(): Logger
    {
        if (self::$instance === null) {
            self::$instance = self::createLogger();
        }
        return self::$instance;
    }

    /**
     * ロガーを作成
     * Cloud Loggingで適切に認識されるように JSON フォーマットで出力
     *
     * @return Logger 新規ロガーインスタンス
     */
    private static function createLogger(): Logger
    {
        $logger = new Logger('tigers-result-delivery');
        
        // 標準出力に JSON フォーマットで出力（Cloud Logging が自動認識）
        $handler = new StreamHandler('php://stderr', Logger::INFO);
        $handler->setFormatter(new JsonFormatter());
        
        $logger->pushHandler($handler);
        return $logger;
    }

    /**
     * テスト用のロガーをリセット
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
