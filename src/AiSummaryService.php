<?php

namespace App;

use OpenAI;
use Monolog\Logger;

/**
 * OpenAI API を使用して試合結果を要約するサービス
 */
class AiSummaryService
{
    private $client;
    private Logger $logger;

    public function __construct()
    {
        $this->logger = LoggerFactory::getLogger();
        $apiKey = getenv('OPENAI_KEY_SMALL_CF_APPS');
        if (!$apiKey) {
            $this->logger->warning('OPENAI_KEY_SMALL_CF_APPS が設定されていないため、AI 要約はスキップされます');
            return;
        }
        $this->logger->info('OpenAI クライアントを初期化しました', ['env' => 'OPENAI_KEY_SMALL_CF_APPS']);
        $this->client = OpenAI::client($apiKey);
    }

    /**
     * 試合情報を要約する
     *
     * @param string $review 戦評
     * @param array $scoringPlays スコアプレー
     * @param array $homeRuns 本塁打
     * @return string|null 要約結果
     */
    public function summarize(string $review, array $scoringPlays, array $homeRuns): ?string
    {
        if (!$this->client) {
            $this->logger->warning('OpenAI クライアントが初期化されていないため、要約をスキップします');
            return null;
        }

        $prompt = $this->buildPrompt($review, $scoringPlays, $homeRuns);
        $this->logger->info('OpenAI API リクエストを送信します', [
            'model' => 'gpt-4o-mini',
            'prompt_length' => mb_strlen($prompt)
        ]);
        $this->logger->debug('OpenAI へのプロンプト', ['prompt' => $prompt]);

        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'あなたはプロ野球のスポーツライターです。提供された試合情報から、簡潔で魅力的な要約を作成してください。'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ]);

            $summary = $response->choices[0]->message->content;
            $this->logger->info('AI 要約が完了しました');
            return trim($summary);
        } catch (\Exception $e) {
            $this->logger->error('OpenAI API でエラーが発生しました', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildPrompt(string $review, array $scoringPlays, array $homeRuns): string
    {
        $scoringPlaysStr = implode("\n", $scoringPlays);
        $homeRunsStr = implode("\n", $homeRuns);

        return <<<EOT
以下のプロ野球の試合情報を元に、阪神タイガースファンのための試合要約を150文字程度で作成してください。

要約に含める内容：
- 主な得点シーン
- 主なトピック
- 発生した記録（もしあれば）

サンプル：
阪神は2点を追う5回裏、大山の適時打などで同点とする。続く6回に、近本の適時打でリードを奪うと、7回には佐藤のソロが飛び出し、貴重な追加点を挙げた。投げては、4番手・湯浅が今季3勝目。敗れた中日は、先発・高橋宏が試合をつくれなかった。

【試合情報】
■戦評
{$review}

■スコアプレー
{$scoringPlaysStr}

■本塁打
{$homeRunsStr}
EOT;
    }
}
