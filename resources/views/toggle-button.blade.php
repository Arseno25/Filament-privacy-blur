<div class="ms-2">
    <button
        x-data
        title="Toggle Privacy Blur"
        type="button"
        @click="$dispatch('toggle-privacy-blur')"
        class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 transition-colors"
    >
        <x-heroicon-o-eye class="w-5 h-5 text-gray-600 dark:text-gray-300" />
    </button>
</div>
