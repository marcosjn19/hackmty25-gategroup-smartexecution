@extends('layouts.app')

@section('content')
    <div class="max-w-3xl">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-etagate-blue">Create model</h2>
            <p class="text-sm text-gray-600 mt-1">This will register the model in the external API and store shared info
                locally.</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 text-red-800 px-4 py-3 border border-red-200">
                {{ $errors->first() }}
            </div>
        @endif
        @error('api')
            <div class="mb-4 rounded-xl bg-red-50 text-red-800 px-4 py-3 border border-red-200">
                {{ $message }}
            </div>
        @enderror

        <form method="POST" action="{{ route('models.store') }}"
            class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-etagate-blue mb-2">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full rounded-xl border-gray-300 focus:border-etagate-orange focus:ring-etagate-orange">
            </div>

            <div>
                <label class="block text-sm font-semibold text-etagate-blue mb-2">Description (optional)</label>
                <textarea name="description" rows="4"
                    class="w-full rounded-xl border-gray-300 focus:border-etagate-orange focus:ring-etagate-orange">{{ old('description') }}</textarea>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('models.index') }}"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                    class="px-6 py-2.5 rounded-full text-white font-semibold bg-gradient-to-r from-etagate-orange to-orange-600 hover:shadow-lg transform hover:scale-105 transition-all">
                    Create
                </button>
            </div>
        </form>
    </div>
@endsection
