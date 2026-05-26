<!-- resources/js/components/MediaCard.vue -->
<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useCollectionStore } from '../stores/collection'
import api from '../api'  // Make sure to import the API module for status updates

const props = defineProps({
    item: Object,
    type: String,
})

const emit = defineEmits(['edit', 'deleted', 'statusChanged'])
const store = useCollectionStore()
const router = useRouter()
const confirmingDelete = ref(false)
const statusLoading = ref(false)

// For consumed items, show a badge indicating "Read", "Seen", or "Completed"
const consumedBadge = computed(() => {
    const d = props.item.details
    if (!d) return null

    if (props.type === 'book' && d.reading_status === 'read') {
        return { label: 'Read', classes: 'text-emerald-200 bg-emerald-600/85 backdrop-blur-sm' }
    }
    if (props.type === 'movie' && d.watch_status === 'seen') {
        return { label: 'Seen', classes: 'text-emerald-200 bg-emerald-600/85 backdrop-blur-sm' }
    }
    if (props.type === 'tv_show' && d.watch_status === 'completed') {
        return { label: 'Completed', classes: 'text-emerald-200 bg-emerald-600/85 backdrop-blur-sm' }
    }
    if (props.type === 'game' && d.playing_status === 'completed') {
        return { label: 'Completed', classes: 'text-emerald-200 bg-emerald-600/85 backdrop-blur-sm' }
    }
    return null
})

// For in-progress items, show a percentage badge based on current progress
const inProgressBadge = computed(() => {
    const d = props.item.details
    if (!d) return null

    if (props.type === 'book' && d.reading_status === 'reading') {
        const pct = d.current_page && d.page_count ? Math.round((d.current_page / d.page_count) * 100) : 0
        return { label: pct + '%', classes: 'text-amber-200 bg-amber-600/85 backdrop-blur-sm' }
    }
    if (props.type === 'game' && d.playing_status === 'playing') {
        return { label: (d.progress_percent || 0) + '%', classes: 'text-amber-200 bg-amber-600/85 backdrop-blur-sm' }
    }
    return null
})

const upNextBadge = computed(() => {
    const d = props.item.details
    if (!d) return null

    if (props.type === 'book' && d.reading_status === 'tbr') {
        return { label: 'TBR', classes: 'text-sky-200 bg-sky-600/85 backdrop-blur-sm' }
    }
    if (props.type === 'movie' && d.watch_status === 'to_be_seen') {
        return { label: 'To See', classes: 'text-sky-200 bg-sky-600/85 backdrop-blur-sm' }
    }
    if (props.type === 'tv_show' && d.watch_status === 'plan_to_watch') {
        return { label: 'To See', classes: 'text-sky-200 bg-sky-600/85 backdrop-blur-sm' }
    }
    return null
})

const statusColors = {
    owned: 'text-emerald-400 bg-emerald-500/15',
    wishlist: 'text-amber-400 bg-amber-500/15',
    borrowed: 'text-sky-400 bg-sky-500/15',
    sold: 'text-vault-400 bg-vault-600/30',
    lost: 'text-rose-400 bg-rose-500/15',
}

const platformConfig = {
    'PS5': { bg: 'bg-[#003087]', text: 'text-white', icon: 'fa-brands fa-playstation' },
    'PS4': { bg: 'bg-[#003087]/80', text: 'text-blue-200', icon: 'fa-brands fa-playstation' },
    'PS3': { bg: 'bg-[#37392e]', text: 'text-gray-200', icon: 'fa-brands fa-playstation' },
    'PS Vita': { bg: 'bg-[#003087]/60', text: 'text-blue-300', icon: 'fa-brands fa-playstation' },
    'Switch': { bg: 'bg-[#e60012]', text: 'text-white', icon: 'fa-solid fa-gamepad' },
    'Wii U': { bg: 'bg-[#8b8b8b]', text: 'text-white', icon: 'fa-solid fa-gamepad' },
    'Wii': { bg: 'bg-[#8b8b8b]', text: 'text-white', icon: 'fa-solid fa-gamepad' },
    'Xbox Series X': { bg: 'bg-[#107c10]', text: 'text-white', icon: 'fa-brands fa-xbox' },
    'Xbox One': { bg: 'bg-[#107c10]/80', text: 'text-green-200', icon: 'fa-brands fa-xbox' },
    'PC': { bg: 'bg-[#0078d4]', text: 'text-white', icon: 'fa-brands fa-windows' },
    'Steam': { bg: 'bg-[#1b2838]', text: 'text-white', icon: 'fa-brands fa-steam' },
    'Other': { bg: 'bg-vault-600', text: 'text-vault-200', icon: 'fa-solid fa-gamepad' },
}

function getPlatformStyle(platformName) {
    return platformConfig[platformName] || platformConfig['Other']
}

const subtitle = computed(() => {
    const d = props.item.details
    if (!d) return ''
    if (props.type === 'movie') return [d.director, d.format, d.release_year, d.file_size].filter(Boolean).join(' \u00B7 ')
    if (props.type === 'book') return [d.author, d.edition, d.release_year].filter(Boolean).join(' \u00B7 ')
    if (props.type === 'game') return [d.platform, d.format, d.release_year].filter(Boolean).join(' \u00B7 ')
    if (props.type === 'music') return [d.artist, d.format, d.release_year].filter(Boolean).join(' \u00B7 ')
    if (props.type === 'tv_show') {
        const d = props.item.details
        if (!d) return ''
        const parts = []
        if (d.seasons && d.seasons.length > 0) {
            parts.push(`${d.seasons.length} season${d.seasons.length > 1 ? 's' : ''}`)
        } else if (d.release_year) {
            parts.push(d.release_year)
        }
        return parts.join(' \u00B7 ')
    }
    return ''
})


// Navigate to detail page — stop propagation on action buttons
function goToDetail() {
    router.push(`/${props.type}/${props.item.id}`)
}

function onEdit(e) {
    e.stopPropagation()
    emit('edit', props.item)
}

function onConfirmDelete(e) {
    e.stopPropagation()
    confirmingDelete.value = true
}

function onDelete(e) {
    e.stopPropagation()
    confirmingDelete.value = false
    store.deleteItem(props.type, props.item.id)
    emit('deleted')
}

async function handleDelete() {
    confirmingDelete.value = false
    await store.deleteItem(props.type, props.item.id)
    emit('deleted')
}

async function quickStatus(e) {
    e.stopPropagation()
    statusLoading.value = true
    try {
        const d = props.item.details
        if (props.type === 'movie') {
            const next = d.watch_status === 'to_be_seen' ? 'seen' : 'to_be_seen'
            await api.patch(`/collection/${props.type}/${props.item.id}/status`, { watch_status: next })
            props.item.details.watch_status = next
        } else if (props.type === 'book') {
            const next = d.reading_status === 'tbr' ? 'reading' : 'tbr'
            await api.patch(`/collection/${props.type}/${props.item.id}/status`, { reading_status: next })
            props.item.details.reading_status = next
        }
        emit('statusChanged')
    } catch (err) {
        console.error('Status update failed:', err)
    } finally {
        statusLoading.value = false
    }
}
</script>

<template>
    <div @click="goToDetail"
        class="media-card bg-vault-800 border border-vault-700 rounded-xl overflow-hidden group relative flex flex-col cursor-pointer">
        <!-- Cover container -->
        <div :class="[
            'bg-vault-700 relative overflow-hidden min-h-0 flex-shrink-0',
            type === 'music' ? 'aspect-square' : 'aspect-2/3'
        ]">
            <img v-if="item.cover_image" :src="'/storage/' + item.cover_image" :alt="item.title"
                class="block w-full h-full object-cover" loading="lazy" />
            <div v-else
                class="block w-full h-full flex items-center justify-center bg-gradient-to-br from-vault-700 to-vault-800">
                <span class="text-4xl opacity-30">
                    {{ type === 'movie' ? '\u{1F3AC}' : type === 'book' ? '\u{1F4D6}' : type === 'game' ? '\u{1F3AE}' :
                        type === 'tv_show' ? '\u{1F4FA}' : '\u{1F3B5}' }}
                </span>
                <!-- Progress bar at bottom of cover -->
                <div v-if="inProgressBadge" class="absolute bottom-0 left-0 right-0 h-1 bg-black/50">
                    <div class="h-full bg-amber-500 transition-all duration-500"
                        :style="{ width: inProgressBadge.label }"></div>
                </div>
            </div>

            <!-- Rating badge -->
            <div v-if="item.details?.personal_rating"
                class="absolute top-2 right-2 w-8 h-8 rounded-lg bg-black/70 backdrop-blur-sm flex items-center justify-center text-amber-400 text-xs font-bold">
                {{ item.details.personal_rating }}
            </div>

            <!-- Status badge -->
            <div class="absolute top-2 left-2">
                <span :class="['text-[10px] font-semibold px-1.5 py-0.5 rounded', statusColors[item.status] || '']">
                    {{ item.status }}
                </span>
            </div>

            <!-- Trailer indicator -->
            <div v-if="(type === 'movie' || type === 'tv_show') && item.details?.trailer_url"
                class="absolute bottom-2 right-2 w-8 h-8 rounded-lg bg-red-600/90 backdrop-blur-sm flex items-center justify-center text-white opacity-80 group-hover:opacity-0 transition-opacity">
                <svg class="w-3.5 h-3.5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z" />
                </svg>
            </div>

            <!-- Consumed badge (read / completed) -->
            <div v-if="consumedBadge"
                class="absolute bottom-2 left-2 flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide opacity-90 group-hover:opacity-0 transition-opacity"
                :class="consumedBadge.classes">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                {{ consumedBadge.label }}
            </div>

            <!-- In-progress badge -->
            <div v-if="inProgressBadge"
                class="absolute bottom-2 left-2 flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide opacity-90 group-hover:opacity-0 transition-opacity"
                :class="inProgressBadge.classes">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                {{ inProgressBadge.label }}
            </div>

            <!-- Up Next badge (TBR / To Be Seen) -->
            <div v-if="upNextBadge"
                class="absolute bottom-2 left-2 flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide opacity-90 group-hover:opacity-0 transition-opacity"
                :class="upNextBadge.classes">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                </svg>
                {{ upNextBadge.label }}
            </div>

            <!-- Hover overlay with action buttons -->
            <div
                class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                <button @click="onEdit"
                    class="w-10 h-10 rounded-xl bg-white/15 backdrop-blur-sm flex items-center justify-center text-white hover:bg-white/25 transition-all"
                    title="Edit">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                </button>

                <!-- To See / TBR button -->
                <button v-if="type === 'movie' && item.details?.watch_status !== 'seen'" @click="quickStatus"
                    :disabled="statusLoading"
                    class="w-10 h-10 rounded-xl bg-sky-500/30 backdrop-blur-sm flex items-center justify-center text-sky-300 hover:bg-sky-500/50 transition-all"
                    :title="item.details?.watch_status === 'to_be_seen' ? 'Mark as Seen' : 'To Be Seen'">
                    <svg v-if="!statusLoading" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <div v-else class="w-4 h-4 border-2 border-sky-300 border-t-transparent rounded-full animate-spin">
                    </div>
                </button>

                <button v-if="type === 'book' && item.details?.reading_status !== 'read'" @click="quickStatus"
                    :disabled="statusLoading"
                    class="w-10 h-10 rounded-xl bg-sky-500/30 backdrop-blur-sm flex items-center justify-center text-sky-300 hover:bg-sky-500/50 transition-all"
                    :title="item.details?.reading_status === 'tbr' ? 'Start Reading' : 'Add to TBR'">
                    <svg v-if="!statusLoading" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <div v-else class="w-4 h-4 border-2 border-sky-300 border-t-transparent rounded-full animate-spin">
                    </div>
                </button>

                <button v-if="!confirmingDelete" @click="onConfirmDelete"
                    class="w-10 h-10 rounded-xl bg-rose-500/30 backdrop-blur-sm flex items-center justify-center text-rose-400 hover:bg-rose-500/50 transition-all"
                    title="Delete">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
                <button v-else @click="onDelete"
                    class="px-3 h-10 rounded-xl bg-rose-500 backdrop-blur-sm flex items-center justify-center text-white text-xs font-bold hover:bg-rose-600 transition-all">
                    Confirm
                </button>
            </div>
        </div>

        <!-- Info area -->
        <div class="p-3 flex-shrink-0">
            <h3 class="text-white text-sm font-semibold truncate" :title="item.title">{{ item.title }}</h3>
            <p v-if="item.details?.original_title && item.details.original_title !== item.title"
                class="text-vault-400 text-xs truncate mt-0.5" :title="item.details.original_title">
                {{ item.details.original_title }}
            </p>
            <p class="text-vault-400 text-xs truncate mt-0.5">{{ subtitle }}</p>
            <div class="mt-2 flex items-center justify-between gap-2">
                <span v-if="item.details?.platform"
                    :class="['inline-flex items-center gap-1.2 text-[10px] font-semibold px-2 py-0.5 rounded-md', getPlatformStyle(item.details.platform).bg, getPlatformStyle(item.details.platform).text]">
                    <i :class="getPlatformStyle(item.details.platform).icon"></i>
                    {{ item.details.platform }}
                </span>
                <span v-if="item.purchase_price" class="text-xs text-vault-400 whitespace-nowrap ml-auto">
                    ${{ Number(item.purchase_price).toFixed(2) }}
                </span>
            </div>
        </div>
    </div>
</template>