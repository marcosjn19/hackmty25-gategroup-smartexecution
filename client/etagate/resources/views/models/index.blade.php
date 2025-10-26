@extends('layouts.app')

@section('content')
    <div x-data="{
        openDelete: false,
        targetName: '',
        targetAction: '',
        confirm(action, name) {
            this.targetAction = action;
            this.targetName = name;
            this.openDelete = true;
        }
    }" x-cloak>

        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-2xl font-bold text-etagate-blue">Models</h2>
            <a href="{{ route('models.create') }}"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-white font-semibold bg-gradient-to-r from-etagate-orange to-orange-600 hover:shadow-lg transform hover:scale-105 transition-all">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 4v16m8-8H4" />
                </svg>
                New Model
            </a>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-xl bg-green-50 text-green-800 px-4 py-3 border border-green-200">
                {{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 text-red-800 px-4 py-3 border border-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($models as $model)
                @php
                    $remoteRow = $remote[$model->uuid] ?? null;
                    // Como $remote viene de /available, si existe el uuid => está available
                    $available = isset($remote[$model->uuid]);
                @endphp

                @include('models.partials.card', [
                    'model' => $model,
                    'available' => $available,
                ])
            @empty
                <div class="col-span-full">
                    <div class="bg-white rounded-2xl shadow border border-gray-100 p-12 text-center text-gray-500">
                        No models yet. <a class="text-etagate-orange font-semibold"
                            href="{{ route('models.create') }}">Create one</a>.
                    </div>
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $models->links() }}
        </div>

        {{-- Delete Modal --}}
        @include('models.modals.delete')
    </div>
@endsection
