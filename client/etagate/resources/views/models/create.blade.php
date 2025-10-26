@extends('layouts.app')

@section('content')
    <div class="max-w-4xl mx-auto" x-data="{
            name: @js(old('name', '')),
            description: @js(old('description', '')),
            submitting: false,
            get nameLen() { return this.name?.length || 0 },
            get descLen() { return this.description?.length || 0 },
        }">

        {{-- Hero --}}
        <div class="bg-gradient-to-br from-etagate-blue to-[#1a2835] rounded-3xl p-8 md:p-10 text-white shadow-2xl mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-white/15 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="1.8" d="M3 7a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                            <path stroke-width="1.8" d="M3 9l7.7 4.7a3 3 0 003 0L21 9" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-3xl md:text-4xl font-bold">Create model</h2>
                        <p class="text-white/80 mt-2">
                            Register a new model in the external API and keep its shared info in your workspace.
                        </p>
                    </div>
                </div>

                <div class="bg-white/10 rounded-2xl p-4 md:p-5 w-full md:w-80">
                    <p class="text-sm font-semibold mb-2">Tips</p>
                    <ul class="space-y-2 text-sm text-white/80">
                        <li class="flex items-start gap-2">
                            <span class="mt-1 w-1.5 h-1.5 rounded-full bg-etagate-orange"></span>
                            Pick a unique, descriptive name.
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1 w-1.5 h-1.5 rounded-full bg-etagate-orange"></span>
                            You can upload samples and train it after creation.
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1 w-1.5 h-1.5 rounded-full bg-etagate-orange"></span>
                            Description is optional but helps collaborators.
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Alerts --}}
        @if ($errors->any())
            <div class="mb-6 rounded-xl bg-red-50 text-red-800 px-4 py-3 border border-red-200">
                {{ $errors->first() }}
            </div>
        @endif
        @error('api')
            <div class="mb-6 rounded-xl bg-red-50 text-red-800 px-4 py-3 border border-red-200">
                {{ $message }}
            </div>
        @enderror

        {{-- Form --}}
        <form method="POST" action="{{ route('models.store') }}"
            class="bg-white rounded-3xl shadow-xl border border-gray-100 p-6 md:p-8" @submit="submitting = true">
            @csrf

            {{-- Name --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-etagate-blue mb-2">Name</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="1.6"
                                d="M12 12c2.8 0 5-2.2 5-5S14.8 2 12 2 7 4.2 7 7s2.2 5 5 5zm0 2c-4 0-7 2-7 4.5V21h14v-2.5c0-2.3-3-4.5-7-4.5z" />
                        </svg>
                    </span>
                    <input type="text" name="name" x-model="name" required maxlength="255"
                        class="w-full pl-11 rounded-xl border-gray-300 focus:border-etagate-orange focus:ring-etagate-orange"
                        placeholder="e.g., Bottle detector" value="{{ old('name') }}">
                </div>
                <div class="mt-1 text-xs text-gray-500" x-text="nameLen + '/255'"></div>
            </div>

            {{-- Description --}}
            <div class="mb-8">
                <label class="block text-sm font-semibold text-etagate-blue mb-2">Description (optional)</label>
                <div class="relative">
                    <span class="absolute left-3 top-3 pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="1.6" d="M4 6h16M4 12h16M4 18h10" />
                        </svg>
                    </span>
                    <textarea name="description" rows="4" x-model="description" maxlength="500"
                        class="w-full pl-11 rounded-xl border-gray-300 focus:border-etagate-orange focus:ring-etagate-orange"
                        placeholder="What does this model detect or classify?">{{ old('description') }}</textarea>
                </div>
                <div class="mt-1 text-xs text-gray-500" x-text="descLen + '/500'"></div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between flex-wrap gap-3">
                <a href="{{ route('models.index') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-width="1.8" d="M15 18l-6-6 6-6" />
                    </svg>
                    Cancel
                </a>

                <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full text-white font-semibold bg-gradient-to-r from-etagate-orange to-orange-600 hover:shadow-lg transform hover:scale-105 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                    :disabled="submitting">
                    <svg x-show="submitting" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle>
                        <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke-width="4" stroke-linecap="round"></path>
                    </svg>
                    <span x-text="submitting ? 'Creating…' : 'Create'"></span>
                </button>
            </div>
        </form>

        {{-- Small reassurance note --}}
        <p class="text-xs text-gray-500 mt-4">
            After creation you’ll be able to upload samples and start training from the Models page.
        </p>
    </div>
@endsection
