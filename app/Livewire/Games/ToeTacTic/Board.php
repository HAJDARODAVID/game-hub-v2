<?php

namespace App\Livewire\Games\ToeTacTic;

use App\Livewire\Games\TicTacToe\Board as TicTacToeBoard;
use Illuminate\Contracts\View\View;

/**
 * Same players / turns / real-time plumbing as Tic Tac Toe; only the board and wording differ.
 */
class Board extends TicTacToeBoard
{
    protected function viewData(): array
    {
        $loser = $this->match->state['loser'] ?? null;

        return parent::viewData() + [
            'loserName' => $loser ? ($this->names[$this->match->state['players'][$loser]] ?? '') : null,
        ];
    }

    public function render(): View
    {
        return view('livewire.games.toe-tac-tic.board', $this->viewData());
    }
}
