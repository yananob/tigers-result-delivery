<?php

namespace App;

/**
 * NPB ゲーム結果を表す Entity
 */
class GameResult
{
    public function __construct(
        private string $date,           // M/D 形式
        private string $team,           // 我がチーム名（阪神）
        private string $opponent,       // 対戦相手チーム名
        private ?string $scoreLink,     // スコアページへのリンク
        private ?int $allyScore,        // 我がチームのスコア
        private ?int $opponentScore,    // 対戦相手のスコア
        private bool $isFinished = false, // 試合が終了したか
        private ?string $summary = null, // AIによる要約
        private array $scoringPlays = [], // スコアプレー
        private array $homeRuns = []     // 本塁打
    ) {
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getTeam(): string
    {
        return $this->team;
    }

    public function getOpponent(): string
    {
        return $this->opponent;
    }

    public function getScoreLink(): ?string
    {
        return $this->scoreLink;
    }

    public function getAllyScore(): ?int
    {
        return $this->allyScore;
    }

    public function getOpponentScore(): ?int
    {
        return $this->opponentScore;
    }

    public function isFinished(): bool
    {
        return $this->isFinished;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): void
    {
        $this->summary = $summary;
    }

    public function getScoringPlays(): array
    {
        return $this->scoringPlays;
    }

    public function setScoringPlays(array $scoringPlays): void
    {
        $this->scoringPlays = $scoringPlays;
    }

    public function getHomeRuns(): array
    {
        return $this->homeRuns;
    }

    public function setHomeRuns(array $homeRuns): void
    {
        $this->homeRuns = $homeRuns;
    }
}
