<?php

use App\Actions\Rooms\RespondToInvite;
use App\Models\RoomInvite;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /** Pending invites to me, across all games. */
    #[Computed]
    public function invites()
    {
        return RoomInvite::pendingFor(Auth::user())
            ->with(['room.game', 'inviter'])
            ->latest()
            ->get();
    }

    public function accept(int $inviteId)
    {
        $this->resetErrorBag();

        $room = app(RespondToInvite::class)->accept(RoomInvite::findOrFail($inviteId), Auth::user());

        return $this->redirectRoute('rooms.show', $room);
    }

    public function decline(int $inviteId): void
    {
        $this->resetErrorBag();

        app(RespondToInvite::class)->decline(RoomInvite::findOrFail($inviteId), Auth::user());

        unset($this->invites);
        $this->dispatch('invites-changed');
    }
};
?>

{{-- Polls until real-time updates (Reverb) are added --}}
<div class="card catalogue" wire:poll.10s>
    <div class="card-header">{{ mb_strtoupper(__('My invites')) }}</div>

    <div class="card-body p-0">
        @error('invite')
            <div class="alert alert-danger rounded-0 m-0">{{ $message }}</div>
        @enderror

        @forelse ($this->invites as $invite)
            <div class="room-row d-flex flex-wrap align-items-center gap-3" wire:key="invite-{{ $invite->id }}">
                <div class="flex-grow-1 min-w-0">
                    <div class="text-white text-truncate">
                        {{ $invite->room->name }}
                        <span class="text-white-50">· {{ $invite->room->game->title }}</span>
                    </div>
                    <small class="text-white-50">
                        {{ __(':name invited you', ['name' => $invite->inviter->username]) }} · {{ $invite->created_at->diffForHumans() }}
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
