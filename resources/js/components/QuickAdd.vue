<script setup>
import { ref, computed, nextTick } from 'vue'
import api from '../api'
import { useCollectionStore } from '../stores/collection'

const props = defineProps({ type: { type: String, required: true } })
const store = useCollectionStore()

const open = ref(false)
const query = ref('')
const results = ref([])
const loading = ref(false)
const saving = ref(null)
const message = ref('')
const inputRef = ref(null)
let timeout = null

const apiConfig = computed(() => {
    return {
        movie: { search: '/tmdb/search', detail: '/tmdb/details', key: 'tmdb_id', label: 'Search TMDB...' },
        tv_show: { search: '/tmdb/search', detail: '/tmdb/details', key: 'tmdb_id', label: 'Search TMDB...' },
        game: { search: '/rawg/search', detail: '/rawg/details', key: 'rawg_id', label: 'Search RAWG...' },
        book: { search: '/google-books/search', detail: '/google-books/details', key: 'google_id', label: 'Search Google Books...' },
        music: { search: '/discogs/search', detail: '/discogs/details', key: 'discogs_id', label: 'Search Discogs...' },
    }[props.type] || {}
})

const typeDefaults = computed(() => {
    return {
        movie: { format: 'Digital', status: 'owned', condition: 'near_mint' },
        tv_show: { format: 'Digital', status: 'owned', condition: 'near_mint', watch_status: 'plan_to_watch' },
        game: { platform: 'PC', format: 'Digital', status: 'owned', condition: 'near_mint' },
        book: { status: 'owned', condition: 'near_mint' },
        music: { format: 'Digital', status: 'owned', condition: 'near_mint' },
    }[props.type] || {}
})

function toggle() {
    open.value = !open.value
    if (open.value) {
        query.value = ''
        results.value = []
        message.value = ''
        nextTick(() => inputRef.value?.focus())
    }
}

function onInput() {
    clearTimeout(timeout)
    message.value = ''
    if (query.value.length >= 2) {
        timeout = setTimeout(doSearch, 400)
    } else {
        results.value = []
    }
}

async function doSearch() {
    loading.value = true
    try {
        const { data } = await api.post(apiConfig.value.search, {
            query: query.value,
            type: props.type,
        })
        results.value = data.results || []
    } catch {
        results.value = []
    } finally {
        loading.value = false
    }
}

async function quickAdd(item) {
    saving.value = item.id
    try {
        const { data } = await api.post(apiConfig.value.detail, {
            [apiConfig.value.key]: item.id,
            type: props.type,
        })

        if (data.error) {
            message.value = data.error
            saving.value = null
            return
        }

        const formData = new FormData()
        formData.append('title', data.title || '')

        const defaults = typeDefaults.value
        for (const [key, val] of Object.entries(defaults)) {
            formData.append(key, val)
        }

        // Fill type-specific fields from auto-fill data
        const detailFields = {
            movie: ['format', 'director', 'genre', 'release_year', 'runtime_minutes', 'imdb_id', 'trailer_url', 'video_quality', 'audio_format', 'language', 'actors', 'franchise_name', 'franchise_position'],
            tv_show: ['format', 'director', 'genre', 'release_year', 'total_seasons', 'total_episodes', 'network', 'trailer_url', 'actors', 'franchise_name', 'franchise_position'],
            game: ['platform', 'format', 'genre', 'publisher', 'release_year', 'franchise_name', 'franchise_position'],
            book: ['author', 'isbn', 'publisher', 'page_count', 'genre', 'release_year', 'series_name', 'series_position', 'franchise_name', 'franchise_position'],
            music: ['format', 'artist', 'genre', 'label', 'track_count', 'release_year', 'vinyl_speed', 'franchise_name', 'franchise_position'],
        }

        const arrayFields = ['audio_format', 'tracks']
        const actorTypes = ['movie', 'tv_show']
        const stringMaxFields = ['genre', 'director', 'author', 'isbn', 'publisher', 'network', 'trailer_url', 'imdb_id']

        const fields = detailFields[props.type] || []
        for (const field of fields) {
            let val = data[field]
            if (val === null || val === undefined || val === '') continue

            // Truncate long strings
            if (stringMaxFields.includes(field) && typeof val === 'string') {
                val = val.substring(0, 255)
            }

            // Actors: convert array of objects to comma-separated names
            if (actorTypes.includes(props.type) && field === 'actors' && Array.isArray(val)) {
                formData.append('actors', val.map(a => a.name).join(', '))
                continue
            }

            // JSON arrays
            if (arrayFields.includes(field) && typeof val === 'object') {
                formData.append(field, JSON.stringify(val))
                continue
            }

            // Everything else
            formData.append(field, typeof val === 'object' ? JSON.stringify(val) : val)
        }

        // Handle boolean fields
        if (data.seen) formData.append('seen', '1')
        if (data.read) formData.append('read', '1')

        // Handle cover
        if (data.cover_image) {
            if (data.cover_image.startsWith('/storage/')) {
                formData.append('existing_cover', data.cover_image.replace('/storage/', ''))
            }
        }

        await store.createItem(props.type, formData)
        message.value = `"${data.title}" added!`
        results.value = []
        query.value = ''

        setTimeout(() => {
            message.value = ''
            open.value = false
        }, 1500)
    } catch (err) {
        message.value = err.response?.data?.message || 'Failed to add.'
    } finally {
        saving.value = null
    }
}

function resultSubtitle(item) {
    if (props.type === 'movie' || props.type === 'tv_show') return item.year || ''
    if (props.type === 'book') return item.authors || ''
    if (props.type === 'game') return ''
    if (props.type === 'music') return ''
    return ''
}
</script>

<template>
    <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">

        <!-- Search Panel -->
        <transition name="quick-add-panel">
            <div v-if="open" class="w-80 bg-vault-800 border border-vault-600 rounded-2xl shadow-2xl overflow-hidden">
                <!-- Input -->
                <div class="p-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-vault-400 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8" />
                        <path d="M21 21l-4.35-4.35" />
                    </svg>
                    <input ref="inputRef" v-model="query" type="text" @input="onInput"
                        class="flex-1 bg-transparent text-white placeholder-vault-500 text-sm outline-none"
                        :placeholder="apiConfig.label" />
                    <button v-if="query" @click="query = ''; results = []; message = ''"
                        class="text-vault-500 hover:text-white transition-colors flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Divider -->
                <div class="h-px bg-vault-700"></div>

                <!-- Message -->
                <div v-if="message" class="px-3 py-2.5 text-xs font-medium text-emerald-400">
                    {{ message }}
                </div>

                <!-- Loading -->
                <div v-else-if="loading" class="px-3 py-4 flex items-center justify-center gap-2">
                    <div class="w-4 h-4 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
                    <span class="text-vault-400 text-xs">Searching...</span>
                </div>

                <!-- Results -->
                <div v-else-if="results.length" class="max-h-64 overflow-y-auto">
                    <button v-for="item in results" :key="item.id" @click="quickAdd(item)"
                        :disabled="saving === item.id"
                        class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-vault-700/70 transition-colors text-left disabled:opacity-50 disabled:cursor-wait group">
                        <div
                            class="w-9 h-[54px] rounded-md bg-vault-700 overflow-hidden flex-shrink-0 border border-vault-600">
                            <img v-if="item.poster_url" :src="item.poster_url" class="w-full h-full object-cover"
                                loading="lazy" />
                            <div v-else class="w-full h-full flex items-center justify-center text-vault-600 text-sm">?
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p
                                class="text-white text-sm font-medium truncate group-hover:text-amber-400 transition-colors">
                                {{ item.title }}</p>
                            <p v-if="resultSubtitle(item)" class="text-vault-500 text-xs truncate mt-0.5">{{
                                resultSubtitle(item) }}</p>
                        </div>
                        <div v-if="saving === item.id"
                            class="w-4 h-4 border-2 border-amber-500 border-t-transparent rounded-full animate-spin flex-shrink-0">
                        </div>
                        <svg v-else
                            class="w-4 h-4 text-vault-600 group-hover:text-emerald-400 transition-colors flex-shrink-0"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                </div>

                <!-- No results -->
                <div v-else-if="query.length >= 2" class="px-3 py-4 text-center">
                    <p class="text-vault-500 text-xs">No results for "{{ query }}"</p>
                </div>

                <!-- Empty state -->
                <div v-else class="px-3 py-4 text-center">
                    <p class="text-vault-500 text-xs">Type at least 2 characters</p>
                </div>
            </div>
        </transition>

        <!-- FAB Button -->
        <button @click="toggle"
            class="w-14 h-14 rounded-2xl flex items-center justify-center shadow-lg transition-all hover:scale-105 active:scale-95"
            :class="open
                ? 'bg-vault-700 border border-vault-500 shadow-vault-900/50 rotate-45'
                : 'bg-gradient-to-br from-amber-500 to-amber-600 shadow-amber-500/30'">
            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
        </button>
    </div>
</template>

<style scoped>
.quick-add-panel-enter-active,
.quick-add-panel-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
    transform-origin: bottom right;
}

.quick-add-panel-enter-from,
.quick-add-panel-leave-to {
    opacity: 0;
    transform: scale(0.9) translateY(8px);
}
</style>