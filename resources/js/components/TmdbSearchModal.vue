<!-- resources/js/components/TmdbSearchModal.vue -->
<script setup>
import { ref, watch } from 'vue'
import api from '../api'

const props = defineProps({ open: Boolean })
const emit = defineEmits(['close', 'selected'])

const query = ref('')
const results = ref([])
const loading = ref(false)
const error = ref('')
const searchInput = ref(null)

let searchTimeout = null

function onSearchInput() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        if (query.value.length >= 2) {
            doSearch()
        } else {
            results.value = []
        }
    }, 500)
}

function clearSearch() {
    query.value = ''
    results.value = []
    error.value = ''
    if (searchInput.value) searchInput.value.focus()
}

async function doSearch() {
    loading.value = true
    error.value = ''
    results.value = []

    try {
        const type = props.open === 'tv_show' ? 'tv_show' : 'movie'
        const { data } = await api.post('/tmdb/search', { type, query: query.value })

        if (data.error) {
            error.value = data.error
            return
        }

        results.value = data.results || []
    } catch (err) {
        error.value = 'Search failed. Try again.'
    } finally {
        loading.value = false
    }
}

function selectItem(item) {
    emit('selected', item)
}

function handleClose() {
    query.value = ''
    results.value = []
    error.value = ''
    emit('close')
}

function onOverlayClick(e) {
    if (e.target === e.currentTarget) handleClose()
}

// Auto-focus search input when opened
watch(() => props.open, (val) => {
    if (val) {
        setTimeout(() => searchInput.value?.focus(), 100)
    }
})
</script>

<template>
    <transition name="tmdb-modal">
        <div v-if="open"
            class="fixed inset-0 z-[70] flex items-start justify-center pt-[5vh] sm:pt-[10vh] px-4 modal-backdrop"
            @click="onOverlayClick">
            <div
                class="bg-vault-800 border border-vault-600 rounded-2xl w-full max-w-lg max-h-[75vh] overflow-hidden shadow-2xl flex flex-col">

                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-vault-700 flex-shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-sky-500/15 flex items-center justify-center">
                            <svg class="w-4 h-4 text-sky-400" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M4 4a2 2 0 012-2V4m0 16a2 2 0 01-2 2H6a2 2 0 01-2-2V6m0-16a2 2 0 012-2H4m6 16h10a2 2 0 002 2v4a2 2 0 002 2H6a2 2 0 002-2V6a2 2 0 00-2-2H4" />
                            </svg>
                        </div>
                        <h2 class="text-white font-semibold">Search TMDB</h2>
                    </div>
                    <button @click="handleClose"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-vault-400 hover:text-white hover:bg-vault-700 transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Search input -->
                <div class="px-5 py-3 border-b border-vault-700 flex-shrink-0">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-vault-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="11" cy="11" r="8" />
                                <path d="M21 21l-4.35-4.35" />
                            </svg>
                        </div>
                        <input ref="searchInput" v-model="query" type="text" @input="onSearchInput"
                            class="w-full pl-10 pr-10 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-sky-500/50 text-sm"
                            placeholder="Search by movie or TV show title..." autocomplete="off" />
                        <button v-if="query" @click="clearSearch"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-vault-400 hover:text-white">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-vault-500 text-xs mt-1.5">Type at least 2 characters. Results come from The Movie
                        Database.</p>
                </div>

                <!-- Error -->
                <div v-if="error" class="mx-5 mt-3">
                    <div class="p-3 bg-rose-500/15 border border-rose-500/30 rounded-xl">
                        <p class="text-rose-400 text-sm">{{ error }}</p>
                    </div>
                </div>

                <!-- Loading -->
                <div v-else-if="loading" class="flex items-center justify-center py-16">
                    <div class="w-8 h-8 border-2 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
                    <span class="ml-3 text-vault-300 text-sm">Searching...</span>
                </div>

                <!-- No results -->
                <div v-else-if="query.length >= 2 && !results.length" class="text-center py-12 px-5">
                    <p class="text-vault-300 text-sm">No results found for "{{ query }}"</p>
                    <p class="text-vault-500 text-xs mt-1">Try a different title or check the spelling</p>
                </div>

                <!-- Waiting for input -->
                <div v-else class="text-center py-12 px-5">
                    <div class="w-14 h-14 rounded-2xl bg-vault-700 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-7 h-7 text-vault-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 4v16M17 4v16M3 12h18M7 8h10" />
                        </svg>
                    </div>
                    <p class="text-vault-400 text-sm">Search for a movie or TV show to auto-fill details</p>
                </div>

                <!-- Results list -->
                <div v-else class="flex-1 overflow-y-auto px-3 pb-3">
                    <p class="text-vault-400 text-xs font-medium px-2 pt-2 pb-2 border-b border-vault-700 mb-2">
                        {{ results.length }} result{{ results.length !== 1 ? 's' : '' }} found
                    </p>

                    <button v-for="item in results" :key="item.id" @click="selectItem(item)"
                        class="w-full flex items-center gap-3 p-2 rounded-xl hover:bg-vault-700/70 transition-colors text-left cursor-pointer group">
                        <!-- Poster thumbnail -->
                        <div
                            class="w-12 h-[72px] rounded-lg bg-vault-700 overflow-hidden flex-shrink-0 border border-vault-600">
                            <img v-if="item.poster_url" :src="item.poster_url" :alt="item.title"
                                class="w-full h-full object-cover" loading="lazy" />
                            <div v-else class="w-full h-full flex items-center justify-center text-vault-600 text-lg">
                                {{ type === 'tv_show' ? '📺' : '🎬' }}
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <p
                                class="text-white text-sm font-medium truncate group-hover:text-sky-400 transition-colors">
                                {{ item.title }}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span v-if="item.year" class="text-vault-500 text-xs">{{ item.year }}</span>
                                <span v-if="item.year && item.overview" class="text-vault-600">&middot;</span>
                                <span v-if="item.overview" class="text-vault-500 text-xs truncate">{{
                                    item.overview.substring(0, 60) }}...</span>
                            </div>
                        </div>

                        <!-- Arrow -->
                        <svg class="w-4 h-4 text-vault-600 group-hover:text-sky-400 transition-colors flex-shrink-0"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.tmdb-modal-enter-active,
.tmdb-modal-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
    transform-origin: top;
}

.tmdb-modal-enter-from,
.tmdb-modal-leave-to {
    opacity: 0;
    transform: scaleY(0.95) translateY(-8px);
}
</style>