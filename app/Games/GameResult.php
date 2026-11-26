<?php

namespace App\Games;

final readonly class GameResult
{
    private function __construct(
        public ?int $winnerId,
    ) {}

    public static function win(int $winnerId): self
    {
        return new self($winnerId);
    }

    public static function draw(): self
    {
        return new self(null);
    }

    public function isDraw(): bool
    {
        return $this->winnerId === null;
    }
}
