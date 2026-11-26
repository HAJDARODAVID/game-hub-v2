{{-- Updates arrive in real time (Reverb); slow polling is only a safety net --}}
<div class="card catalogue" wire:poll.60s>
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
