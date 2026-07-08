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
                    ['role' => 'system', 'content' => 'あなたはテレビのスポーツニュース番組のメインキャスターです。視聴者がワクワクするような熱気と臨場感あふれるハイライトレポートを作成してください。'],
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
以下のプロ野球の試合情報を元に、阪神タイガースファンのためのスポーツニュース風ハイライトレポートを250文字程度で作成してください。

要約に含める内容：
- 【タイガース速報】や【今日のハイライト】といった目を引く見出しを冒頭に付けてください。
- 試合の決定的な場面や、劇的なプレーを中心に構成してください。
- 決勝打や目立った活躍をした選手名。
- 投手陣の状況（勝利投手の好投や、完封、完璧な継投など）。
- 主なトピックや発生した記録（もしあれば）。

作成のポイント：
- スポーツニュースのキャスターが読み上げるような、テンポが良く熱気あふれる臨場感のある内容にしてください。
- 「劇的な一打」「魂の激走」「鉄壁の守護神」といった、感情に訴える力強い表現を積極的に使用してください。
- 活躍した選手の名前をできるだけ多く含め、どのようなプレーだったか詳しく記述してください。
- **必ず提供された【試合情報】に含まれる選手名のみを使用してください。情報にない選手を推測で出したり、過去の記憶から補完したりしないでください。**
- 150文字ではなく、250文字程度の十分なボリュームで作成してください。

サンプル：
【タイガース速報】甲子園が揺れた！阪神は2点を追う5回、主砲・大山が起死回生の2点タイムリーを放ち同点！勢いは止まらず、続く6回には近本がライト線を破る激走の適時三塁打でついに勝ち越し！さらに7回、佐藤輝が夜空に描いた特大のアーチはバックスクリーンへ！投げてはエース才木が魂の投球で7回2失点、今季5勝目をマーク。最後は岩崎、ゲラと繋ぐ鉄壁の継投で宿敵・巨人を封じ込めました！

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
