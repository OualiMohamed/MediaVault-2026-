<script setup>
import { onMounted, computed } from 'vue'
import { useDashboardStore } from '../stores/dashboard'
import { ref } from 'vue'
import { useImport } from '../composables/useImport'
import { useExport } from '../composables/useExport'
import FormatBreakdown from '../components/FormatBreakdown.vue'
import RecentItems from '../components/RecentItems.vue'
import DoughnutChart from '../components/DoughnutChart.vue'
import RatingBarChart from '../components/RatingBarChart.vue'


const dashboard = useDashboardStore()
const { exporting, exportCollection, exportFullZip } = useExport()

onMounted(() => dashboard.fetchStats())

const fullBackupType = ref('')

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

const { validating, importing, preview, error: importError, validateFile, executeImport, resetState } = useImport()

const showImportModal = ref(false)
const importType = ref('movie')
const importFile = ref(null)

const importTypes = [
    { type: 'movie', label: 'Movies', icon: '\u{1F3AC}', format: 'CSV' },
    { type: 'book', label: 'Books', icon: '\u{1F4D6}', format: 'CSV' },
    { type: 'game', label: 'Games', icon: '\u{1F3AE}', format: 'CSV' },
    { type: 'tv_show', label: 'TV Shows', icon: '\u{1F4FA}', format: 'JSON' },
    { type: 'music', label: 'Music', icon: '\u{1F3B5}', format: 'CSV' },
]

function openImportModal(type) {
    importType.value = type
    importFile.value = null
    resetState()
    showImportModal.value = true
}

function handleFileChange(e) {
    importFile.value = e.target.files[0] || null
}

async function handleValidate() {
    if (!importFile.value) return
    await validateFile(importType.value, importFile.value)
}

async function handleConfirmImport() {
    const result = await executeImport(importType.value)
    if (result) {
        showImportModal.value = false
        dashboard.fetchStats() // Refresh dashboard counts
    }
}
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

                <div class="bg-vault-800 border border-vault-700 rounded-2xl p-6">
                    <h2 class="text-white font-semibold mb-4">Collection Breakdown</h2>
                    <DoughnutChart :movie="dashboard.stats?.by_type?.movie?.count ?? 0"
                        :tv-show="dashboard.stats?.by_type?.tv_show?.count ?? 0"
                        :game="dashboard.stats?.by_type?.game?.count ?? 0"
                        :book="dashboard.stats?.by_type?.book?.count ?? 0"
                        :music="dashboard.stats?.by_type?.music?.count ?? 0" />
                </div>
                <div class="bg-vault-800 border border-vault-700 rounded-2xl p-6">
                    <h2 class="text-white font-semibold mb-4">Rating Distribution</h2>
                    <RatingBarChart :data="dashboard.stats?.rating_distribution ?? []" />
                </div>
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
                <div class="mt-4 pt-4 border-t border-vault-700">
                    <div class="flex gap-2">
                        <select v-model="fullBackupType"
                            class="flex-1 px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 text-sm">
                            <option value="" disabled>Select library...</option>
                            <option value="movie">Movies</option>
                            <option value="book">Books</option>
                            <option value="game">Games</option>
                            <option value="tv_show">TV Shows</option>
                            <option value="music">Music</option>
                        </select>
                        <button @click="exportFullZip(fullBackupType)" :disabled="!fullBackupType || !!exporting"
                            class="px-5 py-2.5 bg-vault-700/50 border border-dashed border-vault-500 rounded-xl hover:border-vault-400 hover:bg-vault-700 transition-all disabled:opacity-50 disabled:cursor-wait flex items-center gap-2 text-sm text-vault-200 whitespace-nowrap">
                            <svg v-if="!exporting" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <div v-else
                                class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin">
                            </div>
                            Download ZIP
                        </button>
                    </div>
                    <p class="text-vault-500 text-xs mt-2 text-center">Includes all data + cover images</p>
                </div>
            </div>
            <!-- Import Section -->
            <div class="bg-vault-800 border border-vault-700 rounded-2xl p-6 mt-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 rounded-xl bg-vault-700 flex items-center justify-center">
                        <svg class="w-5 h-5 text-vault-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-white font-semibold">Import Collection</h2>
                        <p class="text-vault-400 text-sm">CSV for media, JSON for TV Shows</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <button v-for="imp in importTypes" :key="imp.type" @click="openImportModal(imp.type)"
                        class="flex items-center gap-3 px-4 py-3 bg-vault-700/50 border border-vault-600 rounded-xl hover:border-vault-500 hover:bg-vault-700 transition-all group text-left">
                        <span class="text-lg flex-shrink-0">{{ imp.icon }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-white text-sm font-medium truncate">{{ imp.label }}</p>
                            <span class="text-[10px] font-mono font-semibold px-1.5 py-0.5 rounded mt-0.5 inline-block"
                                :class="imp.format === 'JSON' ? 'bg-amber-500/15 text-amber-400' : 'bg-vault-600 text-vault-300'">
                                {{ imp.format }}
                            </span>
                        </div>
                        <svg class="w-4 h-4 text-vault-500 group-hover:text-vault-300 transition-colors flex-shrink-0"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                    </button>
                </div>
            </div>
        </template>
        <!-- Import Modal -->
        <transition name="tmdb-modal">
            <div v-if="showImportModal"
                class="fixed inset-0 z-[80] flex items-center justify-center px-4 modal-backdrop"
                @click.self="showImportModal = false">
                <div
                    class="bg-vault-800 border border-vault-600 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">

                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-vault-700">
                        <h2 class="text-white font-semibold">Import {{importTypes.find(t => t.type ===
                            importType)?.label}}
                        </h2>
                        <button @click="showImportModal = false"
                            class="w-8 h-8 rounded-lg flex items-center justify-center text-vault-400 hover:text-white hover:bg-vault-700 transition-all">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-5">
                        <!-- File Input -->
                        <div>
                            <label class="block text-sm font-medium text-vault-200 mb-2">
                                Upload CSV, JSON, or Full Backup ZIP
                            </label>
                            <input type="file" @change="handleFileChange" accept=".csv,.json,.zip"
                                class="block w-full text-sm text-vault-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-vault-600 file:text-white hover:file:bg-vault-500 cursor-pointer" />
                        </div>

                        <!-- Error -->
                        <div v-if="importError" class="p-3 bg-rose-500/15 border border-rose-500/30 rounded-xl">
                            <p class="text-rose-400 text-sm">{{ importError }}</p>
                        </div>

                        <!-- Validate Button -->
                        <button @click="handleValidate" :disabled="!importFile || validating"
                            class="w-full px-4 py-2.5 bg-vault-600 text-white rounded-xl text-sm font-medium hover:bg-vault-500 transition-all disabled:opacity-50 disabled:cursor-wait flex items-center justify-center gap-2">
                            <div v-if="validating"
                                class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin">
                            </div>
                            {{ validating ? 'Validating...' : 'Validate File' }}
                        </button>

                        <!-- Preview Results -->
                        <div v-if="preview" class="bg-vault-700/50 border border-vault-600 rounded-xl p-4 space-y-3">
                            <h3 class="text-white font-medium text-sm">Validation Result</h3>
                            <div class="grid grid-cols-3 gap-3 text-center">
                                <div>
                                    <p class="text-2xl font-bold text-white">{{ preview.total }}</p>
                                    <p class="text-vault-400 text-xs">Total</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-emerald-400">{{ preview.valid }}</p>
                                    <p class="text-vault-400 text-xs">Valid</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-amber-400">{{ preview.duplicates }}</p>
                                    <p class="text-vault-400 text-xs">Duplicates</p>
                                </div>
                            </div>

                            <div v-if="preview.errors > 0" class="pt-2 border-t border-vault-600">
                                <p class="text-rose-400 text-xs font-medium mb-1">{{ preview.errors }} row(s) skipped
                                    due to
                                    errors:</p>
                                <ul class="text-vault-400 text-xs space-y-0.5 max-h-24 overflow-y-auto">
                                    <li v-for="(msg, i) in preview.error_messages" :key="i">• {{ msg }}</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Confirm Button -->
                        <button v-if="preview && preview.valid > 0" @click="handleConfirmImport" :disabled="importing"
                            class="w-full px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-semibold rounded-xl hover:from-emerald-400 hover:to-teal-400 transition-all disabled:opacity-50 disabled:cursor-wait flex items-center justify-center gap-2">
                            <div v-if="importing"
                                class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin">
                            </div>
                            {{ importing ? 'Importing...' : `Confirm Import (${preview.valid} items)` }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>