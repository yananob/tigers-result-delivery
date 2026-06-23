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

    private function buildPrompt(array $scoringPlays, array $homeRuns, array $pitcherResults): string
    {
        $scoringPlaysStr = implode("\n", $scoringPlays);
        $homeRunsStr = implode("\n", $homeRuns);
        $pitcherResultsStr = implode("\n", $pitcherResults);

        return <<<EOT
以下のプロ野球の試合情報を元に、阪神タイガースファンのための試合要約を250文字程度で作成してください。

要約に含める内容：
- 試合の展開（誰がいつどのような活躍をしたか詳しく）
- 決勝打や目立った活躍をした選手名
- 投手陣の状況（誰が勝ち投手になったか、継投の様子など）
- 主なトピックや発生した記録（もしあれば）

作成のポイント：
- 阪神ファンの心に響くような、具体的で臨場感のある内容にしてください。
- 活躍した選手の名前をできるだけ多く含め、どのようなプレーだったか詳しく記述してください。
- **必ず提供された【試合情報】に含まれる選手名のみを使用してください。情報にない選手を推測で出したり、過去の記憶から補完したりしないでください。**
- 150文字ではなく、250文字程度の十分なボリュームで作成してください。

サンプル：
阪神は2点を追う5回裏、1死二三塁から大山の左前2点適時打で同点に追いつく。勢いに乗る打線は続く6回、近本がライト線を破る適時三塁打を放ち勝ち越しに成功。7回には佐藤輝がバックスクリーンへ特大のソロ本塁打を叩き込み、リードを広げた。投げては先発の才木が7回2失点の好投で今季5勝目。その後は岩崎、ゲラと繋ぐ完ぺきな継投で逃げ切った。敗れた巨人は、先発の戸郷が中盤に捕まり、打線も阪神の継投の前に沈黙した。

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
