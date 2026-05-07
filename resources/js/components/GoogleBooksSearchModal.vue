<script setup>
import { ref, watch } from 'vue'
import api from '../api'

const props = defineProps({ open: Boolean })
const emit = defineEmits(['close', 'selected'])

const query = ref('')
const results = ref([])
const loading = ref(false)
const fetchingDetails = ref(false)
const error = ref('')
const searchInput = ref(null)

let searchTimeout = null

function onSearchInput() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        if (query.value.length >= 2) doSearch()
        else results.value = []
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
        const { data } = await api.post('/google-books/search', { query: query.value })
        if (data.error) { error.value = data.error; return }
        results.value = data.results || []
    } catch (err) { error.value = 'Search failed.' }
    finally { loading.value = false }
}

async function selectItem(item) {
    fetchingDetails.value = true
    try {
        const { data } = await api.post('/google-books/details', { google_id: item.id })
        if (data.error) { error.value = data.error; fetchingDetails.value = false; return }
        data.poster_url = item.poster_url
        emit('selected', data)
    } catch (err) { error.value = 'Failed to load details.' }
    finally { fetchingDetails.value = false }
}

function handleClose() {
    query.value = ''; results.value = []; error.value = ''
    emit('close')
}

function onOverlayClick(e) { if (e.target === e.currentTarget) handleClose() }

watch(() => props.open, (val) => { if (val) setTimeout(() => searchInput.value?.focus(), 100) })
</script>

<template>
    <transition name="tmdb-modal">
        <div v-if="open"
            class="fixed inset-0 z-[70] flex items-start justify-center pt-[5vh] sm:pt-[10vh] px-4 modal-backdrop"
            @click="onOverlayClick">
            <div
                class="bg-vault-800 border border-vault-600 rounded-2xl w-full max-w-lg max-h-[75vh] overflow-hidden shadow-2xl flex flex-col">
                <div class="flex items-center justify-between px-5 py-4 border-b border-vault-700 flex-shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/15 flex items-center justify-center">📖</div>
                        <h2 class="text-white font-semibold">Search Google Books</h2>
                    </div>
                    <button @click="handleClose"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-vault-400 hover:text-white hover:bg-vault-700 transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

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
                            class="w-full pl-10 pr-10 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 text-sm"
                            placeholder="Search by book title..." autocomplete="off" />
                        <button v-if="query" @click="clearSearch"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-vault-400 hover:text-white">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-vault-500 text-xs mt-1.5">Powered by Google Books API</p>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <div v-if="error" class="mb-4 p-3 bg-rose-500/15 border border-rose-500/30 rounded-xl">
                        <p class="text-rose-400 text-sm">{{ error }}</p>
                    </div>
                    <div v-else-if="loading" class="flex items-center justify-center py-16">
                        <div class="w-8 h-8 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin">
                        </div><span class="ml-3 text-vault-300 text-sm">Searching...</span>
                    </div>
                    <div v-else-if="query.length >= 2 && !results.length" class="text-center py-12">
                        <p class="text-vault-300 text-sm">No results found for "{{ query }}"</p>
                    </div>

                    <div v-else>
                        <p v-if="results.length > 0"
                            class="text-vault-400 text-xs font-medium px-2 pb-2 mb-3 border-b border-vault-700">{{
                            results.length }} result{{ results.length !== 1 ? 's' : '' }}</p>
                        <div class="space-y-1">
                            <button v-for="item in results" :key="item.id" @click="selectItem(item)"
                                :disabled="fetchingDetails"
                                class="w-full flex items-center gap-3 p-2 rounded-xl hover:bg-vault-700/70 transition-colors text-left cursor-pointer group disabled:opacity-50 disabled:cursor-wait">
                                <div
                                    class="w-12 h-[72px] rounded-lg bg-vault-700 overflow-hidden flex-shrink-0 border border-vault-600">
                                    <img v-if="item.poster_url" :src="item.poster_url" :alt="item.title"
                                        class="w-full h-full object-cover" loading="lazy" />
                                    <div v-else
                                        class="w-full h-full flex items-center justify-center text-vault-600 text-lg">📖
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p
                                        class="text-white text-sm font-medium truncate group-hover:text-emerald-400 transition-colors">
                                        {{ item.title }}</p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span v-if="item.authors" class="text-vault-500 text-xs truncate">{{
                                            item.authors }}</span>
                                        <span v-if="item.authors && item.year" class="text-vault-600">&middot;</span>
                                        <span v-if="item.year" class="text-vault-500 text-xs">{{ item.year }}</span>
                                    </div>
                                </div>
                                <div v-if="fetchingDetails"
                                    class="w-4 h-4 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin flex-shrink-0">
                                </div>
                                <svg v-else
                                    class="w-4 h-4 text-vault-600 group-hover:text-emerald-400 transition-colors flex-shrink-0"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                        <div v-if="query.length < 2" class="text-center py-12">
                            <div
                                class="w-14 h-14 rounded-2xl bg-vault-700 flex items-center justify-center mx-auto mb-3 text-2xl">
                                📖</div>
                            <p class="text-vault-400 text-sm">Search for a book to auto-fill details and cover</p>
                        </div>
                    </div>
                </div>
                <div v-if="fetchingDetails" class="px-5 py-3 border-t border-vault-700 flex-shrink-0 bg-vault-800">
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin">
                        </div><span class="text-vault-300 text-xs">Loading full details...</span>
                    </div>
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
.tmdb-leave-to {
    opacity: 0;
    transform: scaleY(0.95) translateY(-8px);
}
</style>