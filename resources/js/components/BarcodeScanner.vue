<!-- resources/js/components/BarcodeScanner.vue -->
<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Html5Qrcode } from 'html5-qrcode'

const emit = defineEmits(['scanned', 'close'])

const scannerRef = ref(null)
const scannerError = ref('')
const scanning = ref(false)
const torchOn = ref(false)

let html5QrCode = null

async function startScanner() {
    await nextTick()
    if (!scannerRef.value) return

    html5QrCode = new Html5Qrcode('barcode-scanner-region')

    try {
        scanning.value = true
        await html5QrCode.start(
            { facingMode: 'environment' },
            {
                fps: 10,
                qrbox: { width: 280, height: 120 },
                aspectRatio: 1.5,
            },
            onScanSuccess,
            () => { } // continuous no-match callback — do nothing
        )
    } catch (err) {
        scanning.value = false
        const msg = err.toString()
        if (msg.includes('Permission') || msg.includes('NotAllowedError')) {
            scannerError.value = 'Camera permission denied. Allow camera access in your browser settings and try again.'
        } else if (msg.includes('NotFoundError') || msg.includes('DevicesNotFound')) {
            scannerError.value = 'No camera found. Connect a camera and try again.'
        } else if (msg.includes('secure context') || msg.includes('NotReadableError')) {
            scannerError.value = 'Camera requires HTTPS. Make sure you are using a secure connection.'
        } else {
            scannerError.value = 'Could not start camera: ' + (err.message || msg)
        }
    }
}

function onScanSuccess(decodedText) {
    // Stop immediately to prevent double-scans
    html5QrCode.stop().then(() => {
        scanning.value = false
        emit('scanned', decodedText)
    }).catch(() => {
        emit('scanned', decodedText)
    })
}

async function toggleTorch() {
    if (!html5QrCode || !scanning.value) return
    try {
        // Html5Qrcode doesn't have built-in torch, use track API
        const videoElem = scannerRef.value?.querySelector('video')
        if (videoElem?.srcObject) {
            const track = videoElem.srcObject.getVideoTracks()[0]
            if (track) {
                const capabilities = track.getCapabilities()
                if (capabilities.torch) {
                    torchOn.value = !torchOn.value
                    await track.applyConstraints({ advanced: [{ torch: torchOn.value }] })
                }
            }
        }
    } catch (e) {
        // Torch not supported on this device
    }
}

function handleClose() {
    emit('close')
}

onMounted(startScanner)

onBeforeUnmount(async () => {
    if (html5QrCode) {
        try {
            if (scanning.value) await html5QrCode.stop()
            html5QrCode.clear()
        } catch (e) { }
    }
})
</script>

<template>
    <transition name="modal">
        <div class="fixed inset-0 z-[70] flex items-center justify-center modal-backdrop" @click.self="handleClose">
            <div
                class="bg-vault-900 border border-vault-600 rounded-2xl w-full max-w-lg mx-4 overflow-hidden shadow-2xl">

                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-vault-700">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/15 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <h2 class="text-white font-semibold">Scan Barcode</h2>
                    </div>
                    <button @click="handleClose"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-vault-400 hover:text-white hover:bg-vault-700 transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Scanner area -->
                <div class="relative bg-black">
                    <div ref="scannerRef" id="barcode-scanner-region" class="w-full min-h-[320px]"></div>

                    <!-- Scanning indicator -->
                    <div v-if="scanning"
                        class="absolute bottom-4 left-0 right-0 flex justify-center pointer-events-none">
                        <div class="px-4 py-2 bg-black/60 backdrop-blur-sm rounded-full flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
                            <span class="text-white text-xs font-medium">Scanning...</span>
                        </div>
                    </div>
                </div>

                <!-- Error state -->
                <div v-if="scannerError" class="p-6 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-rose-500/15 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <p class="text-vault-200 text-sm mb-4">{{ scannerError }}</p>
                    <button @click="handleClose"
                        class="px-5 py-2.5 rounded-xl bg-vault-700 text-white text-sm font-medium hover:bg-vault-600 transition-all">
                        Close
                    </button>
                </div>

                <!-- Bottom controls -->
                <div v-if="scanning && !scannerError"
                    class="px-5 py-4 border-t border-vault-700 flex items-center justify-between">
                    <p class="text-vault-400 text-xs">
                        Point your camera at a barcode or ISBN
                    </p>
                    <button @click="toggleTorch" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                        :class="torchOn ? 'bg-amber-500 text-white' : 'bg-vault-700 text-vault-300 hover:text-white hover:bg-vault-600'">
                        {{ torchOn ? 'Flash ON' : 'Flash' }}
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
/* Override html5-qrcode injected styles for dark theme */
:deep(#barcode-scanner-region) {
    border: none !important;
    background: #000 !important;
}

:deep(#barcode-scanner-region video) {
    border-radius: 0 !important;
    object-fit: cover !important;
}

:deep(#barcode-scanner-region img) {
    display: none !important;
}

/* The scanning frame border */
:deep(#barcode-scanner-region #qr-shaded-region) {
    border-color: rgba(251, 191, 36, 0.5) !important;
    border-style: solid !important;
    border-width: 2px !important;
}

/* Hide the default dashboard section the library creates */
:deep(#barcode-scanner-region > div:last-child:not(#qr-shaded-region)) {
    display: none !important;
}
</style>