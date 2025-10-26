@extends('layouts.app')

@section('page_title', 'Processes')
@section('html_title', 'Etagate - Processes')

@section('content')
    <div x-data="processList()" x-init="init()" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-etagate-blue">Processes</h2>
                <p class="text-sm text-gray-600">Listado de procesos y sus modelos asociados.</p>
            </div>
            <a href="{{ route('processes.create') }}"
                class="px-5 py-2.5 bg-gradient-to-r from-etagate-orange to-orange-600 text-white font-semibold rounded-full hover:shadow-lg">
                New Process
            </a>
        </div>

        {{-- Tabla --}}
        @include('processes.partials.table', ['processes' => $processes])

        {{-- Paginación --}}
        <div>
            {{ $processes->links() }}
        </div>

        {{-- Modal de detalles --}}
        @include('processes.modals.details')
    </div>
@endsection
