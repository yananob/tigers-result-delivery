<?php

declare(strict_types=1);

namespace App;

/**
 * アプリケーション環境に基づいて設定値を提供します。
 * 環境は`APP_ENV`環境変数によって決定されます。
 *
 * サポートされる環境: 'production', 'test', 'development'。
 * `APP_ENV`が設定されていない場合、デフォルトは'development'です。
 */
class AppConfig
{
    /**
     * 現在のアプリケーション環境を取得します。
     *
     * @return string 現在の環境 ('production', 'test', または 'development')。
     */
    public static function getEnvironment(): string
    {
        return getenv('APP_ENV');
    }

    /**
     * Firestoreのルートコレクション名を取得します。
     *
     * @return string Firestoreコレクションの名前。
     */
    public static function getFirestoreRootCollection(): string
    {
        return match (self::getEnvironment()) {
            'production' => 'tigers-result-delivery',
            'test' => 'tigers-result-delivery-test',
            default => 'tigers-result-delivery-test',
        };
    }

    /**
     * LINEメッセージ配信のターゲットとなるユーザー/グループIDを取得します。
     *
     * @return string LINEターゲットID。
     */
    public static function getLineDeliverTarget(): string
    {
        return match (self::getEnvironment()) {
            'production' => 'bball',
            'test' => 'nobu',
            default => 'nobu',
        };
    }

    /**
     * 開発環境であるかどうかを返します。
     *
     * @return bool 開発環境である場合はtrue。
     */
    public static function isDevelopment(): bool
    {
        return self::getEnvironment() !== 'production';
    }

    /**
     * テスト環境であるかどうかを返します。
     *
     * @return bool テスト環境である場合はtrue。
     */
    public static function isTest(): bool
    {
        return self::getEnvironment() === 'test';
    }
}
