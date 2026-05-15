<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import ItemFormModal from '../components/ItemFormModal.vue'
import EmptyState from '../components/EmptyState.vue'

const router = useRouter()

const items = ref([])
const loading = ref(false)
const errorMsg = ref('')
const showForm = ref(false)
const formType = ref('movie')
const formItem = ref(null)

const typeConfig = {
    movie: { label: 'Movie', icon: '\u{1F3AC}', path: '/movies' },
    book: { label: 'Book', icon: '\u{1F4D6}', path: '/books' },
    game: { label: 'Game', icon: '\u{1F3AE}', path: '/games' },
    music: { label: 'Album', icon: '\u{1F3B5}', path: '/music' },
    tv_show: { label: 'TV Show', icon: '\u{1F4FA}', path: '/tv-shows' },
}


const typeColors = {
    movie: 'text-amber-400 bg-amber-500/15',
    book: 'text-emerald-400 bg-emerald-500/15',
    game: 'text-sky-400 bg-sky-500/15',
    music: 'text-violet-400 bg-violet-500/15',
}

async function fetchWishlist() {
    loading.value = true
    errorMsg.value = ''
    try {
        const types = ['movie', 'book', 'game', 'music', 'tv_show']
        const results = await Promise.all(
            types.map(async (type) => {
                try {
                    const res = await api.get(`/collection/${type}`, {
                        params: { status: 'wishlist', per_page: 100 },
                    })
                    return (res.data.data || []).map(item => ({ ...item, _type: type }))
                } catch (e) {
                    // If one type fails, return empty — don't break the whole page
                    console.warn(`Failed to fetch wishlist for ${type}:`, e)
                    return []
                }
            })
        )
        items.value = results.flat()
    } catch (err) {
        errorMsg.value = 'Failed to load wishlist. Please try again.'
        console.error(err)
    } finally {
        loading.value = false
    }
}

function openAddForm(type) {
    formType.value = type
    formItem.value = null
    showForm.value = true
}

function handleFormSaved() {
    showForm.value = false
    formItem.value = null
    fetchWishlist()
}

onMounted(fetchWishlist)
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                    <span class="text-3xl">&#10084;</span>
                    Wishlist
                </h1>
                <p class="text-vault-300 mt-1">
                    {{ items.length }} item{{ items.length !== 1 ? 's' : '' }} on your wishlist
                </p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-20">
            <div class="w-8 h-8 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Error -->
        <div v-else-if="errorMsg" class="text-center py-20">
            <p class="text-rose-400 mb-4">{{ errorMsg }}</p>
            <button @click="fetchWishlist"
                class="px-5 py-2.5 rounded-xl bg-vault-700 text-white text-sm font-medium hover:bg-vault-600 transition-all">
                Retry
            </button>
        </div>

        <!-- Empty state -->
        <EmptyState v-if="!items.length" type="wishlist" />

        <!-- Wishlist items -->
        <div v-else class="space-y-3">
            <div v-for="item in items" :key="item.id"
                class="flex items-center gap-4 bg-vault-800 border border-vault-700 rounded-xl p-4 hover:border-vault-500 transition-all">
                <!-- Cover -->
                <div class="w-12 h-16 rounded-lg bg-vault-700 overflow-hidden flex-shrink-0">
                    <img v-if="item.cover_image" :src="'/storage/' + item.cover_image" :alt="item.title"
                        class="block w-full h-full object-cover" />
                    <div v-else class="block w-full h-full flex items-center justify-center text-vault-500 text-lg">
                        {{ item._type === 'movie' ? '\u{1F3AC}' : item._type === 'book' ? '\u{1F4D6}' : item._type ===
                            'game' ? '\u{1F3AE}' : item._type === 'tv_show' ? '\u{1F4FA}' : '\u{1F3B5}' }}
                    </div>
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <p class="text-white font-medium truncate">{{ item.title }}</p>
                    <p class="text-vault-400 text-sm truncate">
                        {{ item.details?.format || item.details?.platform || '' }}
                        <span v-if="item.details?.director"> &middot; {{ item.details.director }}</span>
                        <span v-if="item.details?.author"> &middot; {{ item.details.author }}</span>
                        <span v-if="item.details?.artist"> &middot; {{ item.details.artist }}</span>
                        <span v-if="item.details?.network"> &middot; {{ item.details.network }}</span>
                    </p>
                </div>

                <!-- Type badge -->
                <span
                    :class="['text-[10px] font-semibold px-2 py-1 rounded-full flex-shrink-0', typeColors[item._type]]">
                    {{ typeConfig[item._type]?.label || item._type }}
                </span>

                <!-- View in collection link -->
                <router-link :to="typeConfig[item._type]?.path || '/'"
                    class="text-xs font-medium px-3 py-1.5 rounded-lg bg-vault-700 text-vault-300 hover:text-white hover:bg-vault-600 transition-all flex-shrink-0">
                    View
                </router-link>
            </div>
        </div>

        <!-- Add form modal — status defaults to 'wishlist' -->
        <ItemFormModal v-if="showForm" :type="formType" :item="formItem" @close="showForm = false"
            @saved="handleFormSaved" />
    </div>
</template>