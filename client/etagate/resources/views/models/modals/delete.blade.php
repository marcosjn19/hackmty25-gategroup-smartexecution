<div x-show="openDelete" class="fixed inset-0 z-[100] flex items-center justify-center p-4" x-transition
    aria-modal="true" role="dialog">
    <div class="absolute inset-0 bg-black/50" @click="openDelete = false"></div>

    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl border border-gray-100">
        <div class="px-6 pt-6">
            <h3 class="text-lg font-bold text-etagate-blue">Delete model</h3>
            <p class="mt-2 text-sm text-gray-600">
                Are you sure you want to delete <span class="font-semibold" x-text="targetName"></span>? This action
                cannot be undone.
            </p>
        </div>
        <div class="px-6 py-4 flex items-center justify-end gap-3">
            <button class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"
                @click="openDelete = false" type="button">
                Cancel
            </button>

            <form :action="targetAction" method="POST" x-ref="deleteForm">
                @csrf
                @method('DELETE')
                <button class="px-5 py-2.5 rounded-lg text-white font-semibold bg-red-600 hover:bg-red-700">
                    Delete
                </button>
            </form>
        </div>
    </div>
</div>
