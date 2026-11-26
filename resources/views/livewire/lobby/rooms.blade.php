{{-- Polls until real-time updates (Reverb) are added --}}
<div class="d-flex flex-column gap-3" wire:poll.10s>
    {{-- Header on one row: back · title + description (truncated if needed) · create room --}}
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('home') }}" class="btn btn-dark btn-sm flex-shrink-0" aria-label="{{ __('Back to catalogue') }}">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="d-flex align-items-baseline gap-2 flex-grow-1 min-w-0">
            {{-- <h1 class="h4 text-white mb-0 text-nowrap">{{ $game->title }}</h1>
            <small class="text-white-50 text-truncate" title="{{ $game->description }} · {{ $game->players_label }}">{{ $game->description }} · {{ $game->players_label }}</small> --}}
        </div>
        <button type="button" class="btn btn-gh-orange flex-shrink-0" wire:click="create" wire:loading.attr="disabled" wire:target="create"
            title="{{ __('Create room') }}" aria-label="{{ __('Create room') }}">
            <i class="bi bi-plus-lg"></i>
        </button>
    </div>

    @error('invite')
        <div class="alert alert-danger rounded-0 m-0">{{ $message }}</div>
    @enderror
    @error('room')
        <div class="alert alert-danger rounded-0 m-0">{{ $message }}</div>
    @enderror

    {{-- 1. Statistics --}}
    @php($stats = $this->stats)
    <div class="card catalogue">
        <div class="card-header">{{ mb_strtoupper(__('Your statistics')) }}</div>
        <div class="card-body">
            <div class="row row-cols-3 row-cols-md-5 g-2 text-center">
                <div class="col"><div class="stat-tile"><div class="stat-tile__value">{{ $stats->played }}</div><div class="stat-tile__label">{{ __('Played') }}</div></div></div>
                <div class="col"><div class="stat-tile"><div class="stat-tile__value stat-tile__value--win">{{ $stats->wins }}</div><div class="stat-tile__label">{{ __('Wins') }}</div></div></div>
                <div class="col"><div class="stat-tile"><div class="stat-tile__value">{{ $stats->losses }}</div><div class="stat-tile__label">{{ __('Losses') }}</div></div></div>
                <div class="col"><div class="stat-tile"><div class="stat-tile__value">{{ $stats->draws }}</div><div class="stat-tile__label">{{ __('Draws') }}</div></div></div>
                <div class="col"><div class="stat-tile"><div class="stat-tile__value">{{ $stats->played ? $stats->winRate() . '%' : '–' }}</div><div class="stat-tile__label">{{ __('Win rate') }}</div></div></div>
            </div>
        </div>
    </div>

    {{-- 2. Invites --}}
    <div class="card catalogue">
        <div class="card-header">{{ mb_strtoupper(__('Invites')) }}</div>
        <div class="card-body p-0">
            @forelse ($this->invites as $invite)
                <div class="room-row d-flex flex-wrap align-items-center gap-3" wire:key="invite-{{ $invite->id }}">
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-white text-truncate">{{ $invite->room->name }}</div>
                        <small class="text-white-50">
                            {{ __(':name invited you', ['name' => $invite->inviter->username]) }}
                            · <i class="bi bi-people"></i> {{ $invite->room->players_count }}/{{ $game->max_players }}
                            · {{ $invite->created_at->diffForHumans() }}
                        </small>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-gh-orange" wire:click="accept({{ $invite->id }})">{{ __('Accept') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-light rounded-0" wire:click="decline({{ $invite->id }})">{{ __('Decline') }}</button>
                    </div>
                </div>
            @empty
                <p class="text-white-50 m-3">{{ __('No pending invites.') }}</p>
            @endforelse
        </div>
    </div>

    {{-- 3. My open rooms --}}
    <div class="card catalogue">
        <div class="card-header">{{ mb_strtoupper(__('My rooms')) }}</div>
        <div class="card-body p-0">
            @forelse ($this->myRooms as $room)
                <div class="room-row d-flex align-items-center gap-3" wire:key="room-{{ $room->id }}">
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-white text-truncate">{{ $room->name }}</div>
                        <small class="text-white-50">
                            <i class="bi bi-person-fill"></i> {{ $room->host_id === auth()->id() ? __('you') : $room->host->username }}
                            · <i class="bi bi-people"></i> {{ $room->players_count }}/{{ $game->max_players }}
                            · {{ $room->updated_at->diffForHumans() }}
                        </small>
                    </div>
                    <span class="badge {{ $room->status->badge() }}">{{ $room->status->label() }}</span>
                    <a href="{{ route('rooms.show', $room) }}" class="btn btn-sm btn-outline-light rounded-0">{{ __('Open') }}</a>
                </div>
            @empty
                <p class="text-white-50 m-3">{{ __('No open rooms. Create one and invite your friends.') }}</p>
            @endforelse
        </div>
    </div>
</div>
