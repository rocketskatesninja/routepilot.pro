@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Dashboard</h1>
            <p class="text-base-content/70 mt-2">Overview of your pool service business</p>
        </div>
    </div>

    <div class="bg-base-100 shadow-xl rounded-lg p-6">
        <div class="text-base-content">
            {{ __("You're logged in!") }}
        </div>
    </div>
</div>
@endsection
