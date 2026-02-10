<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * LINE 通知サービス
 * GameResult を LINE メッセージとして送信する
 */
class LineNotificationService
{
    private const LINE_PUSH_API_URL = 'https://api.line.biz/v2/bot/message/push';
    private string $botToken;
    private string $userId;
    private Client $httpClient;

    public function __construct()
    {
        $botName = AppConfig::getLineDeliverTarget();
        $lineConfig = json_decode(getenv("LINE_TOKENS_N_TARGETS"), true);
        $this->botToken = $lineConfig["tokens"][$botName];
        $this->userId = $lineConfig["target_ids"][$botName];
        // $line = new Line($lineConfig["tokens"], $lineConfig["target_ids"]);
        $this->httpClient = new Client();

        if (!$this->botToken) {
            throw new \RuntimeException('LINE_BOT_TOKEN 環境変数が設定されていません');
        }

        if (!$this->userId) {
            throw new \RuntimeException('LINE_USER_ID 環境変数が設定されていません');
        }
    }

    /**
     * ゲーム結果を LINE メッセージとして送信する
     *
     * @param GameResult $result ゲーム結果
     * @throws \RuntimeException LINE API への送信に失敗した場合
     * @throws GuzzleException HTTP リクエストエラー時
     */
    public function sendGameResult(GameResult $result): void
    {
        $message = $this->formatGameResultMessage($result);

        try {
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
                throw new \RuntimeException('LINE API への送信に失敗しました（ステータス: ' . $response->getStatusCode() . '）');
            }
        } catch (GuzzleException $e) {
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

        $message = <<<EOT
＜試合終了＞
阪神 {$allyScore} -  {$opponentScore} {$opponent}

https://npb.jp{$result->getScoreLink()}
EOT;

        return $message;
    }
}
