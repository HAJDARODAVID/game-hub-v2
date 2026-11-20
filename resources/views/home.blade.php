@extends('layouts.main-app')

@section('content')
<div class="container">
    <x-games-catalogue.catalogue :games="$games" />
</div>
@endsection
