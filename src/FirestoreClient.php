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
                "keyFile" => json_decode(getenv("FIREBASE_SERVICE_ACCOUNT"), true)
            ]);
        }

        return self::$instance;
    }
}
