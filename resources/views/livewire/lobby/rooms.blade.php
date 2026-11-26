<?php

use App\Actions\Rooms\CreateRoom;
use App\Actions\Rooms\RespondToInvite;
use App\Models\Game;
use App\Models\Room;
use App\Models\RoomInvite;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public Game $game;

    /**
     * Rooms of this game the user may see (hosted, played in, or invited to).
     */
    #[Computed]
    public function rooms()
    {
        return Room::visibleTo(Auth::user())
            ->where('game_id', $this->game->id)
            ->with([
                'host',
                'invites' => fn ($q) => $q->pending()->where('invitee_id', Auth::id())->with('inviter'),
            ])
            ->withCount('players')
            ->openFirst()
            ->limit(30)
            ->get();
    }

    public function create()
    {
        $this->resetErrorBag();

        $room = app(CreateRoom::class)->handle(Auth::user(), $this->game);

        return $this->redirectRoute('rooms.show', $room);
    }

    public function accept(int $inviteId)
    {
        $this->resetErrorBag();

        $invite = RoomInvite::findOrFail($inviteId);
        $room = app(RespondToInvite::class)->accept($invite, Auth::user());

        return $this->redirectRoute('rooms.show', $room);
    }

    public function decline(int $inviteId): void
    {
        $this->resetErrorBag();

        app(RespondToInvite::class)->decline(RoomInvite::findOrFail($inviteId), Auth::user());
        unset($this->rooms);
        $this->dispatch('invites-changed');
    }
};
?>

{{-- Polls until real-time updates (Reverb) are added --}}
<div class="card catalogue" wire:poll.10s>
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>{{ mb_strtoupper(__('Rooms')) }}</span>
        <button type="button" class="btn btn-sm btn-gh-orange" wire:click="create" wire:loading.attr="disabled" wire:target="create">
            <i class="bi bi-plus-lg me-1"></i>{{ __('Create room') }}
        </button>
    </div>

    <div class="card-body p-0">
        @error('invite')
            <div class="alert alert-danger rounded-0 m-0">{{ $message }}</div>
        @enderror
        @error('room')
            <div class="alert alert-danger rounded-0 m-0">{{ $message }}</div>
        @enderror

        @forelse ($this->rooms as $room)
            @php($invite = $room->invites->first())
            <div class="room-row d-flex align-items-center gap-3" wire:key="room-{{ $room->id }}">
                <div class="flex-grow-1 min-w-0">
                    <div class="text-white text-truncate">{{ $room->name }}</div>
                    <small class="text-white-50">
                        <i class="bi bi-person-fill"></i> {{ $room->host->username }}
                        · <i class="bi bi-people"></i> {{ $room->players_count }}/{{ $game->max_players }}
                        · {{ $room->created_at->diffForHumans() }}
                    </small>
                </div>

                <span class="badge {{ $room->status->badge() }}">{{ $room->status->label() }}</span>

                @if ($invite)
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-gh-orange" wire:click="accept({{ $invite->id }})" title="{{ __('Invited by :name', ['name' => $invite->inviter->username]) }}">
                            {{ __('Accept') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-light rounded-0" wire:click="decline({{ $invite->id }})">
                            {{ __('Decline') }}
                        </button>
                    </div>
                @else
                    <a href="{{ route('rooms.show', $room) }}" class="btn btn-sm btn-outline-light rounded-0">
                        {{ $room->status->isOpen() ? __('Open') : __('View') }}
                    </a>
                @endif
            </div>
        @empty
            <p class="text-white-50 m-3">{{ __('No rooms yet. Create one and invite your friends.') }}</p>
        @endforelse
    </div>
</div>
