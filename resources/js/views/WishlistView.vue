<!-- resources/js/views/WishlistView.vue -->
<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'

const items = ref([])
const loading = ref(false)

async function fetchWishlist() {
    loading.value = true
    try {
        const types = ['movie', 'book', 'game', 'music']
        const results = await Promise.all(
            types.map(type =>
                api.get(`/collection/${type}`, { params: { status: 'wishlist', per_page: 100 } })
                    .then(res => res.data.data.map(item => ({ ...item, _type: type })))
            )
        )
        items.value = results.flat()
    } catch (err) {
        console.error(err)
    } finally {
        loading.value = false
    }
}

onMounted(fetchWishlist)
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white tracking-tight">Wishlist</h1>
            <p class="text-vault-300 mt-1">{{ items.length }} item{{ items.length !== 1 ? 's' : '' }} on your wishlist
            </p>
        </div>

        <div v-if="loading" class="flex justify-center py-20">
            <div class="w-8 h-8 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else-if="items.length === 0" class="text-center py-20">
            <p class="text-5xl mb-4">&#10084;</p>
            <h3 class="text-xl font-semibold text-white mb-2">Your wishlist is empty</h3>
            <p class="text-vault-400">Add items with "Wishlist" status from any collection page.</p>
        </div>

        <div v-else class="space-y-3">
            <div v-for="item in items" :key="item.id"
                class="flex items-center gap-4 bg-vault-800 border border-vault-700 rounded-xl p-4 hover:border-vault-500 transition-all">
                <div class="w-12 h-16 rounded-lg bg-vault-700 overflow-hidden flex-shrink-0">
                    <img v-if="item.cover_image" :src="'/storage/' + item.cover_image"
                        class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full flex items-center justify-center text-vault-500 text-lg">
                        {{ item._type === 'movie' ? '\u{1F3AC}' : item._type === 'book' ? '\u{1F4D6}' : item._type ===
                            'game' ? '\u{1F3AE}' : '\u{1F3B5}' }}
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white font-medium truncate">{{ item.title }}</p>
                    <p class="text-vault-400 text-sm">
                        {{ item.details?.format || item.details?.platform || '' }}
                        <span v-if="item.details?.director"> &middot; {{ item.details.director }}</span>
                        <span v-if="item.details?.author"> &middot; {{ item.details.author }}</span>
                        <span v-if="item.details?.artist"> &middot; {{ item.details.artist }}</span>
                    </p>
                </div>
                <router-link :to="'/' + item._type + 's'"
                    class="text-xs font-medium px-3 py-1.5 rounded-lg bg-vault-700 text-vault-300 hover:text-white hover:bg-vault-600 transition-all flex-shrink-0">
                    View in {{ item._type }}s
                </router-link>
            </div>
        </div>
    </div>
</template>