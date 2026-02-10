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
        private ?int $opponentScore     // 対戦相手のスコア
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
}
