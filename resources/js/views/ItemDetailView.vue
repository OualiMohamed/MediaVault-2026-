<script setup>
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'
import ItemFormModal from '../components/ItemFormModal.vue'
import TrailerModal from '../components/TrailerModal.vue'
// import { useNetworkLogo } from '../composables/useNetworkLogo'
import { useCollectionStore } from '../stores/collection'

const route = useRoute()
const router = useRouter()

const item = ref(null)
const loading = ref(true)
const error = ref('')
const showEditModal = ref(false)
const showTrailer = ref(false)
// const { logoUrl: networkLogo, loading: logoLoading, fetchLogo: fetchNetworkLogo, clear: clearNetworkLogo, dispose: disposeNetworkLogo } = useNetworkLogo()
const store = useCollectionStore()

const type = computed(() => {
    const raw = route.params.type
    const map = { 'tv-shows': 'tv_show', 'movies': 'movie', 'books': 'book', 'games': 'game', 'music': 'music' }
    return map[raw] || raw
})

const typeConfig = {
    movie: { label: 'Movie', icon: '\u{1F3AC}', color: 'amber' },
    book: { label: 'Book', icon: '\u{1F4D6}', color: 'emerald' },
    game: { label: 'Game', icon: '\u{1F3AE}', color: 'sky' },
    music: { label: 'Album', icon: '\u{1F3B5}', color: 'violet' },
    tv_show: { label: 'TV Show', icon: '\u{1F4FA}', color: 'rose' },
}

const config = computed(() => typeConfig[type.value] || typeConfig.movie)

const statusColors = {
    owned: 'text-emerald-400 bg-emerald-500/15 border-emerald-500/20',
    wishlist: 'text-amber-400 bg-amber-500/15 border-amber-500/20',
    borrowed: 'text-sky-400 bg-sky-500/15 border-sky-500/20',
    sold: 'text-vault-300 bg-vault-600/20 border-vault-500/20',
    lost: 'text-rose-400 bg-rose-500/15 border-rose-500/20',
}

const watchStatusColors = {
    watching: 'text-rose-400 bg-rose-500/15 border-rose-500/20',
    completed: 'text-emerald-400 bg-emerald-500/15 border-emerald-500/20',
    dropped: 'text-vault-300 bg-vault-600/20 border-vault-500/20',
    plan_to_watch: 'text-sky-400 bg-sky-500/15 border-sky-500/20',
}

const platformConfig = {
    'PS5': { bg: 'bg-[#003087]', text: 'text-white', icon: 'fa-brands fa-playstation' },
    'PS4': { bg: 'bg-[#003087]/80', text: 'text-blue-200', icon: 'fa-brands fa-playstation' },
    'PS3': { bg: 'bg-[#37392e]', text: 'text-gray-200', icon: 'fa-brands fa-playstation' },
    'PS Vita': { bg: 'bg-[#003087]/60', text: 'text-blue-300', icon: 'fa-brands fa-playstation' },
    'Switch': { bg: 'bg-[#e60012]', text: 'text-white', icon: 'fa-solid fa-gamepad' },
    'Wii U': { bg: 'bg-[#8b8b8b]', text: 'text-white', icon: 'fa-solid fa-gamepad' },
    'Wii': { bg: 'bg-[#8b8b8b]', text: 'text-white', icon: 'fa-solid fa-gamepad' },
    'Nintendo DS': { bg: 'bg-[#333]', text: 'text-white', icon: 'fa-solid fa-gamepad' },
    'Xbox Series X': { bg: 'bg-[#107c10]', text: 'text-white', icon: 'fa-brands fa-xbox' },
    'Xbox One': { bg: 'bg-[#107c10]/80', text: 'text-green-200', icon: 'fa-brands fa-xbox' },
    'PC': { bg: 'bg-[#0078d4]', text: 'text-white', icon: 'fa-brands fa-windows' },
    'Steam': { bg: 'bg-[#1b2838]', text: 'text-white', icon: 'fa-brands fa-steam' },
    'Other': { bg: 'bg-vault-600', text: 'text-vault-200', icon: 'fa-solid fa-gamepad' },
}

function getPlatformStyle(platformName) {
    return platformConfig[platformName] || platformConfig['Other']
}

const networkLogo = computed(() => {
    const path = item.value?.details?.network_logo
    if (!path) return null
    return `/api/tmdb/poster?size=w185&path=${path}`
})

const hasTrailer = computed(() => {
    return (type.value === 'movie' || type.value === 'tv_show') && !!item.value?.details?.trailer_url
})

const ratingPercent = computed(() => {
    const r = item.value?.details?.personal_rating
    if (!r) return 0
    return Math.round((r / 10) * 100)
})

const ratingOffset = computed(() => {
    return 2 * Math.PI * 38 - (ratingPercent.value / 100) * 2 * Math.PI * 38
})

const ratingColor = computed(() => {
    const r = ratingPercent.value
    if (r >= 70) return '#10b981'
    if (r >= 40) return '#f59e0b'
    return '#f43f5e'
})

const metadata = computed(() => {
    const d = item.value?.details
    if (!d) return []
    const rows = []

    // At the top of metadata computed, before the type-specific blocks:
    if (d.franchise?.name) {
        rows.push({
            label: 'Franchise',
            value: `${d.franchise.name} #${d.franchise_position}`,
            link: `/franchise/${d.franchise.id}`,
        });
    }

    if (type.value === 'movie') {
        if (item.value.barcode) rows.push({ label: 'Barcode', value: item.value.barcode, copyable: true })
        if (d.director) rows.push({ label: 'Director', value: d.director })
        if (d.runtime_minutes) {
            const h = Math.floor(d.runtime_minutes / 60)
            const m = d.runtime_minutes % 60
            rows.push({ label: 'Runtime', value: h > 0 ? `${h}h ${m}m` : `${m}m` })
        }
        if (d.release_year) rows.push({ label: 'Year', value: d.release_year })
        if (d.imdb_id) rows.push({ label: 'IMDb', value: d.imdb_id, link: `https://www.imdb.com/title/${d.imdb_id}` })
        if (d.video_quality) rows.push({ label: 'Video Quality', value: d.video_quality })
        if (d.audio_format) {
            const formats = Array.isArray(d.audio_format) ? d.audio_format : [d.audio_format]
            rows.push({ label: 'Audio', value: formats })
        }
        if (d.language) rows.push({ label: 'Language', value: d.language })
        if (d.seen) rows.push({ label: 'Seen', value: d.date_seen ? `Seen on ${d.date_seen}` : 'Yes' })
    }

    if (type.value === 'book') {
        if (item.value.barcode) rows.push({ label: 'ISBN', value: item.value.barcode, copyable: true })
        if (d.author) rows.push({ label: 'Author', value: d.author })
        if (d.publisher) rows.push({ label: 'Publisher', value: d.publisher })
        if (d.page_count) rows.push({ label: 'Pages', value: d.page_count })
        if (d.release_year) rows.push({ label: 'Year', value: d.release_year })
        if (d.read) rows.push({ label: 'Read', value: d.date_finished ? `Finished ${d.date_finished}` : 'Yes' })
    }

    if (type.value === 'game') {
        if (item.value.barcode) rows.push({ label: 'Barcode', value: item.value.barcode, copyable: true })
        if (d.platform) rows.push({ label: 'Platform', value: d.platform })
        if (d.publisher) rows.push({ label: 'Publisher', value: d.publisher })
        if (d.release_year) rows.push({ label: 'Year', value: d.release_year })
        if (d.completed) rows.push({ label: 'Completed', value: d.completion_date ? `Finished ${d.completion_date}` : 'Yes' })
    }

    if (type.value === 'tv_show') {
        if (item.value.barcode) rows.push({ label: 'Barcode', value: item.value.barcode, copyable: true })
        if (d.network) rows.push({ label: 'Network', value: d.network })
        if (d.director) rows.push({ label: 'Creator', value: d.director }) // Add this
        if (d.total_seasons) rows.push({ label: 'Total Seasons', value: d.total_seasons })
        if (d.total_episodes) rows.push({ label: 'Total Episodes', value: d.total_episodes })
        if (d.release_year) rows.push({ label: 'Year', value: d.release_year })
        if (d.watch_status) {
            const label = d.watch_status === 'plan_to_watch' ? 'Plan to Watch' : d.watch_status.charAt(0).toUpperCase() + d.watch_status.slice(1)
            rows.push({ label: 'Status', value: label })
        }
        if (d.current_season && d.current_episode) {
            rows.push({ label: 'Currently At', value: `S${String(d.current_season).padStart(2, '0')}E${String(d.current_episode).padStart(2, '0')}` })
        }
    }

    if (type.value === 'music') {
        if (item.value.barcode) rows.push({ label: 'Barcode', value: item.value.barcode, copyable: true })
        if (d.artist) rows.push({ label: 'Artist', value: d.artist })
        if (d.label) rows.push({ label: 'Label', value: d.label })
        if (d.track_count) rows.push({ label: 'Tracks', value: d.track_count })
        if (d.release_year) rows.push({ label: 'Year', value: d.release_year })
        if (d.vinyl_speed) rows.push({ label: 'Vinyl Speed', value: `${d.vinyl_speed} RPM` })
    }

    return rows
})

const tags = computed(() => {
    const d = item.value?.details
    if (!d) return []
    const list = []
    if (d.format) list.push({ label: d.format, color: 'bg-vault-600 text-vault-100' })

    // Change this:
    // if (d.platform) list.push({ label: d.platform, color: 'bg-vault-600 text-vault-100' })
    // To this:
    if (d.platform) {
        const style = getPlatformStyle(d.platform)
        list.push({ label: d.platform, color: `${style.bg} ${style.text}`, icon: style.icon })
    }

    if (d.genre) {
        d.genre.split(',').map(g => g.trim()).filter(Boolean).forEach(g => {
            list.push({ label: g, color: 'bg-vault-700 text-vault-200' })
        })
    }
    return list
})

const coverUrl = computed(() => {
    if (!item.value?.cover_image) return null
    return '/storage/' + item.value.cover_image
})

const prevItem = computed(() => {
    const ids = store.sortedIds
    const idx = ids.indexOf(Number(route.params.id))
    if (idx <= 0) return null
    return { id: ids[idx - 1], type: type.value }
})

const nextItem = computed(() => {
    const ids = store.sortedIds
    const idx = ids.indexOf(Number(route.params.id))
    if (idx === -1 || idx >= ids.length - 1) return null
    return { id: ids[idx + 1], type: type.value }
})

function goToItem(item) {
    const pathMap = { movie: '/movies', book: '/books', game: '/games', music: '/music', tv_show: '/tv-shows' }
    router.push(`${pathMap[item.type] || '/'}/${item.id}`)
}

function goBack() {
    const pathMap = { movie: '/movies', book: '/books', game: '/games', music: '/music', tv_show: '/tv-shows' }
    router.push(pathMap[type.value] || '/')
}

async function fetchItem() {
    loading.value = true
    error.value = ''
    showTrailer.value = false
    try {
        const { data } = await api.get(`/collection/${type.value}/${route.params.id}`)
        item.value = data
    } catch (err) {
        if (err.response?.status === 404) {
            error.value = 'This item does not exist or was deleted.'
        } else {
            error.value = 'Failed to load item details.'
        }
    } finally {
        loading.value = false
    }
}

function handleEditSaved() {
    showEditModal.value = false
    fetchItem()
}

// onBeforeUnmount(() => disposeNetworkLogo?.())

onMounted(fetchItem)
// Update the existing watcher to also handle navigation
watch(() => route.params.id, (newId, oldId) => {
    if (newId && newId !== oldId) fetchItem()
})

// watch(
//     () => [item.value?.title, item.value?.details?.network],
//     ([title, network]) => {
//         if (type.value === 'tv_show' && title && network) {
//             fetchNetworkLogo(title, network)
//         } else {
//             clearNetworkLogo()
//         }
//     },
//     { immediate: true }
// )
</script>

<template>
    <div class="min-h-screen">
        <div v-if="loading" class="flex items-center justify-center py-40">
            <div class="w-8 h-8 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else-if="error" class="max-w-lg mx-auto px-4 py-20 text-center">
            <p class="text-rose-400 text-lg mb-4">{{ error }}</p>
            <button @click="goBack"
                class="px-5 py-2.5 rounded-xl bg-vault-700 text-white text-sm font-medium hover:bg-vault-600 transition-all">Go
                Back</button>
        </div>


        <div v-else-if="item" class="relative">
            <div class="absolute inset-0 h-[500px] overflow-hidden">
                <div v-if="coverUrl" class="absolute inset-0">
                    <img :src="coverUrl" class="w-full h-full object-cover scale-110 blur-2xl opacity-30" />
                </div>
                <div class="absolute inset-0 bg-gradient-to-b from-vault-950/60 via-vault-950/80 to-vault-950"></div>
            </div>

            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 pt-6">
                <button @click="goBack"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-vault-800/60 backdrop-blur-sm border border-vault-600/50 text-vault-300 hover:text-white hover:bg-vault-700/60 transition-all text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to {{ config.label }}s
                </button>
                <div class="flex items-center gap-2">
                    <button v-if="prevItem" @click="goToItem(prevItem)"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-vault-800/60 backdrop-blur-sm border border-vault-600/50 text-vault-300 hover:text-white hover:bg-vault-700/60 transition-all text-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Previous
                    </button>

                    <button v-if="nextItem" @click="goToItem(nextItem)"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-vault-800/60 backdrop-blur-sm border border-vault-600/50 text-vault-300 hover:text-white hover:bg-vault-700/60 transition-all text-sm">
                        Next
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 pt-8 pb-16">
                <div class="flex flex-col lg:flex-row gap-8 lg:gap-12">
                    <div class="flex-shrink-0 mx-auto lg:mx-0">
                        <div
                            class="w-64 sm:w-72 lg:w-80 rounded-2xl overflow-hidden shadow-2xl shadow-black/50 border border-vault-600/30">
                            <img v-if="coverUrl" :src="coverUrl" :alt="item.title"
                                :class="['block w-full object-cover', type === 'music' ? 'aspect-square' : 'aspect-2/3']" />
                            <div v-else
                                :class="['block w-full bg-gradient-to-br from-vault-700 to-vault-800 flex items-center justify-center', type === 'music' ? 'aspect-square' : 'aspect-2/3']">
                                <span class="text-7xl opacity-25">{{ config.icon }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-5">
                            <div>
                                <h1
                                    class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white tracking-tight leading-tight">
                                    {{ item.title }}</h1>
                                <p class="text-vault-300 text-lg mt-2 flex items-center gap-2">
                                    <img v-if="type === 'tv_show' && item.details?.network && networkLogo"
                                        :src="networkLogo" :alt="item.details.network"
                                        class="h-6 w-auto object-contain rounded bg-white/15 p-1 flex-shrink-0" />
                                    <span v-if="item.details?.director">{{ item.details.director }}</span>
                                    <span v-if="item.details?.author">{{ item.details.author }}</span>
                                    <span v-if="item.details?.artist">{{ item.details.artist }}</span>
                                    <span v-if="type === 'tv_show' && item.details?.network && !networkLogo">{{
                                        item.details.network }}</span>
                                </p>
                            </div>
                            <div v-if="item.details?.personal_rating"
                                class="flex-shrink-0 flex items-center justify-center"
                                :title="`${item.details.personal_rating}/10`">
                                <div class="relative w-20 h-20">
                                    <svg class="w-20 h-20 -rotate-90" viewBox="0 0 80 80">
                                        <circle cx="40" cy="40" r="38" fill="none" stroke="#333" stroke-width="4" />
                                        <circle cx="40" cy="40" r="38" fill="none" :stroke="ratingColor"
                                            stroke-width="4" stroke-linecap="round" :stroke-dasharray="2 * Math.PI * 38"
                                            :stroke-dashoffset="ratingOffset" class="transition-all duration-700" />
                                    </svg>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <span class="text-lg font-bold" :style="{ color: ratingColor }">{{ ratingPercent
                                            }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="tags.length" class="flex flex-wrap gap-2 mb-6">
                            <span v-for="(tag, i) in tags" :key="i"
                                :class="['inline-flex items-center gap-1.2 px-3 py-1 rounded-full text-xs font-medium', tag.color]">
                                <i v-if="tag.icon" :class="tag.icon"></i>
                                {{ tag.label }}
                            </span>
                            <span v-if="item.status"
                                :class="['px-3 py-1 rounded-full text-xs font-medium border', statusColors[item.status] || '']">{{
                                    item.status.charAt(0).toUpperCase() + item.status.slice(1) }}</span>
                            <span v-if="type === 'tv_show' && item.details?.watch_status"
                                :class="['px-3 py-1 rounded-full text-xs font-medium border', watchStatusColors[item.details.watch_status] || '']">{{
                                    item.details.watch_status === 'plan_to_watch' ? 'Plan to Watch' :
                                        item.details.watch_status.charAt(0).toUpperCase() + item.details.watch_status.slice(1)
                                }}</span>
                        </div>

                        <div v-if="item.notes" class="mb-8">
                            <p class="text-vault-200 text-base leading-relaxed">{{ item.notes }}</p>
                        </div>

                        <!-- Metadata Grid — NO <template> wrappers, uses v-if/v-else-if on real elements -->
                        <!-- Metadata Grid -->
                        <div v-if="metadata.length || item.details?.series?.name"
                            class="grid grid-cols-2 sm:grid-cols-3 gap-x-8 gap-y-4 mb-8">

                            <!-- Series badge — OUTSIDE the loop -->
                            <div v-if="item.details?.series?.name" class="flex flex-col">
                                <span
                                    class="text-vault-500 text-xs font-medium uppercase tracking-wider mb-0.5">Series</span>
                                <span
                                    class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-md bg-violet-500/15 text-violet-400 w-fit">
                                    {{ item.details.series.name }} #{{ item.details.series_position }}
                                </span>
                            </div>

                            <div v-for="row in metadata" :key="row.label" class="flex flex-col">
                                <span class="text-vault-500 text-xs font-medium uppercase tracking-wider mb-0.5">{{
                                    row.label }}</span>

                                <a v-if="row.link" :href="row.link" target="_blank" rel="noopener"
                                    class="text-amber-400 hover:text-amber-300 text-sm font-medium transition-colors">
                                    {{ row.value }}
                                    <svg class="inline w-3 h-3 ml-0.5" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>

                                <button v-else-if="row.copyable" @click="navigator.clipboard.writeText(row.value)"
                                    class="text-left text-white text-sm font-mono hover:text-amber-400 transition-colors group flex items-center gap-1.5"
                                    title="Click to copy">
                                    <span>{{ row.value }}</span>
                                    <svg class="w-3.5 h-3.5 text-vault-500 group-hover:text-amber-400 transition-colors flex-shrink-0"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </button>

                                <div v-else-if="row.label === 'Audio'" class="flex flex-wrap gap-1.5">
                                    <span v-for="fmt in (Array.isArray(row.value) ? row.value : row.value.split(', '))"
                                        :key="fmt"
                                        class="text-xs font-medium px-2 py-0.5 rounded-md bg-vault-700 text-vault-200">
                                        {{ fmt }}
                                    </span>
                                </div>

                                <!-- Network row -->
                                <div v-else-if="row.label === 'Network' && type === 'tv_show'"
                                    class="flex items-center gap-2.5">
                                    <img v-if="networkLogo" :src="networkLogo" :alt="row.value"
                                        class="h-6 w-auto object-contain rounded bg-white/15 p-1 flex-shrink-0" />
                                    <span v-if="!networkLogo" class="text-white text-sm font-medium">{{ row.value
                                    }}</span>
                                </div>

                                <!-- Default fallback -->
                                <span v-else class="text-white text-sm font-medium">{{ row.value }}</span>
                            </div>
                        </div>

                        <!-- Tracklist -->
                        <div v-if="type === 'music' && item.details?.tracks?.length" class="mb-8">
                            <h3 class="text-sm font-semibold text-vault-300 uppercase tracking-wider mb-4">Tracklist
                            </h3>
                            <div class="bg-vault-800/50 border border-vault-700 rounded-xl overflow-hidden">
                                <div v-for="(track, index) in item.details.tracks" :key="index"
                                    class="flex items-center gap-4 px-4 py-3 text-sm"
                                    :class="index !== item.details.tracks.length - 1 ? 'border-b border-vault-700/50' : ''">
                                    <span class="text-vault-500 font-mono text-xs w-8 text-right flex-shrink-0">{{
                                        track.position }}</span>
                                    <span class="text-white flex-1 truncate">{{ track.title }}</span>
                                    <span v-if="track.duration"
                                        class="text-vault-400 text-xs font-mono flex-shrink-0">{{ track.duration
                                        }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Cast Section -->
                        <div v-if="item.details?.actors?.length" class="mb-8">
                            <h3 class="text-sm font-semibold text-vault-300 uppercase tracking-wider mb-4">Cast</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div v-for="actor in item.details.actors" :key="actor.tmdb_id || actor.name"
                                    class="flex items-center gap-3 bg-vault-800/50 border border-vault-700 rounded-xl p-3">
                                    <div
                                        class="w-10 h-10 rounded-full bg-vault-700 flex items-center justify-center text-vault-300 font-bold text-sm flex-shrink-0">
                                        {{ actor.name ? actor.name.charAt(0) : '?' }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-white text-sm font-medium truncate">{{ actor.name }}</p>
                                        <p v-if="actor.character" class="text-vault-500 text-xs truncate">as {{
                                            actor.character }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="type === 'tv_show' && item.details?.seasons && item.details.seasons.length > 0"
                            class="mb-8">
                            <h3 class="text-sm font-semibold text-vault-300 uppercase tracking-wider mb-4">Owned Seasons
                                ({{ item.details.seasons.length }} of {{ item.details.total_seasons || '?' }})</h3>
                            <div class="flex flex-wrap gap-2">
                                <div v-for="s in item.details.seasons" :key="s.season"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-vault-800 border border-vault-600 rounded-xl">
                                    <span class="text-white font-bold text-sm">S{{ String(s.season).padStart(2, '0')
                                        }}</span>
                                    <span class="w-px h-4 bg-vault-600"></span>
                                    <span class="text-vault-300 text-sm">{{ s.format }}</span>
                                    <template v-if="s.video_quality || s.audio_format || s.language">
                                        <span class="w-px h-4 bg-vault-600"></span>
                                        <span v-if="s.video_quality" class="text-sky-400 text-xs font-medium">{{
                                            s.video_quality }}</span>
                                        <template v-if="s.audio_format && s.audio_format.length">
                                            <span v-for="af in s.audio_format" :key="af"
                                                class="text-vault-300 text-xs font-medium px-1.5 py-0.5 rounded bg-vault-600/50">
                                                {{ af }}
                                            </span>
                                        </template>
                                        <span v-if="s.language"
                                            class="text-amber-400 text-xs font-semibold uppercase">{{ s.language
                                            }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="bg-vault-800/70 backdrop-blur-sm border border-vault-700 rounded-2xl p-6 mb-8">
                            <h3 class="text-sm font-semibold text-vault-300 uppercase tracking-wider mb-4">Collection
                                Details</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
                                <div v-if="item.purchase_price">
                                    <span
                                        class="text-vault-500 text-xs font-medium uppercase tracking-wider block mb-1">Price
                                        Paid</span>
                                    <span class="text-white text-xl font-bold">${{
                                        Number(item.purchase_price).toFixed(2) }}</span>
                                </div>
                                <div v-if="item.purchase_date">
                                    <span
                                        class="text-vault-500 text-xs font-medium uppercase tracking-wider block mb-1">Purchased</span>
                                    <span class="text-white text-sm font-medium">{{ item.purchase_date }}</span>
                                </div>
                                <div>
                                    <span
                                        class="text-vault-500 text-xs font-medium uppercase tracking-wider block mb-1">Condition</span>
                                    <span class="text-white text-sm font-medium">{{ item.condition === 'near_mint' ?
                                        'Near Mint' : item.condition?.charAt(0).toUpperCase() + item.condition?.slice(1)
                                        }}</span>
                                </div>
                                <div>
                                    <span
                                        class="text-vault-500 text-xs font-medium uppercase tracking-wider block mb-1">Added</span>
                                    <span class="text-vault-300 text-sm">{{ item.created_at }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button v-if="hasTrailer" @click.prevent="showTrailer = true"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 text-white font-semibold rounded-xl hover:bg-red-500 transition-all shadow-lg shadow-red-600/20 text-sm">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z" />
                                </svg>
                                Play Trailer
                            </button>
                            <button @click="showEditModal = true"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-amber-500 to-ember-500 text-white font-semibold rounded-xl hover:from-amber-400 hover:to-ember-400 transition-all shadow-lg shadow-amber-500/20 text-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                Edit {{ config.label }}
                            </button>
                            <button @click="goBack"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-vault-700 text-vault-200 font-medium rounded-xl hover:bg-vault-600 hover:text-white transition-all text-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                                Back
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <TrailerModal :url="item?.details?.trailer_url || ''" :open="showTrailer" @close="showTrailer = false" />

        <ItemFormModal v-if="showEditModal && item" :type="type" :item="item" @close="showEditModal = false"
            @saved="handleEditSaved" />
    </div>
</template>