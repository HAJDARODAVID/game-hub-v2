@php
    $items = [
        ['name' => 'Home', 'icon' => 'house', 'url' => route('home')],
        ['name' => 'My invites', 'icon' => 'envelope', 'url' => route('invites.index')],
    ];
@endphp

{{-- Phone: hamburger toggle --}}
<button class="btn btn-dark menu-toggle d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainMenu" aria-controls="mainMenu" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
    <i class="bi bi-list"></i>
</button>

<div class="collapse navbar-collapse" id="mainMenu">
    {{-- Desktop: dropdown under the gamertag --}}
    <ul class="navbar-nav ms-auto d-none d-md-flex align-items-center">
        <li class="nav-item me-1">
            <livewire:invites.badge link-class="nav-link text-white" />
        </li>
        <li class="nav-item dropdown">
            <a id="userDropdown" class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ Auth::user()->username }}
            </a>
            <div class="dropdown-menu dropdown-menu-end main-menu" aria-labelledby="userDropdown">
                @foreach ($items as $item)
                    <a class="dropdown-item" href="{{ $item['url'] }}"><i class="bi bi-{{ $item['icon'] }} me-2"></i>{{ $item['name'] }}</a>
                @endforeach
                <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class="bi bi-box-arrow-right me-2"></i>{{ __('Logout') }}
                </a>
            </div>
        </li>
    </ul>

    {{-- Phone: the same items as a flat list inside the collapsed menu --}}
    <div class="main-menu main-menu--phone d-md-none mt-2">
        @foreach ($items as $item)
            <a class="dropdown-item" href="{{ $item['url'] }}"><i class="bi bi-{{ $item['icon'] }} me-2"></i>{{ $item['name'] }}</a>
        @endforeach
        <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
            <i class="bi bi-box-arrow-right me-2"></i>{{ __('Logout') }}
        </a>
    </div>

    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
        @csrf
    </form>
</div>
