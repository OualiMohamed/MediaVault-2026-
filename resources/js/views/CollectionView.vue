<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useCollectionStore } from '../stores/collection'
import MediaCard from '../components/MediaCard.vue'
import ItemFormModal from '../components/ItemFormModal.vue'

const route = useRoute()
const store = useCollectionStore()

const type = computed(() => route.meta.type)
const search = ref('')
const filterFormat = ref('')
const filterStatus = ref('')
const filterPlatform = ref('')
const showForm = ref(false)
const editItem = ref(null)
const currentPage = ref(1)

const formatOptions = computed(() => {
    const map = {
        movie: ['DVD', 'Blu-ray', '4K UHD', 'Digital', 'VHS'],
        book: [],
        game: ['Physical', 'Digital'],
        music: ['CD', 'Vinyl', 'Digital', 'Cassette', '8-Track'],
    }
    return map[type.value] || []
})

const platformOptions = [
    'PS5', 'PS4', 'PS3', 'PS Vita', 'Switch', 'Wii U', 'Wii',
    'Xbox Series X', 'Xbox One', 'PC', 'Steam', 'Other',
]

const typeConfig = {
    movie: { label: 'Movies', icon: '\u{1F3AC}', color: 'amber' },
    book: { label: 'Books', icon: '\u{1F4D6}', color: 'emerald' },
    game: { label: 'Games', icon: '\u{1F3AE}', color: 'sky' },
    music: { label: 'Music', icon: '\u{1F3B5}', color: 'violet' },
}

const config = computed(() => typeConfig[type.value])

function loadItems() {
    const params = {
        page: currentPage.value,
        search: search.value || undefined,
        format: filterFormat.value || undefined,
        status: filterStatus.value || undefined,
        platform: filterPlatform.value || undefined,
    }
    store.fetchItems(type.value, params)
}

function handlePageChange(page) {
    currentPage.value = page
    loadItems()
}

function openAddForm() {
    editItem.value = null
    showForm.value = true
}

function openEditForm(item) {
    editItem.value = item
    showForm.value = true
}

function handleFormSaved() {
    showForm.value = false
    editItem.value = null
    loadItems()
}

onMounted(loadItems)

// Re-fetch when navigating between /movies, /books, /games, /music
watch(() => route.path, () => {
    search.value = ''
    filterFormat.value = ''
    filterStatus.value = ''
    filterPlatform.value = ''
    currentPage.value = 1
    loadItems()
})

watch([search, filterFormat, filterStatus, filterPlatform], () => {
    currentPage.value = 1
    loadItems()
})
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                    <span class="text-3xl">{{ config.icon }}</span>
                    {{ config.label }}
                </h1>
                <p class="text-vault-300 mt-1">
                    {{ store.pagination.total }} item{{ store.pagination.total !== 1 ? 's' : '' }} in collection
                </p>
            </div>
            <button @click="openAddForm"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-amber-500 to-ember-500 text-white font-semibold rounded-xl hover:from-amber-400 hover:to-ember-400 transition-all shadow-lg shadow-amber-500/20">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Add {{ config.label.slice(0, -1) }}
            </button>
        </div>

        <div class="flex flex-wrap gap-3 mb-6">
            <input v-model="search" type="text" :placeholder="`Search ${config.label.toLowerCase()}...`"
                class="flex-1 min-w-[200px] px-4 py-2 bg-vault-800 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all text-sm" />
            <select v-if="formatOptions.length" v-model="filterFormat"
                class="px-4 py-2 bg-vault-800 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                <option value="">All Formats</option>
                <option v-for="f in formatOptions" :key="f" :value="f">{{ f }}</option>
            </select>
            <select v-if="type === 'game'" v-model="filterPlatform"
                class="px-4 py-2 bg-vault-800 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                <option value="">All Platforms</option>
                <option v-for="p in platformOptions" :key="p" :value="p">{{ p }}</option>
            </select>
            <select v-model="filterStatus"
                class="px-4 py-2 bg-vault-800 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                <option value="">All Status</option>
                <option value="owned">Owned</option>
                <option value="wishlist">Wishlist</option>
                <option value="borrowed">Borrowed</option>
                <option value="sold">Sold</option>
                <option value="lost">Lost</option>
            </select>
        </div>

        <div v-if="store.loading && store.items.length === 0" class="flex justify-center py-20">
            <div class="w-8 h-8 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else-if="store.items.length === 0" class="text-center py-20">
            <p class="text-5xl mb-4">{{ config.icon }}</p>
            <h3 class="text-xl font-semibold text-white mb-2">No {{ config.label.toLowerCase() }} yet</h3>
            <p class="text-vault-400">Click the button above to start building your collection.</p>
        </div>

        <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
            <MediaCard v-for="item in store.items" :key="item.id" :item="item" :type="type" @edit="openEditForm(item)"
                @deleted="loadItems" />
        </div>

        <div v-if="store.pagination.last_page > 1" class="flex items-center justify-center gap-2 mt-10">
            <button @click="handlePageChange(currentPage - 1)" :disabled="currentPage <= 1"
                class="px-3 py-2 bg-vault-800 border border-vault-600 rounded-lg text-vault-300 hover:text-white hover:border-vault-500 disabled:opacity-30 disabled:cursor-not-allowed transition-all text-sm">Previous</button>
            <template v-for="page in store.pagination.last_page" :key="page">
                <button v-if="page === 1 || page === store.pagination.last_page || Math.abs(page - currentPage) <= 2"
                    @click="handlePageChange(page)" :class="[
                        'px-3 py-2 rounded-lg text-sm font-medium transition-all',
                        page === currentPage
                            ? 'bg-amber-500 text-white'
                            : 'bg-vault-800 border border-vault-600 text-vault-300 hover:text-white hover:border-vault-500'
                    ]">{{ page }}</button>
                <span v-else-if="page === 2 || page === store.pagination.last_page - 1"
                    class="text-vault-500 px-1">...</span>
            </template>
            <button @click="handlePageChange(currentPage + 1)" :disabled="currentPage >= store.pagination.last_page"
                class="px-3 py-2 bg-vault-800 border border-vault-600 rounded-lg text-vault-300 hover:text-white hover:border-vault-500 disabled:opacity-30 disabled:cursor-not-allowed transition-all text-sm">Next</button>
        </div>

        <ItemFormModal v-if="showForm" :type="type" :item="editItem" @close="showForm = false"
            @saved="handleFormSaved" />
    </div>
</template>