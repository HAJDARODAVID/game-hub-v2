{{-- Bottom bar, phones only. Buttons are GUI placeholders for now. --}}
<footer class="phone-footer fixed-bottom bg-black py-2 d-md-none">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="text-white">
            {{ Auth::user()->username }}
        </div>
        <div class="d-flex gap-1">
            <a href="#" class="btn btn-dark btn-sm" aria-label="{{ __('Messages') }}">
                <i class="bi bi-chat"></i>
            </a>
            <a href="#" class="btn btn-dark btn-sm" aria-label="{{ __('My invites') }}">
                <i class="bi bi-list-task"></i>
            </a>
        </div>
    </div>
</footer>
