<script setup>
import { computed } from 'vue'
import { useCoverFetch } from '../composables/useCoverFetch'

const props = defineProps({
    type: { type: String, required: true },
    open: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'done'])

const { fetching, progress, error, start, cancel } = useCoverFetch()

const typeLabels = {
    movie: 'Movies',
    book: 'Books',
    game: 'Games',
    music: 'Music',
    tv_show: 'TV Shows',
}

const pct = computed(() => {
    if (!progress.value.total) return 0
    return Math.round(((progress.value.total - progress.value.remaining) / progress.value.total) * 100)
})

const isDone = computed(() => fetching.value === false && progress.value.total > 0 && progress.value.remaining === 0)

async function handleStart() {
    await start(props.type)
    if (progress.value.remaining === 0) {
        emit('done')
    }
}

function handleClose() {
    if (fetching.value) cancel()
    emit('close')
}
</script>

<template>
    <transition name="modal">
        <div v-if="open" class="fixed inset-0 z-[60] flex items-center justify-center px-4 modal-backdrop"
            @click.self="handleClose">
            <div class="bg-vault-800 border border-vault-600 rounded-2xl w-full max-w-md shadow-2xl">
                <div class="px-6 py-4 border-b border-vault-700 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-white">Fetch Missing Covers</h2>
                        <p class="text-vault-400 text-xs mt-0.5">{{ typeLabels[type] }} — {{ progress.total }} items</p>
                    </div>
                    <button v-if="!fetching" @click="handleClose"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-vault-400 hover:text-white hover:bg-vault-700 transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6">
                    <!-- Not started -->
                    <div v-if="!fetching && !isDone && !error">
                        <p class="text-vault-300 text-sm mb-4">
                            Automatically download covers from TMDB, Google Books, and RAWG based on IMDb IDs, ISBNs,
                            and titles.
                        </p>
                        <button @click="handleStart"
                            class="w-full px-5 py-3 bg-gradient-to-r from-amber-500 to-amber-600 text-white font-semibold rounded-xl hover:from-amber-400 hover:to-amber-500 transition-all text-sm">
                            Start Fetching
                        </button>
                    </div>

                    <!-- Progress -->
                    <div v-if="fetching">
                        <div class="mb-4">
                            <div class="flex items-center justify-between text-sm mb-2">
                                <span class="text-vault-300">Fetching covers...</span>
                                <span class="text-amber-400 font-bold">{{ pct }}%</span>
                            </div>
                            <div class="w-full h-3 rounded-full bg-vault-700 overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r from-amber-500 to-amber-400 transition-all duration-500"
                                    :style="{ width: pct + '%' }"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3 mb-5">
                            <div class="bg-vault-700/50 rounded-lg p-3 text-center">
                                <p class="text-emerald-400 text-lg font-bold">{{ progress.fetched }}</p>
                                <p class="text-vault-500 text-xs">Fetched</p>
                            </div>
                            <div class="bg-vault-700/50 rounded-lg p-3 text-center">
                                <p class="text-rose-400 text-lg font-bold">{{ progress.failed }}</p>
                                <p class="text-vault-500 text-xs">Failed</p>
                            </div>
                            <div class="bg-vault-700/50 rounded-lg p-3 text-center">
                                <p class="text-vault-300 text-lg font-bold">{{ progress.remaining }}</p>
                                <p class="text-vault-500 text-xs">Remaining</p>
                            </div>
                        </div>

                        <button @click="cancel"
                            class="w-full px-5 py-2.5 bg-vault-700 text-vault-300 font-medium rounded-xl hover:bg-vault-600 hover:text-white transition-all text-sm">
                            Cancel
                        </button>
                    </div>

                    <!-- Done -->
                    <div v-if="isDone">
                        <div class="text-center py-4">
                            <svg class="w-12 h-12 text-emerald-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <p class="text-white font-semibold mb-1">All done!</p>
                            <p class="text-vault-400 text-sm">
                                {{ progress.fetched }} covers fetched
                                <span v-if="progress.failed"> · {{ progress.failed }} failed</span>
                            </p>
                        </div>
                        <button @click="handleClose"
                            class="w-full px-5 py-2.5 bg-vault-700 text-vault-200 font-medium rounded-xl hover:bg-vault-600 hover:text-white transition-all text-sm mt-2">
                            Close
                        </button>
                    </div>

                    <!-- Error -->
                    <div v-if="error">
                        <p class="text-rose-400 text-sm mb-4">{{ error }}</p>
                        <button @click="handleStart" :disabled="fetching"
                            class="w-full px-5 py-2.5 bg-vault-700 text-vault-200 font-medium rounded-xl hover:bg-vault-600 hover:text-white transition-all text-sm">
                            Retry
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.modal-backdrop {
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
}

.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>