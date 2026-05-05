<script setup>
import { onMounted, computed } from 'vue'
import { useDashboardStore } from '../stores/dashboard'
import { useExport } from '../composables/useExport'
import FormatBreakdown from '../components/FormatBreakdown.vue'
import RecentItems from '../components/RecentItems.vue'

const dashboard = useDashboardStore()
const { exporting, exportCollection } = useExport()

onMounted(() => dashboard.fetchStats())

const exportTypes = [
    { type: 'movie', label: 'Movies', icon: '\u{1F3AC}', format: 'CSV', color: 'amber' },
    { type: 'book', label: 'Books', icon: '\u{1F4D6}', format: 'CSV', color: 'emerald' },
    { type: 'game', label: 'Games', icon: '\u{1F3AE}', format: 'CSV', color: 'sky' },
    { type: 'tv_show', label: 'TV Shows', icon: '\u{1F4FA}', format: 'JSON', color: 'rose' },
    { type: 'music', label: 'Music', icon: '\u{1F3B5}', format: 'CSV', color: 'violet' },
]

const typeCards = computed(() => {
    if (!dashboard.stats) return []
    const s = dashboard.stats
    return [
        {
            label: 'Movies', count: s.by_type?.movie?.count ?? 0,
            value: s.by_type?.movie?.value ?? 0,
            icon: 'movie', color: 'amber', glow: 'stat-glow-amber', path: '/movies',
        },
        {
            label: 'Books', count: s.by_type?.book?.count ?? 0,
            value: s.by_type?.book?.value ?? 0,
            icon: 'book', color: 'emerald', glow: 'stat-glow-emerald', path: '/books',
            extra: `${s.books_read ?? 0} read`,
        },
        {
            label: 'Games', count: s.by_type?.game?.count ?? 0,
            value: s.by_type?.game?.value ?? 0,
            icon: 'gamepad', color: 'sky', glow: 'stat-glow-sky', path: '/games',
            extra: `${s.games_completed ?? 0} completed`,
        },
        {
            label: 'TV Shows', count: s.by_type?.tv_show?.count ?? 0,
            value: s.by_type?.tv_show?.value ?? 0,
            icon: 'tv', color: 'rose', glow: 'stat-glow-rose', path: '/tv-shows',
            extra: `${s.tv_shows_watching ?? 0} watching`,
        },
        {
            label: 'Music', count: s.by_type?.music?.count ?? 0,
            value: s.by_type?.music?.value ?? 0,
            icon: 'music', color: 'violet', glow: 'stat-glow-violet', path: '/music',
        },
    ]
})
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <!-- Header -->
        <div class="mb-10">
            <h1 class="text-3xl sm:text-4xl font-bold text-white tracking-tight">Your Collection</h1>
            <p class="text-vault-300 mt-2 text-lg">
                {{ dashboard.stats ? `${dashboard.stats.total_items} items across 5 libraries` : 'Loading...' }}
            </p>
        </div>

        <!-- Loading -->
        <div v-if="dashboard.loading" class="flex items-center justify-center py-20">
            <div class="w-8 h-8 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <template v-else-if="dashboard.stats">
            <!-- 5 Category Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-10">
                <router-link v-for="card in typeCards" :key="card.label" :to="card.path"
                    class="media-card bg-vault-800 border border-vault-700 rounded-2xl p-6 hover:border-vault-500 cursor-pointer group relative">
                    <div :class="[
                        'w-12 h-12 rounded-xl flex items-center justify-center text-xl mb-4',
                        card.color === 'amber' ? 'bg-amber-500/15 text-amber-400' : '',
                        card.color === 'emerald' ? 'bg-emerald-500/15 text-emerald-400' : '',
                        card.color === 'sky' ? 'bg-sky-500/15 text-sky-400' : '',
                        card.color === 'rose' ? 'bg-rose-500/15 text-rose-400' : '',
                        card.color === 'violet' ? 'bg-violet-500/15 text-violet-400' : '',
                    ]">
                        {{ card.icon === 'movie' ? '\u{1F3AC}' : card.icon === 'book' ? '\u{1F4D6}' : card.icon ===
                            'gamepad' ? '\u{1F3AE}' : card.icon === 'tv' ? '\u{1F4FA}' : '\u{1F3B5}' }}
                    </div>
                    <h3 class="text-white font-semibold text-lg">{{ card.label }}</h3>
                    <p class="text-2xl font-bold mt-1" :class="{
                        'text-amber-400': card.color === 'amber',
                        'text-emerald-400': card.color === 'emerald',
                        'text-sky-400': card.color === 'sky',
                        'text-rose-400': card.color === 'rose',
                        'text-violet-400': card.color === 'violet',
                    }">{{ card.count }}</p>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-vault-400 text-sm">
                            ${{ Number(card.value).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                        </span>
                        <span v-if="card.extra" class="text-vault-400 text-sm">{{ card.extra }}</span>
                    </div>
                    <svg class="w-5 h-5 text-vault-500 group-hover:text-vault-300 group-hover:translate-x-1 transition-all absolute top-6 right-6"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </router-link>
            </div>

            <!-- Bottom Row: Format Breakdowns + Recent Items -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10">
                <FormatBreakdown title="Movies by Format" :data="dashboard.stats.movies_by_format" color="amber" />
                <FormatBreakdown title="Games by Platform" :data="dashboard.stats.games_by_platform" color="sky" />
                <FormatBreakdown title="Music by Format" :data="dashboard.stats.music_by_format" color="violet" />
                <RecentItems :items="dashboard.stats.recent_additions" />
            </div>

            <!-- Export Section -->
            <div class="bg-vault-800 border border-vault-700 rounded-2xl p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 rounded-xl bg-vault-700 flex items-center justify-center">
                        <svg class="w-5 h-5 text-vault-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-white font-semibold">Export Collection</h2>
                        <p class="text-vault-400 text-sm">Download your data as CSV or JSON</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <button v-for="exp in exportTypes" :key="exp.type" @click="exportCollection(exp.type)"
                        :disabled="!!exporting"
                        class="flex items-center gap-3 px-4 py-3 bg-vault-700/50 border border-vault-600 rounded-xl hover:border-vault-500 hover:bg-vault-700 transition-all disabled:opacity-50 disabled:cursor-wait group text-left">
                        <span class="text-lg flex-shrink-0">{{ exp.icon }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-white text-sm font-medium truncate">{{ exp.label }}</p>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="text-[10px] font-mono font-semibold px-1.5 py-0.5 rounded"
                                    :class="exp.format === 'JSON' ? 'bg-amber-500/15 text-amber-400' : 'bg-vault-600 text-vault-300'">
                                    {{ exp.format }}
                                </span>
                                <template v-if="exporting === exp.type">
                                    <div
                                        class="w-3 h-3 border-2 border-current border-t-transparent rounded-full animate-spin text-vault-400">
                                    </div>
                                </template>
                                <template v-else>
                                    <svg class="w-3 h-3 text-vault-500 group-hover:text-vault-300 transition-colors"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </template>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>