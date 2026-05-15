<script setup>
const props = defineProps({
    items: { type: Array, default: () => [] },
})

const typeConfig = {
    movie: { icon: '🎬', color: 'text-amber-400', bg: 'bg-amber-500/15' },
    book: { icon: '📖', color: 'text-emerald-400', bg: 'bg-emerald-500/15' },
    game: { icon: '🎮', color: 'text-sky-400', bg: 'bg-sky-500/15' },
    tv_show: { icon: '📺', color: 'text-rose-400', bg: 'bg-rose-500/15' },
    music: { icon: '🎵', color: 'text-violet-400', bg: 'bg-violet-500/15' },
}

const typePaths = {
    movie: '/movies',
    book: '/books',
    game: '/games',
    tv_show: '/tv-shows',
    music: '/music',
}

function dueLabel(item) {
    if (item.is_overdue) return `${Math.abs(item.days_until_due)} day${Math.abs(item.days_until_due) !== 1 ? 's' : ''} overdue`
    if (item.days_until_due === 0) return 'Due today'
    if (item.days_until_due === 1) return 'Due tomorrow'
    return `${item.days_until_due} days left`
}

function dueColor(item) {
    if (item.is_overdue) return 'text-rose-400'
    if (item.days_until_due <= 2) return 'text-amber-400'
    return 'text-vault-400'
}
</script>

<template>
    <div class="bg-vault-800 border border-vault-700 rounded-2xl p-6">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-xl bg-vault-700 flex items-center justify-center">
                <svg class="w-5 h-5 text-vault-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
            </div>
            <div>
                <h2 class="text-white font-semibold">Loaned Out</h2>
                <p class="text-vault-400 text-sm">{{ items.length }} item{{ items.length !== 1 ? 's' : '' }} out</p>
            </div>
        </div>

        <div v-if="items.length === 0" class="text-center py-8">
            <p class="text-vault-500 text-sm">Nothing loaned out right now.</p>
        </div>

        <div v-else class="space-y-3">
            <a v-for="item in items" :key="item.id" :href="typePaths[item.type] + '/' + item.id"
                class="flex items-center gap-3 p-3 rounded-xl hover:bg-vault-700/50 transition-colors group">
                <div class="w-10 h-10 rounded-lg bg-vault-700 overflow-hidden flex-shrink-0 border border-vault-600">
                    <img v-if="item.cover_image" :src="'/storage/' + item.cover_image"
                        class="w-full h-full object-cover" loading="lazy" />
                    <div v-else class="w-full h-full flex items-center justify-center text-vault-600 text-sm">
                        {{ typeConfig[item.type]?.icon || '?' }}
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white text-sm font-medium truncate group-hover:text-amber-400 transition-colors">
                        {{ item.title }}
                    </p>
                    <p class="text-vault-400 text-xs mt-0.5">
                        <span class="text-vault-200">{{ item.borrowed_to }}</span>
                    </p>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="text-xs font-semibold" :class="dueColor(item)">
                        {{ dueLabel(item) }}
                    </span>
                </div>
            </a>
        </div>
    </div>
</template>