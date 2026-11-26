<?php

namespace App\Livewire\Games\TicTacToe;

use App\Actions\Matches\MakeMove;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Board extends Component
{
    /** Real-time: re-render on every move in this room (Reverb). */
    protected function getListeners(): array
    {
        return ["echo-private:room.{$this->match->room_id},RoomUpdated" => 'refreshMatch'];
    }

    #[Locked]
    public GameMatch $match;

    /** Last status this browser saw, to notice when the match ends. */
    public string $seenStatus = '';

    public function mount(): void
    {
        $this->seenStatus = $this->match->status->value;
    }

    public function hydrate(): void
    {
        if (Gate::denies('view', $this->match->room)) {
            $this->redirectRoute('home');
        }
    }

    /** user id => gamertag for both players */
    #[Computed]
    public function names(): array
    {
        return User::whereIn('id', array_values($this->match->state['players']))->pluck('username', 'id')->all();
    }

    public function play(int $cell): void
    {
        $this->resetErrorBag();

        $this->match = app(MakeMove::class)->handle($this->match, Auth::user(), ['cell' => $cell]);

        $this->notifyIfFinished();
    }

    /** Called on every real-time room update (Reverb), and by the slow safety-net poll. */
    public function refreshMatch(): void
    {
        $this->notifyIfFinished();
    }

    /** Let the room page know (it then offers "Play again"). */
    private function notifyIfFinished(): void
    {
        if ($this->seenStatus !== $this->match->status->value) {
            $this->seenStatus = $this->match->status->value;
            $this->dispatch('match-finished');
        }
    }

    /** Extra variables for the view. */
    protected function viewData(): array
    {
        $state = $this->match->state;
        $me = Auth::id();
        $mySymbol = array_search($me, $state['players'], true) ?: null;
        $turnPlayer = $state['players'][$state['turn']] ?? null;

        return [
            'state' => $state,
            'mySymbol' => $mySymbol,
            'myTurn' => $this->match->isPlaying() && $turnPlayer === $me,
            'turnName' => $this->names[$turnPlayer] ?? '',
            'line' => $state['line'] ?? [],
        ];
    }

    public function render(): View
    {
        return view('livewire.games.tic-tac-toe.board', $this->viewData());
    }
}
