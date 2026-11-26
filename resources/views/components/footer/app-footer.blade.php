{{-- Bottom bar on every screen size. The messages button is a placeholder for now. --}}
<footer class="app-footer fixed-bottom bg-black py-2">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="text-white">
            {{ Auth::user()->username }}
        </div>
        <div class="d-flex gap-1">
            <livewire:matches.active-button />
            <a href="#" class="btn btn-dark btn-sm" aria-label="{{ __('Messages') }}">
                <i class="bi bi-chat"></i>
            </a>
            <livewire:invites.badge :hide-during-match="true" />
        </div>
    </div>
</footer>
