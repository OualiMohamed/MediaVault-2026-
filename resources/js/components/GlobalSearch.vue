<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'

const router = useRouter()
const query = ref('')
const results = ref([])
const isOpen = ref(false)
const loading = ref(false)

let timeout = null

const typeConfig = {
    movie: { icon: '🎬', label: 'Movie' },
    book: { icon: '📖', label: 'Book' },
    game: { icon: '🎮', label: 'Game' },
    music: { icon: '🎵', label: 'Album' },
    tv_show: { icon: '📺', label: 'TV Show' },
}

function onInput() {
    clearTimeout(timeout)
    if (query.value.length < 2) {
        results.value = []
        isOpen.value = false
        return
    }
    timeout = setTimeout(doSearch, 300)
}

async function doSearch() {
    loading.value = true
    isOpen.value = true
    try {
        const { data } = await api.get('/search', { params: { q: query.value } })
        results.value = data
    } catch (e) {
        results.value = []
    } finally {
        loading.value = false
    }
}

function goTo(url) {
    close()
    router.push(url)
}

function close() {
    isOpen.value = false
    query.value = ''
    results.value = []
}

// Close dropdown when navigating away
watch(() => router.currentRoute.value.fullPath, () => close())
</script>

<template>
    <div class="relative">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-vault-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2">
                    <circle cx="11" cy="11" r="8" />
                    <path d="M21 21l-4.35-4.35" />
                </svg>
            </div>
            <input v-model="query" @input="onInput" @keydown.escape="close" type="text"
                class="w-48 sm:w-64 lg:w-80 pl-10 pr-4 py-2 bg-vault-800 border border-vault-600 rounded-xl text-white placeholder-vault-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all text-sm"
                placeholder="Search everything..." autocomplete="off" />
            <div v-if="loading" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <div class="w-4 h-4 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
            </div>
        </div>

        <!-- Dropdown -->
        <transition name="search-dropdown">
            <div v-if="isOpen && (results.length > 0 || loading)"
                class="absolute top-full mt-2 left-0 sm:left-0 w-[400px] bg-vault-800 border border-vault-600 rounded-xl shadow-2xl shadow-black/50 overflow-hidden z-50 max-h-[70vh] overflow-y-auto">

                <div v-if="loading" class="p-6 flex justify-center">
                    <div class="w-6 h-6 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
                </div>

                <template v-else>
                    <div class="px-4 py-2 border-b border-vault-700 flex items-center justify-between bg-vault-900/50">
                        <span class="text-vault-400 text-xs font-medium">{{ results.length }} results for "{{ query
                            }}"</span>
                        <button @click="close"
                            class="text-vault-500 hover:text-white text-xs transition-colors">Close</button>
                    </div>

                    <div>
                        <button v-for="item in results" :key="item.id + '-' + item.type" @click="goTo(item.url)"
                            class="w-full flex items-center gap-3 px-4 py-3 hover:bg-vault-700/50 transition-colors text-left group">

                            <div
                                class="w-8 h-8 rounded-lg bg-vault-700 flex items-center justify-center text-sm flex-shrink-0">
                                {{ typeConfig[item.type]?.icon || '📦' }}
                            </div>

                            <div
                                class="w-10 h-10 rounded-md bg-vault-700 overflow-hidden flex-shrink-0 border border-vault-600">
                                <img v-if="item.cover_image" :src="item.cover_image"
                                    class="w-full h-full object-cover" />
                                <div v-else
                                    class="w-full h-full flex items-center justify-center text-vault-600 text-xs">
                                    {{ typeConfig[item.type]?.icon || '?' }}
                                </div>
                            </div>

                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-white text-sm font-medium truncate group-hover:text-amber-400 transition-colors">
                                    {{ item.title }}</p>
                                <p class="text-vault-500 text-xs truncate">
                                    <span v-if="item.subtitle">{{ item.subtitle }} · </span>
                                    <span>{{ typeConfig[item.type]?.label }}</span>
                                </p>
                            </div>

                            <svg class="w-4 h-4 text-vault-600 group-hover:text-vault-300 transition-colors flex-shrink-0"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </template>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.search-dropdown-enter-active,
.search-dropdown-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
    transform-origin: top;
}

.search-dropdown-enter-from,
.search-dropdown-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>