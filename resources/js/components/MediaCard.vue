<!-- resources/js/components/MediaCard.vue -->
<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useCollectionStore } from '../stores/collection'

const props = defineProps({
    item: Object,
    type: String,
})

const emit = defineEmits(['edit', 'deleted'])
const store = useCollectionStore()
const router = useRouter()
const confirmingDelete = ref(false)

const consumedBadge = computed(() => {
    const d = props.item.details
    if (!d) return null

    if (props.type === 'book' && d.read) {
        return { label: 'Read', classes: 'text-emerald-200 bg-emerald-600/85 backdrop-blur-sm' }
    }
    if (props.type === 'tv_show' && d.watch_status === 'completed') {
        return { label: 'Completed', classes: 'text-emerald-200 bg-emerald-600/85 backdrop-blur-sm' }
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

const subtitle = computed(() => {
    const d = props.item.details
    if (!d) return ''
    if (props.type === 'movie') return [d.director, d.format, d.release_year].filter(Boolean).join(' \u00B7 ')
    if (props.type === 'book') return [d.author, d.release_year].filter(Boolean).join(' \u00B7 ')
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
</script>

<template>
    <div @click="goToDetail"
        class="media-card bg-vault-800 border border-vault-700 rounded-xl overflow-hidden group relative flex flex-col cursor-pointer">
        <!-- Cover container -->
        <div class="aspect-2/3 bg-vault-700 relative overflow-hidden min-h-0 flex-shrink-0">
            <img v-if="item.cover_image" :src="'/storage/' + item.cover_image" :alt="item.title"
                class="block w-full h-full object-cover" loading="lazy" />
            <div v-else
                class="block w-full h-full flex items-center justify-center bg-gradient-to-br from-vault-700 to-vault-800">
                <span class="text-4xl opacity-30">
                    {{ type === 'movie' ? '\u{1F3AC}' : type === 'book' ? '\u{1F4D6}' : type === 'game' ? '\u{1F3AE}' :
                        type === 'tv_show' ? '\u{1F4FA}' : '\u{1F3B5}' }}
                </span>
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
            <p class="text-vault-400 text-xs truncate mt-0.5">{{ subtitle }}</p>
            <div class="mt-2 flex items-center justify-between gap-2">
                <span v-if="item.details?.format || item.details?.platform"
                    class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-vault-700 text-vault-300 truncate">
                    {{ item.details.format || item.details.platform }}
                </span>
                <span v-if="item.purchase_price" class="text-xs text-vault-400 whitespace-nowrap ml-auto">
                    ${{ Number(item.purchase_price).toFixed(2) }}
                </span>
            </div>
        </div>
    </div>
</template>