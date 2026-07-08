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
     * @param array $scoringPlays スコアプレー
     * @param array $homeRuns 本塁打
     * @param array $pitcherResults 責任投手
     * @return string|null 要約結果
     */
    public function summarize(array $scoringPlays, array $homeRuns, array $pitcherResults = []): ?string
    {
        if (!$this->client) {
            $this->logger->warning('OpenAI クライアントが初期化されていないため、要約をスキップします');
            return null;
        }

        $prompt = $this->buildPrompt($scoringPlays, $homeRuns, $pitcherResults);
        $this->logger->info('OpenAI API リクエストを送信します', [
            'model' => 'gpt-5.4-mini',
            'prompt_length' => mb_strlen($prompt)
        ]);
        $this->logger->debug('OpenAI へのプロンプト', ['prompt' => $prompt]);

        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-5.4-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'あなたはプロのスポーツニュースキャスターです。落ち着いたトーンで、正確かつ分かりやすく試合のハイライトを伝えてください。'],
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

    private function buildPrompt(array $scoringPlays, array $homeRuns, array $pitcherResults): string
    {
        $scoringPlaysStr = implode("\n", $scoringPlays);
        $homeRunsStr = implode("\n", $homeRuns);
        $pitcherResultsStr = implode("\n", $pitcherResults);

        return <<<EOT
以下のプロ野球の試合情報を元に、落ち着いたスポーツニュース風のハイライトレポートを250文字程度で作成してください。

要約に含める内容：
- 試合の重要な局面や、決定的なプレーを中心に構成してください。
- 決勝打や目立った活躍をした選手名。
- 投手陣の状況（勝利投手の成績や継投の様子など）。
- 主なトピックや発生した記録（もしあれば）。

作成のポイント：
- プロのニュースキャスターが冷静に淡々と説明するような、正確で分かりやすい文章にしてください。
- **「！」や「？」などの記号は一切使用せず、句読点のみで構成してください。**
- 見出しやタイトルは含めず、本文のみを作成してください。
- 活躍した選手の名前をできるだけ多く含め、どのようなプレーだったか記述してください。
- **必ず提供された【試合情報】に含まれる選手名のみを使用してください。情報にない選手を推測で出したり、過去の記憶から補完したりしないでください。**
- 150文字ではなく、250文字程度の十分なボリュームで作成してください。

サンプル：
阪神は2点を追う5回、大山がレフト前へ同点の2点適時打を放ちました。続く6回には近本がライト線への適時三塁打を放ち、勝ち越しに成功しています。さらに7回には佐藤輝がバックスクリーンへの本塁打を放ち、リードを広げました。投げては先発の才木が7回を2失点に抑える好投で今季5勝目を挙げています。終盤は岩崎、ゲラと繋ぐ継投で巨人の反撃を許さず、勝利を収めました。

【試合情報】
■スコアプレー
{$scoringPlaysStr}

■本塁打
{$homeRunsStr}

■責任投手
{$pitcherResultsStr}
EOT;
    }
}
