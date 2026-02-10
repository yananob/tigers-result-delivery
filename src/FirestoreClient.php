<?php

namespace App;

use Google\Cloud\Firestore\FirestoreClient as GoogleFirestoreClient;

/**
 * Firestore クライアント管理（シングルトン）
 * 
 * Google Cloud Firestore への接続とアクセスを管理します。
 * Application Default Credentials を使用した自動認証に対応しています。
 */
class FirestoreClient
{
    private static ?GoogleFirestoreClient $instance = null;
    private const PROJECT_ID = 'result-delivery-test';
    private const DATABASE_ID = '(default)';

    private function __construct()
    {
    }

    /**
     * Firestore クライアントのシングルトンインスタンスを取得
     * 
     * @return GoogleFirestoreClient Firestore クライアント
     */
    public static function getInstance(): GoogleFirestoreClient
    {
        if (self::$instance === null) {
            self::$instance = new GoogleFirestoreClient([
                'projectId' => self::PROJECT_ID,
            ]);
        }

        return self::$instance;
    }

    /**
     * プロジェクトID を取得
     * 
     * @return string プロジェクトID
     */
    public static function getProjectId(): string
    {
        return self::PROJECT_ID;
    }

    /**
     * データベースID を取得
     * 
     * @return string データベースID
     */
    public static function getDatabaseId(): string
    {
        return self::DATABASE_ID;
    }
}
