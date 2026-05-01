<script setup>
import { onMounted, computed } from 'vue'
import { useDashboardStore } from '../stores/dashboard'
import FormatBreakdown from '../components/FormatBreakdown.vue'
import RecentItems from '../components/RecentItems.vue'

const dashboard = useDashboardStore()
onMounted(() => dashboard.fetchStats())

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
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <FormatBreakdown title="Movies by Format" :data="dashboard.stats.movies_by_format" color="amber" />
                <FormatBreakdown title="Games by Platform" :data="dashboard.stats.games_by_platform" color="sky" />
                <FormatBreakdown title="Music by Format" :data="dashboard.stats.music_by_format" color="violet" />
                <RecentItems :items="dashboard.stats.recent_additions" />
            </div>
        </template>
    </div>
</template>