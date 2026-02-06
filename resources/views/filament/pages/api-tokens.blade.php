<x-filament-panels::page>
    @if ($plainTextToken)
        <div class="rounded-xl border border-green-600/30 bg-green-50 p-4 dark:bg-green-950/20">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-green-800 dark:text-green-200">
                        Token generated — copy it now, it won't be shown again.
                    </p>
                    <div class="mt-2 flex items-center gap-2">
                        <code
                            x-data
                            x-ref="token"
                            class="block min-w-0 flex-1 select-all truncate rounded-lg bg-white/80 px-3 py-2 font-mono text-sm text-gray-900 dark:bg-gray-900/50 dark:text-gray-100"
                        >{{ $plainTextToken }}</code>
                        <button
                            x-data="{ copied: false }"
                            x-on:click="
                                navigator.clipboard.writeText('{{ $plainTextToken }}');
                                copied = true;
                                setTimeout(() => copied = false, 2000);
                            "
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <template x-if="!copied">
                                <span>Copy</span>
                            </template>
                            <template x-if="copied">
                                <span class="text-green-600 dark:text-green-400">Copied!</span>
                            </template>
                        </button>
                    </div>
                </div>
                <button
                    wire:click="dismissToken"
                    type="button"
                    class="shrink-0 rounded-lg p-1 text-green-600 transition hover:bg-green-100 dark:text-green-400 dark:hover:bg-green-900/30"
                >
                    <x-heroicon-m-x-mark class="h-5 w-5" />
                </button>
            </div>
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
