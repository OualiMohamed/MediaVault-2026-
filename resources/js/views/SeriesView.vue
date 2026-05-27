<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'
import EmptyState from '../components/EmptyState.vue'

const route = useRoute()
const router = useRouter()

const series = ref(null)
const loading = ref(true)

const statusColors = {
    not_started: 'text-vault-400 bg-vault-600/30',
    tbr: 'text-sky-400 bg-sky-500/15',
    reading: 'text-amber-400 bg-amber-500/15',
    read: 'text-emerald-400 bg-emerald-500/15',
}

const statusLabels = {
    not_started: 'Not Started',
    tbr: 'TBR',
    reading: 'Reading',
    read: 'Read',
}

async function fetchSeries() {
    loading.value = true
    try {
        const { data } = await api.get(`/book-series/${route.params.id}`)
        series.value = data
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

function goToBook(book) {
    router.push(`/books/${book.id}`)
}

function coverUrl(book) {
    return book.cover_image ? '/storage/' + book.cover_image : null
}

onMounted(fetchSeries)
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <div v-if="loading" class="flex justify-center py-20">
            <div class="w-8 h-8 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <template v-else-if="series">
            <!-- Header -->
            <div class="flex items-center gap-6 mb-10">
                <div class="w-32 h-32 rounded-2xl bg-vault-800 border border-vault-600 overflow-hidden flex-shrink-0">
                    <img v-if="series.books.length && coverUrl(series.books[0])" :src="coverUrl(series.books[0])"
                        class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full flex items-center justify-center text-4xl text-vault-600">📖</div>
                </div>
                <div>
                    <h1 class="text-3xl sm:text-4xl font-bold text-white tracking-tight">{{ series.name }}</h1>
                    <p class="text-vault-400 mt-2">
                        {{ series.books.length }} book{{ series.books.length !== 1 ? 's' : '' }} in series
                    </p>
                </div>
            </div>

            <!-- Books list -->
            <div v-if="series.books.length" class="space-y-3">
                <div v-for="book in series.books" :key="book.id"
                    class="flex items-center gap-4 p-4 bg-vault-800 border border-vault-700 rounded-xl cursor-pointer hover:border-vault-500 transition-all"
                    @click="goToBook(book)">

                    <!-- Position -->
                    <div class="w-10 h-10 rounded-full bg-vault-700 flex items-center justify-center flex-shrink-0">
                        <span class="text-vault-300 font-bold text-sm">{{ book.series_position || '?' }}</span>
                    </div>

                    <!-- Cover -->
                    <div class="w-12 h-16 rounded-lg bg-vault-700 overflow-hidden flex-shrink-0">
                        <img v-if="coverUrl(book)" :src="coverUrl(book)" class="w-full h-full object-cover"
                            loading="lazy" />
                        <div v-else class="w-full h-full flex items-center justify-center text-lg text-vault-600">📖
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <p class="text-white font-semibold truncate">{{ book.title }}</p>
                        <p class="text-vault-500 text-sm mt-0.5">
                            <template v-if="book.author">{{ book.author }}</template>
                            <template v-if="book.author && book.edition"> &middot; </template>
                            <template v-if="book.edition">{{ book.edition }}</template>
                            <template v-if="(book.author || book.edition) && book.release_year"> &middot; </template>
                            <template v-if="book.release_year">{{ book.release_year }}</template>
                        </p>
                    </div>

                    <!-- Status badge -->
                    <span v-if="book.reading_status"
                        :class="['px-2.5 py-1 rounded-lg text-xs font-semibold flex-shrink-0', statusColors[book.reading_status] || '']">
                        {{ statusLabels[book.reading_status] || book.reading_status }}
                    </span>

                    <!-- Rating -->
                    <span v-if="book.personal_rating"
                        class="text-amber-400 text-sm font-bold w-8 text-right flex-shrink-0">
                        {{ book.personal_rating }}
                    </span>

                    <!-- Arrow -->
                    <svg class="w-4 h-4 text-vault-600 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </div>

            <!-- Empty -->
            <EmptyState v-if="!series.books.length" type="series" />
        </template>
    </div>
</template>