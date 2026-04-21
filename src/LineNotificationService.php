<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;

/**
 * LINE 通知サービス
 * GameResult を LINE メッセージとして送信する
 */
class LineNotificationService
{
    private const LINE_PUSH_API_URL = 'https://api.line.me/v2/bot/message/push';
    private string $botToken;
    private string $userId;
    private Client $httpClient;
    private Logger $logger;

    public function __construct()
    {
        $this->logger = LoggerFactory::getLogger();

        $this->logger->info('LINE 通知サービスを初期化');

        $botName = AppConfig::getLineDeliverTarget();
        $this->logger->debug('LINE Bot 設定を読み込み', ['botName' => $botName]);

        $lineConfig = json_decode(getenv("LINE_TOKENS_N_TARGETS"), true);
        $this->botToken = $lineConfig["tokens"][$botName];
        $this->userId = $lineConfig["target_ids"][$botName];
        // $line = new Line($lineConfig["tokens"], $lineConfig["target_ids"]);
        $this->httpClient = new Client();

        if (!$this->botToken) {
            $this->logger->error('LINE Bot トークンが設定されていません');
            throw new \RuntimeException('LINE_BOT_TOKEN 環境変数が設定されていません');
        }

        if (!$this->userId) {
            $this->logger->error('LINE User ID が設定されていません');
            throw new \RuntimeException('LINE_USER_ID 環境変数が設定されていません');
        }

        $this->logger->debug('LINE 通知サービスの初期化完了');
    }

    /**
     * ゲーム結果を LINE メッセージとして送信する
     *
     * @param GameResult $result ゲーム結果
     * @return bool 送信成功時 true、失敗時 false
     * @throws \RuntimeException LINE API への送信に失敗した場合
     * @throws GuzzleException HTTP リクエストエラー時
     */
    public function sendGameResult(GameResult $result): bool
    {
        $this->logger->info('LINE 通知を送信開始', [
            'date' => $result->getDate(),
            'ally' => $result->getTeam(),
            'opponent' => $result->getOpponent(),
        ]);

        $message = $this->formatGameResultMessage($result);
        $this->logger->debug('送信メッセージを生成', ['message' => $message]);

        try {
            $this->logger->debug('LINE API へリクエスト送信', ['url' => self::LINE_PUSH_API_URL]);

            $response = $this->httpClient->post(self::LINE_PUSH_API_URL, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->botToken,
                ],
                'json' => [
                    'to' => $this->userId,
                    'messages' => [
                        [
                            'type' => 'text',
                            'text' => $message,
                        ],
                    ],
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('LINE API のレスポンスステータスが異常', [
                    'statusCode' => $response->getStatusCode(),
                ]);
                throw new \RuntimeException('LINE API への送信に失敗しました（ステータス: ' . $response->getStatusCode() . '）');
            }

            $this->logger->info('LINE 通知送信完了', ['statusCode' => $response->getStatusCode()]);
            return true;
        } catch (GuzzleException $e) {
            $this->logger->error('LINE API リクエスト中にエラーが発生', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw new \RuntimeException('LINE API への送信中にエラーが発生しました: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * ゲーム結果をメッセージ文字列にフォーマットする
     *
     * @param GameResult $result ゲーム結果
     * @return string フォーマットされたメッセージ
     */
    private function formatGameResultMessage(GameResult $result): string
    {
        $allyScore = $result->getAllyScore();
        $opponentScore = $result->getOpponentScore();
        $opponent = $result->getOpponent();
        $summary = $result->getSummary();

        $message = <<<EOT
＜試合終了＞
⚾🐅

阪神 {$allyScore} - {$opponentScore} {$opponent}
EOT;

        if ($summary) {
            $message .= "\n\n" . $summary;
        }

        $message .= "\n\nhttps://baseball.yahoo.co.jp{$result->getScoreLink()}";

        return $message;
    }
}
