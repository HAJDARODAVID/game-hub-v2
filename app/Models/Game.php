<?php

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'cover',
        'min_players',
        'max_players',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_players' => 'integer',
            'max_players' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Route model binding uses the slug: /games/tic-tac-toe
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Games shown in the catalogue, in display order.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('title');
    }

    /**
     * Full URL of the cover image, or null when the game has none.
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn () => $this->cover ? asset($this->cover) : null);
    }

    /**
     * Human-readable player count, e.g. "2 players" or "2–4 players".
     */
    protected function playersLabel(): Attribute
    {
        return Attribute::get(fn () => $this->min_players === $this->max_players
            ? trans_choice(':count player|:count players', $this->max_players)
            : "{$this->min_players}–{$this->max_players} " . __('players'));
    }
}
