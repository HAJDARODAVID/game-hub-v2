@use('App\Enums\RoomStatus')
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

        {{-- Always-visible exit: close / leave the room, or leave the game (forfeit) during a match --}}
        @if ($exit = $this->exit)
            <button type="button" class="btn btn-sm btn-outline-danger rounded-0 room-exit-btn"
                wire:click="{{ $exit['action'] }}"
                wire:confirm="{{ $exit['confirm'] }}"
                wire:loading.attr="disabled"
                title="{{ $exit['label'] }}" aria-label="{{ $exit['label'] }}">
                <i class="bi bi-door-open"></i>
            </button>
        @endif
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
    @if ($room->status === RoomStatus::Waiting && $this->isHost)
        {{-- Waiting room: the host starts (closing / leaving is the door icon in the header) --}}
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <button type="button" class="btn btn-gh-orange" wire:click="startGame" wire:loading.attr="disabled" wire:target="startGame" @disabled(! $this->canStart)>
                <i class="bi bi-play-fill me-1"></i>{{ __('Start game') }}
            </button>
            @unless ($this->canStart)
                <small class="text-white-50">
                    {{ $this->boardComponent ? __('Waiting for players: :count needed.', ['count' => $room->game->min_players]) : __("This game can't be played yet.") }}
                </small>
            @endunless
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
