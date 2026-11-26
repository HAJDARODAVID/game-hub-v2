@extends('layouts.main-app')

@section('content')
<div class="container">
    {{-- Header (with "Create room"), statistics, invites and rooms are all in the Livewire component --}}
    <livewire:lobby.rooms :game="$game" />
</div>
@endsection
