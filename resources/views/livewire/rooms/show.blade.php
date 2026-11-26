<?php

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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
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

    public function close(): void
    {
        $this->resetErrorBag();

        app(CloseRoom::class)->handle($this->room, Auth::user());

        $this->room->refresh();
        unset($this->pendingInvites);
    }
};
?>

{{-- Polls until the room is closed (players may wait for a rematch), until real-time updates (Reverb) are added --}}
<div @if ($room->status !== RoomStatus::Closed) wire:poll.5s @endif>
    <div class="d-flex align-items-center gap-3 mb-3">
        <a href="{{ route('games.lobby', $room->game) }}" class="btn btn-dark btn-sm" aria-label="{{ __('Back to lobby') }}">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="flex-grow-1 min-w-0">
            <h1 class="h4 text-white mb-0 text-truncate">{{ $room->name }}</h1>
            <small class="text-white-50">{{ $room->game->title }} · {{ __('Host') }}: {{ $room->host->username }}</small>
        </div>
        <span class="badge {{ $room->status->badge() }}">{{ $room->status->label() }}</span>
    </div>

    @error('room')
        <div class="alert alert-danger rounded-0">{{ $message }}</div>
    @enderror
    @error('invite')
        <div class="alert alert-danger rounded-0">{{ $message }}</div>
    @enderror

    {{-- Invitation banner for an invited (not yet seated) player --}}
    @if ($this->myInvite)
        <div class="invite-banner d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="flex-grow-1 text-white">
                <i class="bi bi-envelope-paper me-1"></i>
                {{ __(':name invited you to this room.', ['name' => $this->myInvite->inviter->username]) }}
            </span>
            <button type="button" class="btn btn-sm btn-gh-orange" wire:click="accept">{{ __('Accept') }}</button>
            <button type="button" class="btn btn-sm btn-outline-light rounded-0" wire:click="decline">{{ __('Decline') }}</button>
        </div>
    @endif

    {{-- Room controls (above the game board) --}}
    @if ($room->status === RoomStatus::Waiting && ! $this->myInvite)
        {{-- Waiting room: host starts, others can leave --}}
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            @if ($this->isHost)
                <button type="button" class="btn btn-gh-orange" wire:click="startGame" wire:loading.attr="disabled" wire:target="startGame" @disabled(! $this->canStart)>
                    <i class="bi bi-play-fill me-1"></i>{{ __('Start game') }}
                </button>
                @unless ($this->canStart)
                    <small class="text-white-50">
                        {{ $this->boardComponent ? __('Waiting for players: :count needed.', ['count' => $room->game->min_players]) : __("This game can't be played yet.") }}
                    </small>
                @endunless
                <button type="button" class="btn btn-outline-danger rounded-0 ms-auto" wire:click="close" wire:confirm="{{ __('Close this room? Pending invites will be cancelled.') }}">
                    {{ __('Close room') }}
                </button>
            @else
                <button type="button" class="btn btn-outline-light rounded-0 ms-auto" wire:click="leave" wire:confirm="{{ __('Leave this room?') }}">
                    {{ __('Leave room') }}
                </button>
            @endif
        </div>
    @elseif ($room->status === RoomStatus::Finished)
        {{-- After a match: every player decides; the rematch starts when all say yes --}}
        @php($rematch = $this->rematch)
        <div class="rematch-bar d-flex flex-wrap align-items-center gap-2 mb-3 @if ($rematch['askedBy'] && ! $rematch['iVoted']) rematch-bar--asked @endif">
            @if ($rematch['iVoted'])
                <span class="text-white-50">
                    <span class="spinner-grow spinner-grow-sm me-1" aria-hidden="true"></span>
                    {{ __('Waiting for :names to answer…', ['names' => implode(', ', $rematch['waitingFor'])]) }}
                </span>
            @elseif ($rematch['askedBy'])
                <span class="text-white">
                    <i class="bi bi-arrow-repeat me-1"></i>
                    {{ __(':names wants a rematch!', ['names' => implode(', ', $rematch['askedBy'])]) }}
                </span>
                <button type="button" class="btn btn-gh-orange" wire:click="playAgain" wire:loading.attr="disabled" wire:target="playAgain">
                    {{ __('Yes!') }}
                </button>
            @else
                <button type="button" class="btn btn-gh-orange" wire:click="playAgain" wire:loading.attr="disabled" wire:target="playAgain">
                    <i class="bi bi-arrow-repeat me-1"></i>{{ __('Play again?') }}
                </button>
            @endif

            <button type="button" class="btn btn-outline-danger rounded-0 ms-auto" wire:click="close" wire:confirm="{{ __('Close this room?') }}">
                {{ __('Close room') }}
            </button>
        </div>
    @elseif ($room->status === RoomStatus::Closed && $this->match)
        <p class="text-white-50 mb-3"><i class="bi bi-door-closed me-1"></i>{{ __('This room is closed.') }}</p>
    @endif

    {{-- Game board for the current / last match --}}
    @if ($this->match && $this->boardComponent)
        <livewire:is :component="$this->boardComponent" :match="$this->match" :key="'match-' . $this->match->id" />
    @endif

    {{-- Waiting room only: once a match exists, the board shows the players and invites are closed --}}
    @unless ($this->match)
    <div class="row g-3">
        {{-- Players --}}
        <div class="col-md-6">
            <div class="card catalogue h-100">
                <div class="card-header d-flex justify-content-between">
                    <span>{{ mb_strtoupper(__('Players')) }}</span>
                    <span class="text-white-50">{{ $this->players->count() }}/{{ $room->game->max_players }}</span>
                </div>
                <ul class="list-unstyled mb-0">
                    @foreach ($this->players as $player)
                        <li class="room-row d-flex align-items-center gap-2" wire:key="player-{{ $player->id }}">
                            <span class="seat">{{ $player->seat }}</span>
                            <span class="text-white flex-grow-1">{{ $player->user->username }}</span>
                            @if ($player->user_id === $room->host_id)
                                <span class="badge text-bg-warning">{{ __('Host') }}</span>
                            @endif
                            @if ($player->user_id === auth()->id())
                                <span class="badge text-bg-secondary">{{ __('You') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Invites (host only) --}}
        @if ($this->isHost)
            <div class="col-md-6">
                <div class="card catalogue h-100">
                    <div class="card-header">{{ mb_strtoupper(__('Invites')) }}</div>
                    <div class="card-body">
                        @if ($room->status === RoomStatus::Waiting)
                            @if ($this->seatsLeft > 0)
                                <form wire:submit="invite" class="mb-3">
                                    <div class="input-group has-validation">
                                        <span class="input-group-text"><i class="bi bi-person-plus"></i></span>
                                        <input type="text" class="form-control rounded-0 @error('username') is-invalid @enderror" wire:model="username" placeholder="{{ __('Gamertag') }}" aria-label="{{ __('Gamertag') }}" autocomplete="off" required>
                                        <button type="submit" class="btn btn-gh-orange" wire:loading.attr="disabled" wire:target="invite">{{ __('Invite') }}</button>
                                        @error('username')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </form>
                            @else
                                <p class="text-white-50 small">{{ __('All seats are taken or invited.') }}</p>
                            @endif
                        @endif

                        @forelse ($this->pendingInvites as $invite)
                            <div class="d-flex align-items-center gap-2 py-1" wire:key="invite-{{ $invite->id }}">
                                <i class="bi bi-hourglass-split text-white-50"></i>
                                <span class="text-white flex-grow-1">{{ $invite->invitee->username }}</span>
                                <button type="button" class="btn btn-sm btn-outline-light rounded-0" wire:click="cancelInvite({{ $invite->id }})" aria-label="{{ __('Cancel invite') }}">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        @empty
                            <p class="text-white-50 small mb-0">{{ __('No pending invites.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>
    @endunless
</div>
