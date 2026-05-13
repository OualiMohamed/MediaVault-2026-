<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'

const route = useRoute()
const router = useRouter()

const franchise = ref(null)
const loading = ref(true)

const typeConfig = {
    movie: { label: 'Movie', icon: '🎬', color: 'amber', path: '/movies' },
    book: { label: 'Book', icon: '📖', color: 'emerald', path: '/books' },
    game: { label: 'Game', icon: '🎮', color: 'sky', path: '/games' },
    tv_show: { label: 'TV Show', icon: '📺', color: 'rose', path: '/tv-shows' },
    music: { label: 'Album', icon: '🎵', color: 'violet', path: '/music' },
}

const colorMap = {
    amber: 'border-amber-500/30 bg-amber-500/10',
    emerald: 'border-emerald-500/30 bg-emerald-500/10',
    sky: 'border-sky-500/30 bg-sky-500/10',
    rose: 'border-rose-500/30 bg-rose-500/10',
    violet: 'border-violet-500/30 bg-violet-500/10',
}

const textColorMap = {
    amber: 'text-amber-400',
    emerald: 'text-emerald-400',
    sky: 'text-sky-400',
    rose: 'text-rose-400',
    violet: 'text-violet-400',
}

async function fetchFranchise() {
    loading.value = true
    try {
        const { data } = await api.get(`/franchises/${route.params.id}`)
        franchise.value = data
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

function goToItem(item) {
    const cfg = typeConfig[item.media_type]
    router.push(`${cfg.path}/${item.id}`)
}

function coverUrl(item) {
    return item.cover_image ? '/storage/' + item.cover_image : null
}

onMounted(fetchFranchise)
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <div v-if="loading" class="flex justify-center py-20">
            <div class="w-8 h-8 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <template v-else-if="franchise">
            <!-- Header -->
            <div class="flex items-center gap-6 mb-10">
                <div class="w-32 h-32 rounded-2xl bg-vault-800 border border-vault-600 overflow-hidden flex-shrink-0">
                    <img v-if="franchise.cover_image" :src="'/storage/' + franchise.cover_image"
                        class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full flex items-center justify-center text-4xl text-vault-600">🎞️</div>
                </div>
                <div>
                    <h1 class="text-3xl sm:text-4xl font-bold text-white tracking-tight">{{ franchise.name }}</h1>
                    <p class="text-vault-400 mt-2">
                        {{ franchise.items.length }} item{{ franchise.items.length !== 1 ? 's' : '' }} across
                        {{new Set(franchise.items.map(i => i.media_type)).size}} media types
                    </p>
                </div>
            </div>

            <!-- Items timeline -->
            <div v-if="franchise.items.length" class="space-y-4">
                <div v-for="(item, idx) in franchise.items" :key="item.id"
                    class="flex items-center gap-4 p-4 bg-vault-800 border border-vault-700 rounded-xl cursor-pointer hover:border-vault-500 transition-all"
                    @click="goToItem(item)">

                    <!-- Position number -->
                    <div class="w-10 h-10 rounded-full bg-vault-700 flex items-center justify-center flex-shrink-0">
                        <span class="text-vault-300 font-bold text-sm">{{ item.detail?.franchise_position || idx + 1
                            }}</span>
                    </div>

                    <!-- Cover -->
                    <div class="w-14 h-20 rounded-lg bg-vault-700 overflow-hidden flex-shrink-0">
                        <img v-if="coverUrl(item)" :src="coverUrl(item)" class="w-full h-full object-cover"
                            loading="lazy" />
                        <div v-else class="w-full h-full flex items-center justify-center text-xl text-vault-600">
                            {{ typeConfig[item.media_type]?.icon }}
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <p class="text-white font-semibold truncate">{{ item.title }}</p>
                        <p class="text-vault-500 text-sm mt-0.5">
                            <template v-if="item.detail?.director">{{ item.detail.director }}</template>
                            <template v-else-if="item.detail?.author">{{ item.detail.author }}</template>
                            <template v-else-if="item.detail?.artist">{{ item.detail.artist }}</template>
                            <template v-else-if="item.detail?.platform">{{ item.detail.platform }}</template>
                            <template v-else-if="item.detail?.network">{{ item.detail.network }}</template>
                            <template v-else>&nbsp;</template>
                        </p>
                    </div>

                    <!-- Type badge -->
                    <span class="px-2.5 py-1 rounded-lg text-xs font-semibold border flex-shrink-0"
                        :class="[colorMap[typeConfig[item.media_type]?.color], textColorMap[typeConfig[item.media_type]?.color]]">
                        {{ typeConfig[item.media_type]?.icon }} {{ typeConfig[item.media_type]?.label }}
                    </span>

                    <!-- Year -->
                    <span v-if="item.detail?.release_year"
                        class="text-vault-500 text-sm font-mono w-12 text-right flex-shrink-0">
                        {{ item.detail.release_year }}
                    </span>

                    <!-- Arrow -->
                    <svg class="w-4 h-4 text-vault-600 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </div>

            <!-- Empty -->
            <div v-else class="text-center py-20">
                <p class="text-5xl mb-4">🎞️</p>
                <h3 class="text-xl font-semibold text-white mb-2">No items yet</h3>
                <p class="text-vault-400">Add movies, books, or games to this franchise.</p>
            </div>
        </template>
    </div>
</template>