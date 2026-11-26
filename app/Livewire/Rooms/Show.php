<?php

namespace App\Livewire\Rooms;

use App\Actions\Matches\RequestRematch;
use App\Actions\Matches\StartMatch;
use App\Actions\Rooms\CancelInvite;
use App\Actions\Rooms\CloseRoom;
use App\Actions\Rooms\InvitePlayer;
use App\Actions\Rooms\LeaveRoom;
use App\Actions\Rooms\RespondToInvite;
use App\Enums\RoomStatus;
use App\Games\GameRegistry;
use App\Models\Room;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Room $room;

    public string $username = '';

    /**
     * Re-check access on every follow-up request (polling, button clicks),
     * e.g. after the player left or their invite was cancelled.
     */
    public function hydrate(): void
    {
        if (Gate::denies('view', $this->room)) {
            $this->redirectRoute('games.lobby', $this->room->game);
        }
    }

    #[Computed]
    public function players()
    {
        return $this->room->players()->with('user')->orderBy('seat')->get();
    }

    #[Computed]
    public function pendingInvites()
    {
        return $this->room->invites()->pending()->with('invitee')->oldest()->get();
    }

    #[Computed]
    public function myInvite()
    {
        return $this->room->pendingInviteFor(Auth::user());
    }

    #[Computed]
    public function isHost(): bool
    {
        return $this->room->isHost(Auth::user());
    }

    #[Computed]
    public function seatsLeft(): int
    {
        return max(0, $this->room->game->max_players - $this->players->count() - $this->pendingInvites->count());
    }

    /** The current or last match, shown as the game board. */
    #[Computed]
    public function match()
    {
        return $this->room->latestMatch()->first();
    }

    /** Livewire component that draws this game's board, or null if the game has no engine yet. */
    #[Computed]
    public function boardComponent(): ?string
    {
        $games = app(GameRegistry::class);

        return $games->has($this->room->game) ? $games->for($this->room->game)->boardComponent() : null;
    }

    /** Host can start the first match of a waiting room. */
    #[Computed]
    public function canStart(): bool
    {
        return $this->isHost
            && $this->room->status === RoomStatus::Waiting
            && $this->players->count() >= $this->room->game->min_players
            && $this->boardComponent !== null;
    }

    /**
     * After a match: who asked to play again, and who we're still waiting for.
     *
     * @return array{iVoted: bool, askedBy: list<string>, waitingFor: list<string>}
     */
    #[Computed]
    public function rematch(): array
    {
        $votes = $this->match?->rematchVotes() ?? [];
        $me = Auth::id();
        $others = $this->players->reject(fn ($p) => $p->user_id === $me);

        return [
            'iVoted' => in_array($me, $votes, true),
            'askedBy' => $others->filter(fn ($p) => in_array($p->user_id, $votes, true))->map(fn ($p) => $p->user->username)->values()->all(),
            'waitingFor' => $others->reject(fn ($p) => in_array($p->user_id, $votes, true))->map(fn ($p) => $p->user->username)->values()->all(),
        ];
    }

    public function startGame(): void
    {
        $this->resetErrorBag();

        app(StartMatch::class)->handle($this->room, Auth::user());

        $this->room->refresh();
        unset($this->match, $this->pendingInvites, $this->canStart);
    }

    /** "Play again?" / "Yes!": the rematch starts once every player has said yes. */
    public function playAgain(): void
    {
        $this->resetErrorBag();

        app(RequestRematch::class)->handle($this->room, Auth::user());

        $this->room->refresh();
        unset($this->match, $this->rematch);
    }

    /** Dispatched by the board when a match ends. */
    #[On('match-finished')]
    public function matchFinished(): void
    {
        $this->room->refresh();
        unset($this->match, $this->canStart, $this->rematch);
    }

    public function invite(): void
    {
        $this->resetErrorBag();

        app(InvitePlayer::class)->handle($this->room, Auth::user(), $this->username);

        $this->reset('username');
        unset($this->pendingInvites, $this->seatsLeft);
    }

    public function cancelInvite(int $inviteId): void
    {
        $this->resetErrorBag();

        $invite = $this->room->invites()->findOrFail($inviteId);
        app(CancelInvite::class)->handle($invite, Auth::user());

        unset($this->pendingInvites, $this->seatsLeft);
    }

    public function accept(): void
    {
        $this->resetErrorBag();

        app(RespondToInvite::class)->accept($this->myInvite, Auth::user());

        unset($this->players, $this->myInvite);
    }

    public function decline()
    {
        $this->resetErrorBag();

        app(RespondToInvite::class)->decline($this->myInvite, Auth::user());

        return $this->redirectRoute('games.lobby', $this->room->game);
    }

    public function leave()
    {
        $this->resetErrorBag();

        app(LeaveRoom::class)->handle($this->room, Auth::user());

        return $this->redirectRoute('games.lobby', $this->room->game);
    }

    /** Close the room; during a match this means leaving the game (the opponent wins). */
    public function close()
    {
        $this->resetErrorBag();

        app(CloseRoom::class)->handle($this->room, Auth::user());

        return $this->redirectRoute('games.lobby', $this->room->game);
    }

    /**
     * What the always-visible door icon in the header does right now, or null when there's nothing to exit.
     *
     * @return array{action: 'close'|'leave', label: string, confirm: string}|null
     */
    #[Computed]
    public function exit(): ?array
    {
        if ($this->myInvite || ! $this->players->contains('user_id', Auth::id())) {
            return null;   // invited or not seated: nothing to leave
        }

        $opponents = $this->players->reject(fn ($p) => $p->user_id === Auth::id())->map(fn ($p) => $p->user->username)->implode(', ');

        return match ($this->room->status) {
            RoomStatus::Closed => null,
            RoomStatus::Waiting => $this->isHost
                ? ['action' => 'close', 'label' => __('Close room'), 'confirm' => __('Close this room? Pending invites will be cancelled.')]
                : ['action' => 'leave', 'label' => __('Leave room'), 'confirm' => __('Leave this room?')],
            RoomStatus::Playing => [
                'action' => 'close',
                'label' => __('Leave game'),
                'confirm' => $opponents !== ''
                    ? __('Leave the game? :names wins and the room closes.', ['names' => $opponents])
                    : __('Leave the game? The room closes.'),
            ],
            RoomStatus::Finished => ['action' => 'close', 'label' => __('Close room'), 'confirm' => __('Close this room?')],
        };
    }

    public function render(): View
    {
        return view('livewire.rooms.show');
    }
}
