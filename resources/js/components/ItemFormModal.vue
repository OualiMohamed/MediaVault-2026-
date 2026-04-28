<!-- resources/js/components/ItemFormModal.vue -->
<script setup>
import { ref, reactive, watch, computed } from 'vue'
import { useCollectionStore } from '../stores/collection'

const props = defineProps({
    type: { type: String, required: true },
    item: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])
const store = useCollectionStore()
const submitting = ref(false)
const errors = ref({})
const coverPreview = ref(null)

// données du formulaire
const form = reactive({
    title: '',
    cover_image: null,
    purchase_date: '',
    purchase_price: '',
    condition: 'near_mint',
    status: 'owned',
    notes: '',
    // Movie Champ 
    format: 'Blu-ray',
    runtime_minutes: '',
    director: '',
    genre: '',
    personal_rating: '',
    release_year: '',
    imdb_id: '',
    // Book Champ 
    author: '',
    isbn: '',
    page_count: '',
    publisher: '',
    read: false,
    date_finished: '',
    // Game Champ 
    platform: 'PS5',
    completed: false,
    completion_date: '',
    // Music Champ 
    artist: '',
    label: '',
    track_count: '',
    vinyl_speed: '',
})

const isEditing = computed(() => !!props.item)

// Options de formatage
const formatOptions = computed(() => {
    const map = {
        movie: ['DVD', 'Blu-ray', '4K UHD', 'Digital', 'VHS'],
        game: ['Physical', 'Digital'],
        music: ['CD', 'Vinyl', 'Digital', 'Cassette', '8-Track'],
    }
    return map[props.type] || []
})

const platformOptions = [
    'PS5', 'PS4', 'PS3', 'PS Vita', 'Switch', 'Wii U', 'Wii',
    'Xbox Series X', 'Xbox One', 'PC', 'Steam', 'Other',
]

// Remplissez le formulaire lors de la modification
watch(() => props.item, (item) => {
    if (!item) return
    Object.assign(form, {
        title: item.title,
        cover_image: null,
        purchase_date: item.purchase_date || '',
        purchase_price: item.purchase_price || '',
        condition: item.condition || 'near_mint',
        status: item.status || 'owned',
        notes: item.notes || '',
    })
    if (item.details) {
        Object.keys(item.details).forEach(key => {
            if (key in form) form[key] = item.details[key]
        })
    }
}, { immediate: true })

// Définir le format par défaut selon le type
watch(() => props.type, (type) => {
    if (!isEditing.value) {
        if (type === 'movie') form.format = 'Blu-ray'
        if (type === 'game') form.platform = 'PS5'
        if (type === 'music') form.format = 'CD'
    }
}, { immediate: true })

function handleCoverChange(e) {
    const file = e.target.files[0]
    if (file) {
        form.cover_image = file
        coverPreview.value = URL.createObjectURL(file)
    }
}

async function handleSubmit() {
    errors.value = {}
    submitting.value = true

    try {
        const formData = new FormData()
        // N'envoyez que les champs non vides
        const fields = [
            'title', 'purchase_date', 'purchase_price', 'condition', 'status', 'notes',
            'format', 'runtime_minutes', 'director', 'genre', 'personal_rating', 'release_year', 'imdb_id',
            'author', 'isbn', 'page_count', 'publisher', 'read', 'date_finished',
            'platform', 'completed', 'completion_date',
            'artist', 'label', 'track_count', 'vinyl_speed',
        ]

        // N'envoyez que les champs pertinents en fonction du type.
        const baseFields = ['title', 'purchase_date', 'purchase_price', 'condition', 'status', 'notes']
        const typeFields = {
            movie: ['format', 'runtime_minutes', 'director', 'genre', 'personal_rating', 'release_year', 'imdb_id'],
            book: ['author', 'isbn', 'page_count', 'publisher', 'genre', 'personal_rating', 'release_year', 'read', 'date_finished'],
            game: ['platform', 'format', 'genre', 'publisher', 'personal_rating', 'release_year', 'completed', 'completion_date'],
            music: ['format', 'artist', 'genre', 'label', 'track_count', 'personal_rating', 'release_year', 'vinyl_speed'],
        }

        const activeFields = [...baseFields, ...(typeFields[props.type] || [])]
        activeFields.forEach(field => {
            if (form[field] !== '' && form[field] !== null && form[field] !== undefined) {
                formData.append(field, form[field])
            }
        })

        if (form.cover_image instanceof File) {
            formData.append('cover_image', form.cover_image)
        }

        if (isEditing.value) {
            await store.updateItem(props.type, props.item.id, formData)
        } else {
            await store.createItem(props.type, formData)
        }

        emit('saved')
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors
        } else {
            console.error('Save failed:', err)
        }
    } finally {
        submitting.value = false
    }
}

function fieldError(field) {
    return errors.value[field] ? errors.value[field][0] : ''
}
</script>

<template>
    <transition name="modal">
        <div class="fixed inset-0 z-[60] flex items-start justify-center pt-10 sm:pt-20 px-4 modal-backdrop"
            @click.self="emit('close')">
            <div
                class="bg-vault-800 border border-vault-600 rounded-2xl w-full max-w-2xl max-h-[85vh] overflow-y-auto shadow-2xl">
                <!-- En-tête de la modale -->
                <div
                    class="sticky top-0 bg-vault-800 border-b border-vault-700 px-6 py-4 flex items-center justify-between z-10 rounded-t-2xl">
                    <h2 class="text-lg font-bold text-white">
                        {{ isEditing ? 'Edit' : 'Add' }}
                        {{
                            type === 'movie' ? 'Movie' :
                                type === 'book' ? 'Book' :
                                    type === 'game' ? 'Game' : 'Album'
                        }}
                    </h2>
                    <button @click="emit('close')"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-vault-400 hover:text-white hover:bg-vault-700 transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- contenu du formulaire -->
                <form @submit.prevent="handleSubmit" class="p-6 space-y-5">
                    <!-- Téléchargement de la couverture -->
                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-2">Cover Image</label>
                        <div class="flex items-center gap-4">
                            <div
                                class="w-24 h-36 rounded-xl bg-vault-700 overflow-hidden flex-shrink-0 border border-vault-600">
                                <img v-if="coverPreview" :src="coverPreview" class="w-full h-full object-cover" />
                                <img v-else-if="item?.cover_image" :src="'/storage/' + item.cover_image"
                                    class="w-full h-full object-cover" />
                                <div v-else
                                    class="w-full h-full flex items-center justify-center text-vault-500 text-2xl">
                                    {{ type === 'movie' ? '\u{1F3AC}' : type === 'book' ? '\u{1F4D6}' : type === 'game'
                                    ? '\u{1F3AE}' : '\u{1F3B5}' }}
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

                    <!-- Titre (tous types) -->
                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-1.5">Title *</label>
                        <input v-model="form.title" type="text"
                            class="w-full px-4 py-2.5 bg-vault-700 border rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 transition-all text-sm"
                            :class="fieldError('title') ? 'border-rose-500' : 'border-vault-600'"
                            placeholder="Enter title..." />
                        <p v-if="fieldError('title')" class="text-rose-500 text-xs mt-1">{{ fieldError('title') }}</p>
                    </div>

                    <!-- Champs spécifiques au type -->
                    <!-- Movie -->
                    <template v-if="type === 'movie'">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Format *</label>
                                <select v-model="form.format"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                    <option v-for="f in formatOptions" :key="f" :value="f">{{ f }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Director</label>
                                <input v-model="form.director" type="text"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="Director name" />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Runtime (min)</label>
                                <input v-model="form.runtime_minutes" type="number" min="1"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="120" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Year</label>
                                <input v-model="form.release_year" type="number" min="1888"
                                    :max="new Date().getFullYear() + 2"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="2024" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">IMDb ID</label>
                                <input v-model="form.imdb_id" type="text"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="tt1234567" />
                            </div>
                        </div>
                    </template>

                    <!-- Book -->
                    <template v-if="type === 'book'">
                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Author *</label>
                            <input v-model="form.author" type="text"
                                class="w-full px-4 py-2.5 bg-vault-700 border rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                :class="fieldError('author') ? 'border-rose-500' : 'border-vault-600'"
                                placeholder="Author name" />
                            <p v-if="fieldError('author')" class="text-rose-500 text-xs mt-1">{{ fieldError('author') }}
                            </p>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">ISBN</label>
                                <input v-model="form.isbn" type="text"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="978-..." />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Pages</label>
                                <input v-model="form.page_count" type="number" min="1"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="320" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Year</label>
                                <input v-model="form.release_year" type="number"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="2024" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Publisher</label>
                                <input v-model="form.publisher" type="text"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="Publisher" />
                            </div>
                            <div class="flex items-end gap-4">
                                <label class="flex items-center gap-2 cursor-pointer pb-2.5">
                                    <input v-model="form.read" type="checkbox"
                                        class="w-4 h-4 rounded bg-vault-700 border-vault-600 text-amber-500 focus:ring-amber-500/50" />
                                    <span class="text-sm text-vault-200">Mark as Read</span>
                                </label>
                            </div>
                        </div>
                        <div v-if="form.read">
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Date Finished</label>
                            <input v-model="form.date_finished" type="date"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm" />
                        </div>
                    </template>

                    <!-- Game -->
                    <template v-if="type === 'game'">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Platform *</label>
                                <select v-model="form.platform"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                    <option v-for="p in platformOptions" :key="p" :value="p">{{ p }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Format *</label>
                                <select v-model="form.format"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                    <option v-for="f in formatOptions" :key="f" :value="f">{{ f }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Year</label>
                                <input v-model="form.release_year" type="number"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="2024" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Publisher</label>
                                <input v-model="form.publisher" type="text"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="Publisher" />
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 cursor-pointer pb-2.5">
                                    <input v-model="form.completed" type="checkbox"
                                        class="w-4 h-4 rounded bg-vault-700 border-vault-600 text-amber-500 focus:ring-amber-500/50" />
                                    <span class="text-sm text-vault-200">Completed</span>
                                </label>
                            </div>
                        </div>
                        <div v-if="form.completed">
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Completion Date</label>
                            <input v-model="form.completion_date" type="date"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm" />
                        </div>
                    </template>

                    <!-- Music -->
                    <template v-if="type === 'music'">
                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Artist *</label>
                            <input v-model="form.artist" type="text"
                                class="w-full px-4 py-2.5 bg-vault-700 border rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                :class="fieldError('artist') ? 'border-rose-500' : 'border-vault-600'"
                                placeholder="Artist name" />
                            <p v-if="fieldError('artist')" class="text-rose-500 text-xs mt-1">{{ fieldError('artist') }}
                            </p>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Format *</label>
                                <select v-model="form.format"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                    <option v-for="f in formatOptions" :key="f" :value="f">{{ f }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Tracks</label>
                                <input v-model="form.track_count" type="number" min="1"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="12" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Year</label>
                                <input v-model="form.release_year" type="number"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="2024" />
                            </div>
                            <div v-if="form.format === 'Vinyl'">
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Speed (RPM)</label>
                                <select v-model="form.vinyl_speed"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                                    <option value="">Select</option>
                                    <option value="33">33 RPM</option>
                                    <option value="45">45 RPM</option>
                                    <option value="78">78 RPM</option>
                                </select>
                            </div>
                            <div v-else>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Label</label>
                                <input v-model="form.label" type="text"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="Label" />
                            </div>
                        </div>
                        <div v-if="form.format === 'Vinyl' && !form.vinyl_speed ? true : false">
                            <label class="block text-sm font-medium text-vault-200 mb-1.5">Label</label>
                            <input v-model="form.label" type="text"
                                class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                placeholder="Label" />
                        </div>
                    </template>

                    <!-- Champs communs: Genre -->
                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-1.5">Genre</label>
                        <input v-model="form.genre" type="text"
                            class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                            placeholder="e.g. Action, Sci-Fi, Rock" />
                    </div>

                    <!-- Champs communs: Personal Rating -->
                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-2">Personal Rating</label>
                        <div class="flex items-center gap-1">
                            <button v-for="n in 10" :key="n" type="button"
                                @click="form.personal_rating = form.personal_rating === n ? '' : n"
                                class="star w-7 h-7 rounded flex items-center justify-center text-sm font-bold transition-all"
                                :class="n <= form.personal_rating
                                    ? 'bg-amber-500 text-white'
                                    : 'bg-vault-700 text-vault-400 hover:bg-vault-600'">
                                {{ n }}
                            </button>
                            <span class="text-vault-400 text-sm ml-2">/ 10</span>
                        </div>
                    </div>

                    <!-- Champs communs: Purchase Info -->
                    <div class="border-t border-vault-700 pt-5">
                        <h3 class="text-sm font-semibold text-vault-200 mb-3">Purchase Details</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Purchase Date</label>
                                <input v-model="form.purchase_date" type="date"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-vault-200 mb-1.5">Price Paid</label>
                                <input v-model="form.purchase_price" type="number" step="0.01" min="0"
                                    class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm"
                                    placeholder="29.99" />
                            </div>
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

                    <!-- Champs communs: Status -->
                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-1.5">Status</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="s in ['owned', 'wishlist', 'borrowed', 'sold', 'lost']" :key="s"
                                type="button" @click="form.status = s"
                                class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all" :class="form.status === s
                                    ? 'bg-amber-500 text-white'
                                    : 'bg-vault-700 text-vault-300 hover:bg-vault-600'">
                                {{ s.charAt(0).toUpperCase() + s.slice(1) }}
                            </button>
                        </div>
                    </div>

                    <!-- Champs communs: Notes -->
                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-1.5">Notes</label>
                        <textarea v-model="form.notes" rows="3"
                            class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm resize-none"
                            placeholder="Any additional notes..."></textarea>
                    </div>

                    <!-- Bouton de soumission -->
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="emit('close')"
                            class="px-5 py-2.5 rounded-xl text-sm font-medium text-vault-300 hover:text-white hover:bg-vault-700 transition-all">
                            Cancel
                        </button>
                        <button type="submit" :disabled="submitting"
                            class="px-6 py-2.5 bg-gradient-to-r from-amber-500 to-ember-500 text-white font-semibold rounded-xl hover:from-amber-400 hover:to-ember-400 transition-all disabled:opacity-50 disabled:cursor-not-allowed text-sm">
                            {{ submitting ? 'Saving...' : (isEditing ? 'Update' : 'Add to Collection') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </transition>
</template>