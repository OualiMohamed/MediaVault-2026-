<script setup>
import { ref, reactive, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useCollectionStore } from '../stores/collection'
import api from '../api'
import BarcodeScanner from './BarcodeScanner.vue'
import TmdbSearchModal from './TmdbSearchModal.vue'
import RawgSearchModal from './RawgSearchModal.vue'
import GoogleBooksSearchModal from './GoogleBooksSearchModal.vue'
import DiscogsSearchModal from './DiscogsSearchModal.vue'

const props = defineProps({
    type: { type: String, required: true },
    item: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])
const store = useCollectionStore()
const route = useRoute()
const submitting = ref(false)
const errors = ref({})
const serverError = ref('')
const coverPreview = ref(null)
const showScanner = ref(false)
const lookupLoading = ref(false)
const lookupMessage = ref('')
const existingCover = ref('')
const seasons = ref([{ season: 1, format: 'Digital', video_quality: '', audio_format: '', language: '' }])
const showTmdbSearch = ref(false)
const tmdbMessage = ref('')
const showRawgSearch = ref(false)
const rawgMessage = ref('')
const showGoogleBooksSearch = ref(false)
const googleBooksMessage = ref('')
const showDiscogsSearch = ref(false)
const discogsMessage = ref('')

const isEditing = computed(() => !!props.item)

const form = reactive({
    title: '',
    cover_image: null,
    purchase_date: '',
    purchase_price: '',
    condition: 'near_mint',
    status: 'owned',
    notes: '',
    barcode: '',
    format: 'Blu-ray',
    runtime_minutes: '',
    director: '',
    genre: '',
    personal_rating: '',
    release_year: '',
    imdb_id: '',
    author: '',
    isbn: '',
    page_count: '',
    publisher: '',
    read: false,
    date_finished: '',
    platform: 'PS5',
    completed: false,
    completion_date: '',
    artist: '',
    label: '',
    track_count: '',
    vinyl_speed: '',
    tracks: [],
    watch_status: 'plan_to_watch',
    current_season: '',
    current_episode: '',
    total_seasons: '',
    total_episodes: '',
    network: '',
    trailer_url: '',
    seen: false,       // add these two
    date_seen: '',
    video_quality: '',
    audio_format: '',
    language: '',
    actors: '', // Simple string for manual input
})

const formatOptions = computed(() => {
    const map = {
        movie: ['DVD', 'Blu-ray', '4K UHD', 'Digital', 'VHS'],
        game: ['Physical', 'Digital'],
        music: ['CD', 'Vinyl', 'Digital', 'Cassette', '8-Track'],
        tv_show: ['Digital', 'DVD', 'Blu-ray', '4K UHD', 'VHS'],
    }
    return map[props.type] || []
})

const platformOptions = [
    'PS5', 'PS4', 'PS3', 'PS Vita', 'Switch', 'Wii U', 'Wii', 'Nintendo DS',
    'Xbox Series X', 'Xbox One', 'PC', 'Steam', 'Other',
]

const videoQualityOptions = [
    'Ultra HDLight', 'HDLight 1080p', 'HDLight 1080p(x265)', 'HDLight 720p', 'HDLight 720p(x265)',
    'SD', 'dvdrip', '720p', '1080p', 'TVrip', 'Full HD', 'Blu-Ray 3D', '4k',
]

const audioFormatOptions = [
    'DTS', 'DTS-HD', 'DTS-MA', 'Dolby Digital 5.1', 'Dolby Digital 2.1', 'Dolby Digital 2.0', 'Dolby Atmos', 'Stereo', 'Mono', 'mp3',
]

const languageOptions = [
    'MULTI', 'VO', 'English', 'French', 'Arabic',
]

const typeFieldMap = {
    movie: ['format', 'runtime_minutes', 'director', 'genre', 'personal_rating', 'release_year', 'imdb_id', 'trailer_url', 'seen', 'date_seen', 'video_quality', 'audio_format', 'language', 'actors'],
    book: ['author', 'isbn', 'page_count', 'publisher', 'genre', 'personal_rating', 'release_year', 'read', 'date_finished'],
    game: ['platform', 'format', 'genre', 'publisher', 'personal_rating', 'release_year', 'completed', 'completion_date'],
    music: ['format', 'artist', 'genre', 'label', 'track_count', 'personal_rating', 'release_year', 'vinyl_speed'],
    tv_show: ['format', 'total_seasons', 'total_episodes', 'network', 'network_logo', 'director', 'genre', 'personal_rating', 'release_year', 'watch_status', 'current_season', 'current_episode', 'seasons', 'trailer_url', 'actors'],
}

const baseFields = ['title', 'barcode', 'purchase_date', 'purchase_price', 'condition', 'status', 'notes']
const booleanFields = ['read', 'completed', 'seen']

const validationErrors = computed(() => {
    const list = []
    const errs = errors?.value
    if (!errs || typeof errs !== 'object') return list
    for (const [field, messages] of Object.entries(errs)) {
        if (!Array.isArray(messages)) continue
        const label = field === '_general' ? 'Error' : field
        messages.forEach(msg => list.push({ field: label, message: msg }))
    }
    return list
})

watch(() => props.item, (item) => {
    if (!item) return
    Object.assign(form, {
        title: item.title || '',
        cover_image: null,
        purchase_date: item.purchase_date || '',
        purchase_price: item.purchase_price ?? '',
        condition: item.condition || 'near_mint',
        status: item.status || 'owned',
        notes: item.notes || '',
        barcode: item.barcode || '',
    })
    existingCover.value = item.cover_image || ''
    if (item.details) {
        Object.keys(item.details).forEach(key => {
            if (key in form) form[key] = item.details[key]
        })
    }

    // Convert actors array back to comma string for the input
    if (item.details?.actors && Array.isArray(item.details.actors)) {
        form.actors = item.details.actors.map(a => a.name || a).join(', ')
    }

    if (item.details?.seasons && Array.isArray(item.details.seasons)) {
        seasons.value = item.details.seasons.map(s => ({
            season: s.season,
            format: s.format || 'Digital',
            video_quality: s.video_quality || '',
            audio_format: s.audio_format || '',
            language: s.language || '',
        }))
    } else {
        seasons.value = [{ season: 1, format: 'Digital', video_quality: '', audio_format: '', language: '' }]
    }
}, { immediate: true })

watch(() => props.type, (type) => {
    if (!isEditing.value) {
        if (type === 'movie') form.format = 'Blu-ray'
        if (type === 'game') { form.platform = 'PS5'; form.format = 'Physical' }
        if (type === 'music') form.format = 'CD'
        if (type === 'tv_show') { form.format = 'Digital'; form.watch_status = 'plan_to_watch' }
    }
    // Force wishlist status when opened from /wishlist
    if (!isEditing.value && route.path === '/wishlist') {
        form.status = 'wishlist'
    }
    if (type !== 'tv_show') {
        seasons.value = [{ season: 1, format: 'Digital', video_quality: '', audio_format: '', language: '' }]
    }
}, { immediate: true })

function handleCoverChange(e) {
    const file = e.target.files[0]
    if (file) {
        form.cover_image = file
        coverPreview.value = URL.createObjectURL(file)
    }
}

async function handleBarcodeScanned(code) {
    showScanner.value = false
    form.barcode = code
    lookupMessage.value = ''
    existingCover.value = ''

    if (props.type === 'movie' || props.type === 'game' || props.type === 'tv_show') {
        lookupMessage.value = 'Barcode saved. Fill in the details manually.'
        return
    }

    lookupLoading.value = true
    try {
        const { data } = await api.post('/barcode/lookup', {
            type: props.type,
            barcode: code,
        })
        if (data.auto_filled) {
            const fieldsToFill = [
                'title', 'author', 'publisher', 'page_count', 'release_year',
                'genre', 'notes', 'artist', 'label', 'track_count', 'format', 'vinyl_speed',
            ]
            fieldsToFill.forEach(field => {
                if (data[field] !== null && data[field] !== undefined && data[field] !== '') {
                    form[field] = data[field]
                }
            })
            if (data.cover_image) {
                existingCover.value = data.cover_image
                coverPreview.value = '/storage/' + data.cover_image
            }
            lookupMessage.value = props.type === 'book' ? 'Auto-filled from Open Library' : 'Auto-filled from MusicBrainz'
        } else {
            lookupMessage.value = data.message || 'No match found.'
        }
    } catch (err) {
        lookupMessage.value = err.response?.data?.message || 'Lookup failed.'
    } finally {
        lookupLoading.value = false
    }
}

async function manualLookup() {
    if (!form.barcode) return
    if (props.type !== 'book' && props.type !== 'music') return
    await handleBarcodeScanned(form.barcode)
}

async function applyTmdbData(data) {
    showTmdbSearch.value = false
    tmdbMessage.value = ''
    existingCover.value = ''

    if (data.title) form.title = data.title
    if (data.director) form.director = data.director
    if (data.network) form.network = data.network
    if (data.network_logo) form.network_logo = data.network_logo  // Add this
    if (data.genre) form.genre = data.genre
    if (data.release_year) form.release_year = data.release_year
    if (data.runtime_minutes) form.runtime_minutes = data.runtime_minutes
    if (data.total_seasons) form.total_seasons = data.total_seasons
    if (data.overview) form.notes = data.overview
    if (data.trailer_url) form.trailer_url = data.trailer_url
    if (data.imdb_id) form.imdb_id = data.imdb_id

    if (data.cover_image) {
        // Strip /storage/ prefix so existingCover stays relative,
        // matching what the edit watcher sets from the database
        existingCover.value = data.cover_image.replace(/^\/storage\//, '')
        coverPreview.value = data.cover_image
        tmdbMessage.value = 'Auto-filled from TMDB — poster downloaded'
    } else {
        tmdbMessage.value = 'Auto-filled from TMDB'
    }

    if (data.actors) {
        form.actors = data.actors.map(a => a.name).join(', ')
    }
}

async function applyRawgData(data) {
    showRawgSearch.value = false
    rawgMessage.value = ''
    existingCover.value = ''

    if (data.title) form.title = data.title
    if (data.publisher) form.publisher = data.publisher
    if (data.genre) form.genre = data.genre
    if (data.release_year) form.release_year = data.release_year
    if (data.platform) form.platform = data.platform // Auto-maps from RAWG!
    if (data.overview) form.notes = data.overview

    if (data.cover_image) {
        existingCover.value = data.cover_image.replace(/^\/storage\//, '')
        coverPreview.value = data.cover_image
        rawgMessage.value = 'Auto-filled from RAWG — cover downloaded'
    } else {
        rawgMessage.value = 'Auto-filled from RAWG'
    }
}

async function applyGoogleBooksData(data) {
    showGoogleBooksSearch.value = false
    googleBooksMessage.value = ''
    existingCover.value = ''

    if (data.title) form.title = data.title
    if (data.author) form.author = data.author
    if (data.isbn) form.isbn = data.isbn
    if (data.publisher) form.publisher = data.publisher
    if (data.page_count) form.page_count = data.page_count
    if (data.release_year) form.release_year = data.release_year
    if (data.genre) form.genre = data.genre
    if (data.overview) form.notes = data.overview

    if (data.cover_image) {
        existingCover.value = data.cover_image.replace(/^\/storage\//, '')
        coverPreview.value = data.cover_image
        googleBooksMessage.value = 'Auto-filled from Google Books — cover downloaded'
    } else {
        googleBooksMessage.value = 'Auto-filled from Google Books'
    }
}

async function applyDiscogsData(data) {
    showDiscogsSearch.value = false
    discogsMessage.value = ''
    existingCover.value = ''

    if (data.title) form.title = data.title
    if (data.artist) form.artist = data.artist
    if (data.label) form.label = data.label
    if (data.track_count) form.track_count = data.track_count
    if (data.release_year) form.release_year = data.release_year
    if (data.genre) form.genre = data.genre

    // Auto-set format and vinyl speed!
    if (data.format) form.format = data.format
    if (data.vinyl_speed) form.vinyl_speed = data.vinyl_speed
    if (data.tracks) form.tracks = data.tracks // Add this

    if (data.cover_image) {
        existingCover.value = data.cover_image.replace(/^\/storage\//, '')
        coverPreview.value = data.cover_image
        discogsMessage.value = 'Auto-filled from Discogs — cover downloaded'
    } else {
        discogsMessage.value = 'Auto-filled from Discogs'
    }
}

async function handleSubmit() {
    errors.value = {}
    serverError.value = ''
    submitting.value = true

    if (coverPreview.value) {
        URL.revokeObjectURL(coverPreview.value)
        coverPreview.value = null
    }

    try {
        const formData = new FormData()
        const activeFields = [...baseFields, ...(typeFieldMap[props.type] || [])]

        activeFields.forEach(field => {
            const value = form[field]
            if (booleanFields.includes(field)) {
                formData.append(field, value ? '1' : '0')
                return
            }
            if (value === '' || value === null || value === undefined) return
            formData.append(field, value)
        })

        // Send seasons as JSON string
        if (props.type === 'tv_show' && seasons.value.length > 0) {
            formData.append('seasons', JSON.stringify(seasons.value))
        }

        // Send tracks as JSON string
        if (props.type === 'music' && form.tracks.length > 0) {
            formData.append('tracks', JSON.stringify(form.tracks))
        }

        if (existingCover.value) {
            formData.append('existing_cover', existingCover.value)
        } else if (form.cover_image instanceof File) {
            formData.append('cover_image', form.cover_image)
        }

        if (isEditing.value) {
            await store.updateItem(props.type, props.item.id, formData)
        } else {
            await store.createItem(props.type, formData)
        }

        emit('saved')
    } catch (err) {
        console.error('Full error response:', err.response?.data)

        if (err.response?.status === 422) {
            // Defensive: data.errors might not exist
            const validationErrors = err.response?.data?.errors

            if (validationErrors && typeof validationErrors === 'object') {
                errors.value = validationErrors
            } else {
                // Laravel returned 422 but not in standard format — show raw message
                errors.value = {
                    _general: [err.response?.data?.message || 'Validation failed. Check the console for details.'],
                }
            }
        } else if (err.response?.status === 401) {
            serverError.value = 'Your session has expired. Please log in again.'
        } else if (err.response?.data?.message) {
            serverError.value = err.response.data.message
        } else {
            serverError.value = 'Something went wrong. Check the browser console.'
        }
    } finally {
        submitting.value = false
    }
}

function fieldError(field) {
    if (!errors?.value?.[field]) return ''
    return errors.value[field][0]
}

function formTitle() {
    if (!isEditing.value) return 'Add'
    if (props.type === 'movie') return 'Edit Movie'
    if (props.type === 'book') return 'Edit Book'
    if (props.type === 'game') return 'Edit Game'
    if (props.type === 'tv_show') return 'Edit TV Show'
    return 'Edit Album'
}

function submitLabel() {
    if (submitting.value) return 'Saving...'
    return isEditing.value ? 'Update' : 'Add to Collection'
}

function addSeason() {
    const nextNum = seasons.value.length > 0
        ? Math.max(...seasons.value.map(s => s.season)) + 1
        : 1
    seasons.value.push({ season: nextNum, format: 'Digital' })
}

function removeSeason(index) {
    seasons.value.splice(index, 1)
}
</script>

<template>
    <transition name="modal">
        <div class="fixed inset-0 z-[60] flex items-start justify-center pt-10 sm:pt-20 px-4 modal-backdrop"
            @click.self="emit('close')">
            <div
                class="bg-vault-800 border border-vault-600 rounded-2xl w-full max-w-2xl max-h-[85vh] overflow-y-auto shadow-2xl">

                <!-- Header -->
                <div
                    class="sticky top-0 bg-vault-800 border-b border-vault-700 px-6 py-4 flex items-center justify-between z-10 rounded-t-2xl">
                    <h2 class="text-lg font-bold text-white">{{ formTitle() }}</h2>
                    <button @click="emit('close')"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-vault-400 hover:text-white hover:bg-vault-700 transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="handleSubmit" class="p-6 space-y-5">

                    <!-- Cover Image -->
                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-2">Cover Image</label>
                        <div class="flex items-center gap-4">
                            <div
                                class="w-24 h-36 rounded-xl bg-vault-700 overflow-hidden flex-shrink-0 border border-vault-600">
                                <img v-if="coverPreview" :src="coverPreview" class="block w-full h-full object-cover" />
                                <img v-else-if="existingCover" :src="'/storage/' + existingCover"
                                    class="block w-full h-full object-cover" />
                                <div v-else
                                    class="block w-full h-full flex items-center justify-center text-vault-500 text-2xl">
                                    {{ type === 'movie' ? '🎬' : type === 'book' ? '📖' : type === 'game' ? '🎮' : type
                                        === 'tv_show' ? '📺' : '🎵' }}
                                </div>
                            </div>
                            <label class="flex-1 cursor-pointer">
                                <input type="file" accept="image/*" @change="handleCoverChange" class="hidden" />
                                <div
                                    class="border-2 border-dashed border-vault-600 rounded-xl p-4 text-center hover:border-vault-500 transition-colors">
                                    <p class="text-vault-300 text-sm">Click to upload cover</p>
                                    <p class="text-vault-500 text-xs mt-1">JPG, PNG up to 2MB</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Barcode -->
                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-1.5">Barcode / ISBN</label>
                        <div class="flex gap-2">
                            <input v-model="form.barcode" type="text"
                                class="flex-1 px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 transition-all text-sm font-mono"
                                placeholder="Scan or type barcode..." />
                            <button v-if="(type === 'book' || type === 'music') && form.barcode" @click="manualLookup"
                                :disabled="lookupLoading" type="button"
                                class="px-4 py-2.5 bg-vault-600 text-white rounded-xl text-sm font-medium hover:bg-vault-500 transition-all disabled:opacity-50 flex items-center gap-1.5">
                                <svg v-if="!lookupLoading" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <div v-else
                                    class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin">
                                </div>
                                Lookup
                            </button>
                            <button @click="showScanner = true" type="button"
                                class="px-4 py-2.5 bg-amber-500/15 text-amber-400 rounded-xl text-sm font-medium hover:bg-amber-500/25 transition-all flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                Scan
                            </button>
                        </div>
                        <div v-if="lookupMessage" class="mt-2 flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 flex-shrink-0"
                                :class="existingCover ? 'text-emerald-400' : 'text-amber-400'" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-xs" :class="existingCover ? 'text-emerald-400' : 'text-amber-400'">{{
                                lookupMessage }}</span>
                        </div>
                    </div>

                    <!-- ═══ TMDB Auto-fill ═══ -->
                    <div class="mt-4">
                        <button v-if="type === 'movie' || type === 'tv_show'" @click="showTmdbSearch = true"
                            type="button"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-sky-500/15 border border-sky-500/30 text-sky-400 rounded-xl text-sm font-medium hover:bg-sky-500/25 hover:border-sky-500/50 transition-all">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M4 4a2 2 0 012-2V4m0 16a2 2 0 01-2 2H6a2 2 0 01-2-2V6m0-16a2 2 0 012-2H4m6 16h10a2 2 0 002 2v4a2 2 0 002 2H6a2 2 0 002-2V6a2 2 0 00-2-2H4" />
                            </svg>
                            Auto-fill from TMDB
                        </button>
                        <p class="text-vault-500 text-xs mt-1.5 text-center">Search by title to auto-fill details and
                            poster</p>
                    </div>

                    <!-- TMDB success message — add right after the barcode lookup message block -->
                    <div v-if="tmdbMessage" class="mt-2 flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 flex-shrink-0 text-sky-400" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12l2 2 4-4m6 2a2 2 0 012-2H4m6 0h8a2 2 0 002 2v4a2 2 0 002-2H6a2 2 0 00-2-2H4" />
                        </svg>
                        <span class="text-xs" :class="existingCover ? 'text-sky-400' : 'text-amber-400'">{{ tmdbMessage
                        }}</span>
                    </div>

                    <!-- Title -->
                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-1.5">Title <span
                                class="text-rose-400">*</span></label>
                        <input v-model="form.title" type="text"
                            class="w-full px-4 py-2.5 bg-vault-700 border rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 transition-all text-sm"
                            :class="fieldError('title') ? 'border-rose-500' : 'border-vault-600'"
                            placeholder="Enter title..." />
                        <p v-if="fieldError('title')" class="text-rose-500 text-xs mt-1">{{ fieldError('title') }}</p>
                    </div>

                    <!-- Movie Fields -->
                    <template v-if="type === 'movie'">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Format <span
                                        class="text-rose-400">*</span></label>
                                <select v-model="form.format"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                    <option v-for="f in formatOptions" :key="f" :value="f">{{ f }}</option>
                                </select>
                                <p v-if="fieldError('format')" class="text-rose-500 text-xs mt-1">{{
                                    fieldError('format') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Director</label>
                                <input v-model="form.director" type="text"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="Director name" />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">Runtime
                                    (min)</label><input v-model="form.runtime_minutes" type="number" min="1"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="120" /></div>
                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">Year</label><input
                                    v-model="form.release_year" type="number" min="1888"
                                    :max="new Date().getFullYear() + 2"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="2024" /></div>

                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">IMDb ID</label><input
                                    v-model="form.imdb_id" type="text"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="tt1234567" /></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Trailer URL</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-vault-500" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                </div>
                                <input v-model="form.trailer_url" type="url"
                                    class="w-full pl-10 pr-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="https://www.youtube.com/watch?v=..." />
                            </div>
                            <p v-if="fieldError('trailer_url')" class="text-rose-500 text-xs mt-1">{{
                                fieldError('trailer_url') }}</p>
                        </div>
                        <!-- Seen toggle + date -->
                        <div class="flex items-end pb-2.5">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.seen" type="checkbox"
                                    class="w-4 h-4 rounded bg-vault-700 border-vault-600 text-amber-500 focus:ring-amber-500/50" />
                                <span class="text-sm text-vault-200">Mark as Seen</span>
                            </label>
                        </div>
                        <div v-if="form.seen">
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Date Seen</label>
                            <input v-model="form.date_seen" type="date"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm" />
                        </div>
                        <!-- Video Quality -->
                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Video Quality</label>
                            <select v-model="form.video_quality"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                <option value="">Not specified</option>
                                <option v-for="q in videoQualityOptions" :key="q" :value="q">{{ q }}</option>
                            </select>
                        </div>

                        <!-- Audio Format -->
                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Audio Format</label>
                            <select v-model="form.audio_format"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                <option value="">Not specified</option>
                                <option v-for="a in audioFormatOptions" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>

                        <!-- Language -->
                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Language</label>
                            <select v-model="form.language"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                <option value="">Not specified</option>
                                <option v-for="l in languageOptions" :key="l" :value="l">{{ l }}</option>
                            </select>
                        </div>

                        <!-- Actors -->
                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Actors</label>
                            <input v-model="form.actors" type="text"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                placeholder="Keanu Reeves, Carrie-Anne Moss..." />
                            <p class="text-vault-500 text-xs mt-1">Separated by commas. Auto-filled by TMDB.</p>
                        </div>
                    </template>

                    <!-- Book Fields -->
                    <template v-if="type === 'book'">
                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Author <span
                                    class="text-rose-400">*</span></label>
                            <input v-model="form.author" type="text"
                                class="w-full px-4 py-2.5 bg-vault-700 border rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                :class="fieldError('author') ? 'border-rose-500' : 'border-vault-600'"
                                placeholder="Author name" />
                            <p v-if="fieldError('author')" class="text-rose-500 text-xs mt-1">{{ fieldError('author') }}
                            </p>
                        </div>
                        <!-- ═══ Google Books Auto-fill ═══ -->
                        <div class="mt-4">
                            <button @click="showGoogleBooksSearch = true" type="button"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 rounded-xl text-sm font-medium hover:bg-emerald-500/25 hover:border-emerald-500/50 transition-all">
                                📖 Auto-fill from Google Books
                            </button>
                            <p class="text-vault-500 text-xs mt-1.5 text-center">Search by title to auto-fill details,
                                ISBN, and cover</p>
                        </div>

                        <!-- Google Books success message -->
                        <div v-if="googleBooksMessage" class="mt-2 flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 flex-shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-xs text-emerald-400">{{ googleBooksMessage }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">ISBN</label><input
                                    v-model="form.isbn" type="text"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="978-..." /></div>
                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">Pages</label><input
                                    v-model="form.page_count" type="number" min="1"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="320" /></div>
                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">Year</label><input
                                    v-model="form.release_year" type="number"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="2024" /></div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">Publisher</label><input
                                    v-model="form.publisher" type="text"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="Publisher" /></div>
                            <div class="flex items-end pb-2.5"><label
                                    class="flex items-center gap-2 cursor-pointer"><input v-model="form.read"
                                        type="checkbox"
                                        class="w-4 h-4 rounded bg-vault-700 border-vault-600 text-amber-500 focus:ring-amber-500/50" /><span
                                        class="text-sm text-vault-200">Mark as Read</span></label></div>
                        </div>
                        <div v-if="form.read"><label class="block text-sm font-medium text-vault-200 mb-1.5">Date
                                Finished</label><input v-model="form.date_finished" type="date"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm" />
                        </div>
                    </template>

                    <!-- Game Fields -->
                    <template v-if="type === 'game'">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Platform <span
                                        class="text-rose-400">*</span></label>
                                <select v-model="form.platform"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                    <option v-for="p in platformOptions" :key="p" :value="p">{{ p }}</option>
                                </select>
                                <p v-if="fieldError('platform')" class="text-rose-500 text-xs mt-1">{{
                                    fieldError('platform') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Format <span
                                        class="text-rose-400">*</span></label>
                                <select v-model="form.format"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                    <option v-for="f in formatOptions" :key="f" :value="f">{{ f }}</option>
                                </select>
                                <p v-if="fieldError('format')" class="text-rose-500 text-xs mt-1">{{
                                    fieldError('format') }}</p>
                            </div>

                            <!-- RAWG success message -->
                            <div v-if="rawgMessage" class="mt-2 flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 flex-shrink-0 text-sky-400" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-xs text-sky-400">{{ rawgMessage }}</span>
                            </div>
                        </div>
                        <!-- ═══ RAWG Auto-fill ═══ -->
                        <div class="mt-4">
                            <button @click="showRawgSearch = true" type="button"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-sky-500/15 border border-sky-500/30 text-sky-400 rounded-xl text-sm font-medium hover:bg-sky-500/25 hover:border-sky-500/50 transition-all">
                                🎮 Auto-fill from RAWG
                            </button>
                            <p class="text-vault-500 text-xs mt-1.5 text-center">Search by title to auto-fill
                                details, platform, and cover</p>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">Year</label><input
                                    v-model="form.release_year" type="number"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="2024" /></div>
                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">Publisher</label><input
                                    v-model="form.publisher" type="text"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="Publisher" /></div>
                            <div class="flex items-end pb-2.5"><label
                                    class="flex items-center gap-2 cursor-pointer"><input v-model="form.completed"
                                        type="checkbox"
                                        class="w-4 h-4 rounded bg-vault-700 border-vault-600 text-amber-500 focus:ring-amber-500/50" /><span
                                        class="text-sm text-vault-200">Completed</span></label></div>
                        </div>
                        <div v-if="form.completed"><label
                                class="block text-sm font-medium text-vault-200 mb-1.5">Completion Date</label><input
                                v-model="form.completion_date" type="date"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm" />
                        </div>
                    </template>

                    <!-- ─── TV Show Fields ─── -->
                    <template v-if="type === 'tv_show'">
                        <!-- Actors -->
                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Actors</label>
                            <input v-model="form.actors" type="text"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                placeholder="Keanu Reeves, Carrie-Anne Moss..." />
                            <p class="text-vault-500 text-xs mt-1">Separated by commas. Auto-filled by TMDB.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Network</label>
                            <input v-model="form.network" type="text"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                placeholder="Netflix, HBO, ABC..." />
                        </div>

                        <!-- Add this Creator/Director block -->
                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Creator</label>
                            <input v-model="form.director" type="text"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                placeholder="Vince Gilligan, David Benioff..." />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Total Seasons</label>
                                <input v-model="form.total_seasons" type="number" min="1"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="5" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Total Episodes</label>
                                <input v-model="form.total_episodes" type="number" min="1"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="50" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Trailer URL</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-vault-500" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                </div>
                                <input v-model="form.trailer_url" type="url"
                                    class="w-full pl-10 pr-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="https://www.youtube.com/watch?v=..." />
                            </div>
                            <p v-if="fieldError('trailer_url')" class="text-rose-500 text-xs mt-1">{{
                                fieldError('trailer_url') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Year</label>
                            <input v-model="form.release_year" type="number" min="1920"
                                :max="new Date().getFullYear() + 2"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                placeholder="2024" />
                        </div>

                        <!-- ── Owned Seasons ── -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <label class="text-sm font-medium text-vault-200">Owned Seasons</label>
                                <button type="button" @click="addSeason"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-vault-600 text-vault-200 hover:bg-vault-500 hover:text-white transition-all">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Season
                                </button>
                            </div>

                            <div v-if="seasons.length === 0"
                                class="text-center py-6 border-2 border-dashed border-vault-600 rounded-xl">
                                <p class="text-vault-400 text-sm">No seasons added yet</p>
                                <button type="button" @click="addSeason"
                                    class="text-amber-400 text-sm font-medium hover:text-amber-300 mt-1">
                                    + Add your first season
                                </button>
                            </div>

                            <div v-else class="space-y-2">
                                <div v-for="(s, index) in seasons" :key="index"
                                    class="bg-vault-700/50 border border-vault-600 rounded-xl p-4 space-y-3">

                                    <!-- Row 1: Season number, format, remove -->
                                    <div class="flex items-center gap-3">
                                        <span class="text-vault-400 text-sm font-medium w-12 flex-shrink-0">S{{
                                            String(s.season).padStart(2, '0') }}</span>

                                        <input v-model.number="s.season" type="number" min="1"
                                            class="w-20 px-2 py-1.5 bg-vault-700 border border-vault-600 rounded-lg text-white text-sm text-center focus:outline-none focus:ring-2 focus:ring-amber-500/50" />

                                        <select v-model="s.format"
                                            class="flex-1 px-3 py-1.5 bg-vault-700 border border-vault-600 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                                            <option value="Digital">Digital</option>
                                            <option value="DVD">DVD</option>
                                            <option value="Blu-ray">Blu-ray</option>
                                            <option value="4K UHD">4K UHD</option>
                                            <option value="VHS">VHS</option>
                                        </select>

                                        <button type="button" @click="removeSeason(index)"
                                            class="w-8 h-8 rounded-lg flex items-center justify-center text-vault-400 hover:text-rose-400 hover:bg-rose-500/15 transition-all flex-shrink-0"
                                            title="Remove season">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Row 2: Video quality, Audio format, Language -->
                                    <div class="grid grid-cols-3 gap-2 pl-12">
                                        <select v-model="s.video_quality"
                                            class="px-2 py-1.5 bg-vault-700 border border-vault-600 rounded-lg text-white text-xs focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                                            <option value="">Video quality</option>
                                            <option v-for="q in videoQualityOptions" :key="q" :value="q">{{ q }}
                                            </option>
                                        </select>
                                        <select v-model="s.audio_format"
                                            class="px-2 py-1.5 bg-vault-700 border border-vault-600 rounded-lg text-white text-xs focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                                            <option value="">Audio format</option>
                                            <option v-for="a in audioFormatOptions" :key="a" :value="a">{{ a }}</option>
                                        </select>
                                        <select v-model="s.language"
                                            class="px-2 py-1.5 bg-vault-700 border border-vault-600 rounded-lg text-white text-xs focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                                            <option value="">Language</option>
                                            <option v-for="l in languageOptions" :key="l" :value="l">{{ l }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Watch Status -->
                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Watch Status</label>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="s in ['watching', 'completed', 'dropped', 'plan_to_watch']" :key="s"
                                    type="button" @click="form.watch_status = s"
                                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all" :class="form.watch_status === s
                                        ? 'bg-rose-500 text-white'
                                        : 'bg-vault-700 text-vault-300 hover:bg-vault-600'">
                                    {{ s === 'plan_to_watch' ? 'Plan to Watch' : s.charAt(0).toUpperCase() + s.slice(1)
                                    }}
                                </button>
                            </div>
                        </div>

                        <!-- Current progress -->
                        <div v-if="form.watch_status === 'watching'"
                            class="bg-vault-700/50 border border-vault-600 rounded-xl p-4">
                            <p class="text-sm font-medium text-vault-200 mb-3">Current Progress</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-vault-400 mb-1">Season</label>
                                    <input v-model="form.current_season" type="number" min="1"
                                        class="w-full px-3 py-2 bg-vault-700 border border-vault-600 rounded-lg text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-rose-500/50 text-sm"
                                        placeholder="2" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-vault-400 mb-1">Episode</label>
                                    <input v-model="form.current_episode" type="number" min="1"
                                        class="w-full px-3 py-2 bg-vault-700 border border-vault-600 rounded-lg text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-rose-500/50 text-sm"
                                        placeholder="5" />
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Music Fields -->
                    <template v-if="type === 'music'">
                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Artist <span
                                    class="text-rose-400">*</span></label>
                            <input v-model="form.artist" type="text"
                                class="w-full px-4 py-2.5 bg-vault-700 border rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                :class="fieldError('artist') ? 'border-rose-500' : 'border-vault-600'"
                                placeholder="Artist name" />
                            <p v-if="fieldError('artist')" class="text-rose-500 text-xs mt-1">{{ fieldError('artist') }}
                            </p>
                        </div>
                        <!-- ═══ Discogs Auto-fill ═══ -->
                        <div class="mt-4">
                            <button @click="showDiscogsSearch = true" type="button"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-violet-500/15 border border-violet-500/30 text-violet-400 rounded-xl text-sm font-medium hover:bg-violet-500/25 hover:border-violet-500/50 transition-all">
                                🎵 Auto-fill from Discogs
                            </button>
                            <p class="text-vault-500 text-xs mt-1.5 text-center">Search for vinyl, CD or cassette to
                                auto-fill details and cover</p>
                        </div>

                        <!-- Discogs success message -->
                        <div v-if="discogsMessage" class="mt-2 flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 flex-shrink-0 text-violet-400" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-xs text-violet-400">{{ discogsMessage }}</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Format <span
                                        class="text-rose-400">*</span></label>
                                <select v-model="form.format"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                    <option v-for="f in formatOptions" :key="f" :value="f">{{ f }}</option>
                                </select>
                                <p v-if="fieldError('format')" class="text-rose-500 text-xs mt-1">{{
                                    fieldError('format') }}</p>
                            </div>
                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">Tracks</label><input
                                    v-model="form.track_count" type="number" min="1"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="12" /></div>
                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">Year</label><input
                                    v-model="form.release_year" type="number"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="2024" /></div>
                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">Label</label><input
                                    v-model="form.label" type="text"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="Label" /></div>
                        </div>
                        <div v-if="form.format === 'Vinyl'">
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Vinyl Speed (RPM)</label>
                            <select v-model="form.vinyl_speed"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                <option value="">Select speed</option>
                                <option value="33">33 RPM</option>
                                <option value="45">45 RPM</option>
                                <option value="78">78 RPM</option>
                            </select>
                        </div>
                    </template>

                    <!-- Genre -->
                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-1.5">Genre</label>
                        <input v-model="form.genre" type="text"
                            class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                            placeholder="e.g. Action, Sci-Fi, Rock" />
                    </div>

                    <!-- Rating -->
                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-2">Personal Rating</label>
                        <div class="flex items-center gap-1">
                            <button v-for="n in 10" :key="n" type="button"
                                @click="form.personal_rating = form.personal_rating === n ? '' : n"
                                class="star w-7 h-7 rounded flex items-center justify-center text-sm font-bold transition-all"
                                :class="n <= form.personal_rating ? 'bg-amber-500 text-white' : 'bg-vault-700 text-vault-400 hover:bg-vault-600'">{{
                                    n }}</button>
                            <span class="text-vault-400 text-sm ml-2">/ 10</span>
                        </div>
                    </div>

                    <!-- Purchase Details -->
                    <div class="border-t border-vault-700 pt-5">
                        <h3 class="text-sm font-semibold text-vault-200 mb-3">Purchase Details</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">Purchase
                                    Date</label><input v-model="form.purchase_date" type="date"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm" />
                            </div>
                            <div><label class="block text-sm font-medium text-vault-200 mb-1.5">Price Paid</label><input
                                    v-model="form.purchase_price" type="number" step="0.01" min="0"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="29.99" /></div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Condition</label>
                                <select v-model="form.condition"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                    <option value="mint">Mint</option>
                                    <option value="near_mint">Near Mint</option>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="poor">Poor</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-1.5">Status</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="s in ['owned', 'wishlist', 'borrowed', 'sold', 'lost']" :key="s"
                                type="button" @click="form.status = s"
                                class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all"
                                :class="form.status === s ? 'bg-amber-500 text-white' : 'bg-vault-700 text-vault-300 hover:bg-vault-600'">{{
                                    s.charAt(0).toUpperCase() + s.slice(1) }}</button>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-1.5">Notes</label>
                        <textarea v-model="form.notes" rows="3"
                            class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm resize-none"
                            placeholder="Any additional notes..."></textarea>
                    </div>

                    <!-- Validation errors -->
                    <div v-if="validationErrors.length"
                        class="mx-6 mt-4 p-4 bg-rose-500/15 border border-rose-500/30 rounded-xl">
                        <p class="text-rose-400 text-sm font-semibold mb-2">{{ validationErrors.length }} error{{
                            validationErrors.length > 1 ? 's' : '' }}:</p>
                        <ul class="space-y-1">
                            <li v-for="(err, i) in validationErrors" :key="i"
                                class="text-rose-300 text-sm flex items-start gap-2">
                                <span class="text-rose-500 mt-0.5">&#8226;</span>
                                <span><span class="font-medium text-rose-400">{{ err.field }}</span>: {{ err.message
                                    }}</span>
                            </li>
                        </ul>
                    </div>
                    <div v-else-if="serverError"
                        class="mx-6 mt-4 p-3 bg-rose-500/15 border border-rose-500/30 rounded-xl">
                        <p class="text-rose-400 text-sm font-medium">{{ serverError }}</p>
                    </div>
                    <!-- Submit -->
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="emit('close')"
                            class="px-5 py-2.5 rounded-xl text-sm font-medium text-vault-300 hover:text-white hover:bg-vault-700 transition-all">Cancel</button>
                        <button type="submit" :disabled="submitting"
                            class="px-6 py-2.5 bg-gradient-to-r from-amber-500 to-ember-500 text-white font-semibold rounded-xl hover:from-amber-400 hover:to-ember-400 transition-all disabled:opacity-50 disabled:cursor-not-allowed text-sm">{{
                                submitLabel() }}</button>
                    </div>
                </form>
            </div>
        </div>
    </transition>

    <!-- Barcode Scanner Modal -->
    <BarcodeScanner v-if="showScanner" @scanned="handleBarcodeScanned" @close="showScanner = false" />

    <!-- TMDB Search Modal -->
    <TmdbSearchModal :open="showTmdbSearch" :type="type" @close="showTmdbSearch = false" @selected="applyTmdbData" />

    <!-- RAWG Search Modal -->
    <RawgSearchModal :open="showRawgSearch" @close="showRawgSearch = false" @selected="applyRawgData" />

    <!-- Google Books Search Modal -->
    <GoogleBooksSearchModal :open="showGoogleBooksSearch" @close="showGoogleBooksSearch = false"
        @selected="applyGoogleBooksData" />

    <!-- Discogs Search Modal -->
    <DiscogsSearchModal :open="showDiscogsSearch" @close="showDiscogsSearch = false" @selected="applyDiscogsData" />
</template>