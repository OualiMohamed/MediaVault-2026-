<script setup>
defineProps({
    items: { type: Array, default: () => [] },
})

const typeColors = {
    movie: 'text-amber-400 bg-amber-500/15',
    book: 'text-emerald-400 bg-emerald-500/15',
    game: 'text-sky-400 bg-sky-500/15',
    music: 'text-violet-400 bg-violet-500/15',
}

const typeLabels = { movie: 'Movie', book: 'Book', game: 'Game', music: 'Music' }
</script>

<template>
    <div class="bg-vault-800 border border-vault-700 rounded-2xl p-6">
        <h3 class="text-white font-semibold mb-4">Recently Added</h3>

        <div v-if="items.length === 0" class="text-vault-400 text-sm py-4">No items yet. Start adding to your
            collection!</div>

        <div v-else class="space-y-3">
            <div v-for="item in items" :key="item.id"
                class="flex items-center gap-3 p-2 rounded-lg hover:bg-vault-700/50 transition-colors">
                <!-- Thumbnail — fixed size, no aspect-ratio needed -->
                <div class="w-10 h-10 rounded-lg bg-vault-700 flex-shrink-0 overflow-hidden">
                    <img v-if="item.cover_image" :src="'/storage/' + item.cover_image" :alt="item.title"
                        class="block w-full h-full object-cover" />
                    <div v-else class="block w-full h-full flex items-center justify-center text-vault-500 text-xs">
                        {{ item.type === 'movie' ? '\u{1F3AC}' : item.type === 'book' ? '\u{1F4D6}' : item.type ===
                            'game' ? '\u{1F3AE}' : '\u{1F3B5}' }}
                    </div>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-white text-sm font-medium truncate">{{ item.title }}</p>
                    <p class="text-vault-400 text-xs">
                        <span v-if="item.details?.director">{{ item.details.director }} &middot; </span>
                        <span v-if="item.details?.author">{{ item.details.author }} &middot; </span>
                        <span v-if="item.details?.artist">{{ item.details.artist }} &middot; </span>
                        {{ item.details?.format || item.details?.platform || '' }}
                    </p>
                </div>

                <span
                    :class="['text-[10px] font-semibold px-2 py-0.5 rounded-full flex-shrink-0', typeColors[item.type]]">
                    {{ typeLabels[item.type] }}
                </span>
            </div>
        </div>
    </div>
</template>