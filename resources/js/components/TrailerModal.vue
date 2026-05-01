<!-- resources/js/components/TrailerModal.vue -->
<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'

const props = defineProps({
    url: { type: String, default: '' },
    open: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const playing = ref(false)

const videoId = computed(() => {
    if (!props.url) return null
    try {
        const url = new URL(props.url)
        // youtube.com/watch?v=xxxxx
        if (url.hostname.includes('youtube.com') && url.searchParams.get('v')) {
            return url.searchParams.get('v')
        }
        // youtu.be/xxxxx
        if (url.hostname === 'youtu.be') {
            return url.pathname.slice(1)
        }
        // youtube.com/embed/xxxxx
        if (url.pathname.startsWith('/embed/')) {
            return url.pathname.split('/embed/')[1]?.split('?')[0]
        }
    } catch (e) {
        // Not a valid URL, try raw string match
        const match = props.url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/)
        if (match) return match[1]
    }
    return null
})

const embedUrl = computed(() => {
    return videoId.value ? `https://www.youtube-nocookie.com/embed/${videoId.value}?autoplay=1&rel=0&modestbranding=1` : null
})

function handleClose() {
    playing.value = false
    emit('close')
}

function onOverlayClick(e) {
    if (e.target === e.currentTarget) {
        handleClose()
    }
}

function onKeydown(e) {
    if (e.key === 'Escape') handleClose()
}

watch(() => props.open, (val) => {
    if (val) {
        playing.value = true
        document.addEventListener('keydown', onKeydown)
        document.body.style.overflow = 'hidden'
    } else {
        document.removeEventListener('keydown', onKeydown)
        document.body.style.overflow = ''
    }
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = ''
})
</script>

<template>
    <transition name="trailer-modal">
        <div v-if="open && embedUrl" class="fixed inset-0 z-[80] flex items-center justify-center p-4 modal-backdrop"
            @click="onOverlayClick">
            <div class="w-full max-w-5xl">

                <!-- Close button -->
                <div class="flex justify-end mb-3">
                    <button @click="handleClose"
                        class="w-10 h-10 rounded-full bg-white/10 backdrop-blur-sm flex items-center justify-center text-white hover:bg-white/20 transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Video container — 16:9 aspect ratio -->
                <div
                    class="relative w-full bg-black rounded-2xl overflow-hidden shadow-2xl shadow-black/60 ring-1 ring-white/10">
                    <div class="aspect-video">
                        <iframe v-if="playing" :src="embedUrl" class="absolute inset-0 w-full h-full" frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen></iframe>
                    </div>
                </div>

                <!-- Bottom info -->
                <div class="mt-3 text-center">
                    <p class="text-vault-400 text-xs">Press Escape or click outside to close</p>
                </div>

            </div>
        </div>
    </transition>
</template>

<style scoped>
.trailer-modal-enter-active,
.trailer-modal-leave-active {
    transition: opacity 0.25s ease;
}

.trailer-modal-enter-from,
.trailer-modal-leave-to {
    opacity: 0;
}

/* iframe fade-in after backdrop */
iframe {
    animation: fadeIn 0.3s ease 0.1s both;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}
</style>