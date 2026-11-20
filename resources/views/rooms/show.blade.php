@extends('layouts.main-app')

@section('content')
<div class="container">
    <livewire:rooms.show :room="$room" />
</div>
@endsection
