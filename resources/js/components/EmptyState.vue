<script setup>
const props = defineProps({
    type: { type: String, default: 'default' },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    action: { type: String, default: '' },
})

const emit = defineEmits(['action'])

const defaults = {
    movie: { title: 'No movies yet', message: 'Search TMDB and start building your film collection.' },
    book: { title: 'No books yet', message: 'Search Google Books or scan a barcode to add your first read.' },
    game: { title: 'No games yet', message: 'Search RAWG to add games across all your platforms.' },
    tv_show: { title: 'No TV shows yet', message: 'Find your next binge-worthy series from TMDB.' },
    music: { title: 'No albums yet', message: 'Search Discogs for vinyl, CDs, and digital releases.' },
    wishlist: { title: 'Wishlist is empty', message: 'Save items you want to add to your collection later.' },
    search: { title: 'No results found', message: 'Try a different search term or adjust your filters.' },
    franchise: { title: 'No items in this franchise', message: 'Add movies, books, or games to this collection.' },
    default: { title: 'Nothing here yet', message: 'Get started by adding your first item.' },
}

const config = defaults[props.type] || defaults.default
const displayTitle = props.title || config.title
const displayMessage = props.message || config.message
</script>

<template>
    <div class="flex flex-col items-center justify-center py-16 sm:py-20 px-4">
        <!-- Illustrations -->
        <div class="w-32 h-32 sm:w-40 sm:h-40 mb-6 flex items-center justify-center">

            <!-- Movie: Film reel with empty slot -->
            <svg v-if="type === 'movie'" viewBox="0 0 120 120" fill="none" class="w-full h-full text-vault-600">
                <circle cx="60" cy="60" r="50" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 3"
                    opacity="0.4" />
                <circle cx="60" cy="60" r="20" stroke="currentColor" stroke-width="1.5" opacity="0.6" />
                <circle cx="60" cy="60" r="8" stroke="currentColor" stroke-width="1.5" opacity="0.4" />
                <circle cx="60" cy="35" r="4" stroke="currentColor" stroke-width="1.5" opacity="0.3" />
                <circle cx="85" cy="60" r="4" stroke="currentColor" stroke-width="1.5" opacity="0.3" />
                <circle cx="60" cy="85" r="4" stroke="currentColor" stroke-width="1.5" opacity="0.3" />
                <circle cx="35" cy="60" r="4" stroke="currentColor" stroke-width="1.5" opacity="0.3" />
                <rect x="45" y="52" width="30" height="16" rx="2" stroke="currentColor" stroke-width="1.5"
                    opacity="0.25" />
            </svg>

            <!-- Book: Open book with no text -->
            <svg v-else-if="type === 'book'" viewBox="0 0 120 120" fill="none" class="w-full h-full text-vault-600">
                <path d="M25 30 C25 30 55 25 60 25 C65 25 95 30 95 30 L95 90 C95 90 65 85 60 85 C55 85 25 90 25 90 Z"
                    stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" opacity="0.6" />
                <path d="M60 25 L60 85" stroke="currentColor" stroke-width="1.5" opacity="0.3" />
                <line x1="35" y1="42" x2="52" y2="40" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.25" />
                <line x1="35" y1="52" x2="52" y2="50" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.25" />
                <line x1="35" y1="62" x2="52" y2="60" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.25" />
                <line x1="68" y1="40" x2="85" y2="42" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.25" />
                <line x1="68" y1="50" x2="85" y2="52" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.25" />
                <line x1="68" y1="60" x2="85" y2="62" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.25" />
            </svg>

            <!-- Game: Controller -->
            <svg v-else-if="type === 'game'" viewBox="0 0 120 120" fill="none" class="w-full h-full text-vault-600">
                <rect x="20" y="40" width="80" height="45" rx="22" stroke="currentColor" stroke-width="1.5"
                    opacity="0.6" />
                <line x1="10" y1="50" x2="20" y2="50" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.3" />
                <line x1="10" y1="75" x2="20" y2="75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.3" />
                <circle cx="38" cy="60" r="7" stroke="currentColor" stroke-width="1.5" opacity="0.3" />
                <circle cx="38" cy="60" r="2.5" stroke="currentColor" stroke-width="1" opacity="0.2" />
                <line x1="32" y1="54" x2="32" y2="66" stroke="currentColor" stroke-width="1" opacity="0.2" />
                <line x1="44" y1="54" x2="44" y2="66" stroke="currentColor" stroke-width="1" opacity="0.2" />
                <line x1="32" y1="60" x2="44" y2="60" stroke="currentColor" stroke-width="1" opacity="0.2" />
                <circle cx="82" cy="52" r="3.5" stroke="currentColor" stroke-width="1.5" opacity="0.3" />
                <circle cx="92" cy="62" r="3.5" stroke="currentColor" stroke-width="1.5" opacity="0.3" />
                <circle cx="77" cy="70" r="3.5" stroke="currentColor" stroke-width="1.5" opacity="0.3" />
            </svg>

            <!-- TV Show: Screen with signal -->
            <svg v-else-if="type === 'tv_show'" viewBox="0 0 120 120" fill="none" class="w-full h-full text-vault-600">
                <rect x="15" y="25" width="90" height="60" rx="6" stroke="currentColor" stroke-width="1.5"
                    opacity="0.6" />
                <line x1="45" y1="85" x2="75" y2="85" stroke="currentColor" stroke-width="1.5" opacity="0.3" />
                <line x1="60" y1="85" x2="60" y2="95" stroke="currentColor" stroke-width="1.5" opacity="0.3" />
                <line x1="45" y1="95" x2="75" y2="95" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.3" />
                <!-- Signal waves -->
                <path d="M42 52 C42 52 48 45 60 45 C72 45 78 52 78 52" stroke="currentColor" stroke-width="1.5"
                    stroke-linecap="round" opacity="0.2" />
                <path d="M36 55 C36 55 46 42 60 42 C74 42 84 55 84 55" stroke="currentColor" stroke-width="1.5"
                    stroke-linecap="round" opacity="0.15" />
                <line x1="28" y1="55" x2="92" y2="55" stroke="currentColor" stroke-width="0.5" opacity="0.1" />
                <rect x="40" y="62" width="40" height="6" rx="1" stroke="currentColor" stroke-width="1"
                    opacity="0.15" />
                <rect x="50" y="72" width="20" height="4" rx="1" stroke="currentColor" stroke-width="1" opacity="0.1" />
            </svg>

            <!-- Music: Vinyl record -->
            <svg v-else-if="type === 'music'" viewBox="0 0 120 120" fill="none" class="w-full h-full text-vault-600">
                <circle cx="60" cy="60" r="48" stroke="currentColor" stroke-width="1.5" opacity="0.5" />
                <circle cx="60" cy="60" r="38" stroke="currentColor" stroke-width="0.5" opacity="0.2" />
                <circle cx="60" cy="60" r="28" stroke="currentColor" stroke-width="0.5" opacity="0.15" />
                <circle cx="60" cy="60" r="18" stroke="currentColor" stroke-width="0.5" opacity="0.1" />
                <circle cx="60" cy="60" r="8" stroke="currentColor" stroke-width="1.5" opacity="0.4" />
                <circle cx="60" cy="60" r="3" fill="currentColor" opacity="0.3" />
                <!-- Tonearm -->
                <line x1="85" y1="15" x2="68" y2="48" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.3" />
                <circle cx="85" cy="15" r="3" stroke="currentColor" stroke-width="1" opacity="0.2" />
            </svg>

            <!-- Wishlist: Heart outline -->
            <svg v-else-if="type === 'wishlist'" viewBox="0 0 120 120" fill="none" class="w-full h-full text-vault-600">
                <path
                    d="M60 95 C60 95 15 65 15 42 C15 28 26 18 40 18 C50 18 55 24 60 32 C65 24 70 18 80 18 C94 18 105 28 105 42 C105 65 60 95 60 95 Z"
                    stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" opacity="0.5" />
                <path d="M30 42 L50 42" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.15" />
                <path d="M70 42 L90 42" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.15" />
            </svg>

            <!-- Search: Magnifying glass with empty result -->
            <svg v-else-if="type === 'search'" viewBox="0 0 120 120" fill="none" class="w-full h-full text-vault-600">
                <circle cx="52" cy="52" r="28" stroke="currentColor" stroke-width="1.5" opacity="0.5" />
                <line x1="72" y1="72" x2="98" y2="98" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                    opacity="0.5" />
                <!-- Question mark inside -->
                <path d="M46 44 C46 38 52 34 58 34 C64 34 68 38 66 42" stroke="currentColor" stroke-width="1.5"
                    stroke-linecap="round" opacity="0.25" />
                <circle cx="57" cy="50" r="1.5" fill="currentColor" opacity="0.25" />
            </svg>

            <!-- Franchise: Film strip chain -->
            <svg v-else-if="type === 'franchise'" viewBox="0 0 120 120" fill="none"
                class="w-full h-full text-vault-600">
                <rect x="20" y="25" width="24" height="18" rx="2" stroke="currentColor" stroke-width="1.5"
                    opacity="0.4" />
                <rect x="48" y="25" width="24" height="18" rx="2" stroke="currentColor" stroke-width="1.5"
                    opacity="0.3" />
                <rect x="76" y="25" width="24" height="18" rx="2" stroke="currentColor" stroke-width="1.5"
                    opacity="0.2" />
                <line x1="32" y1="43" x2="32" y2="55" stroke="currentColor" stroke-width="1" stroke-dasharray="2 2"
                    opacity="0.2" />
                <line x1="60" y1="43" x2="60" y2="55" stroke="currentColor" stroke-width="1" stroke-dasharray="2 2"
                    opacity="0.15" />
                <line x1="88" y1="43" x2="88" y2="55" stroke="currentColor" stroke-width="1" stroke-dasharray="2 2"
                    opacity="0.1" />
                <rect x="20" y="55" width="24" height="18" rx="2" stroke="currentColor" stroke-width="1.5"
                    opacity="0.2" />
                <rect x="48" y="55" width="24" height="18" rx="2" stroke="currentColor" stroke-width="1.5"
                    opacity="0.15" />
                <rect x="76" y="55" width="24" height="18" rx="2" stroke="currentColor" stroke-width="1.5"
                    stroke-dasharray="3 2" opacity="0.15" />
                <!-- Plus sign -->
                <line x1="88" y1="61" x2="88" y2="67" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.2" />
                <line x1="85" y1="64" x2="91" y2="64" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.2" />
            </svg>

            <!-- Default: Empty box -->
            <svg v-else viewBox="0 0 120 120" fill="none" class="w-full h-full text-vault-600">
                <rect x="22" y="30" width="76" height="60" rx="4" stroke="currentColor" stroke-width="1.5"
                    opacity="0.5" />
                <line x1="22" y1="50" x2="98" y2="50" stroke="currentColor" stroke-width="1" opacity="0.15" />
                <line x1="22" y1="70" x2="98" y2="70" stroke="currentColor" stroke-width="1" opacity="0.15" />
                <!-- Plus -->
                <line x1="60" y1="52" x2="60" y2="68" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.25" />
                <line x1="52" y1="60" x2="68" y2="60" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    opacity="0.25" />
            </svg>
        </div>

        <!-- Text -->
        <h3 class="text-lg sm:text-xl font-semibold text-vault-200 mb-2 text-center">{{ displayTitle }}</h3>
        <p class="text-vault-400 text-sm text-center max-w-xs">{{ displayMessage }}</p>

        <!-- Action button -->
        <button v-if="action" @click="emit('action')"
            class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 text-white font-semibold rounded-xl hover:from-amber-400 hover:to-amber-500 transition-all shadow-lg shadow-amber-500/20 text-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            {{ action }}
        </button>
    </div>
</template>