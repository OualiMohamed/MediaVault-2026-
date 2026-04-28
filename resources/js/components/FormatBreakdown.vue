<!-- resources/js/components/FormatBreakdown.vue -->
<script setup>
import { computed } from 'vue'

const props = defineProps({
    title: String,
    data: { type: Array, default: () => [] },
    color: { type: String, default: 'amber' },
})

const sorted = computed(() =>
    [...props.data].sort((a, b) => b.count - a.count)
)

const total = computed(() =>
    sorted.value.reduce((sum, item) => sum + item.count, 0)
)

const colorMap = {
    amber: { bg: 'bg-amber-500', light: 'bg-amber-500/20', text: 'text-amber-400' },
    sky: { bg: 'bg-sky-500', light: 'bg-sky-500/20', text: 'text-sky-400' },
    violet: { bg: 'bg-violet-500', light: 'bg-violet-500/20', text: 'text-violet-400' },
    emerald: { bg: 'bg-emerald-500', light: 'bg-emerald-500/20', text: 'text-emerald-400' },
}
const colors = computed(() => colorMap[props.color] || colorMap.amber)
</script>

<template>
    <div class="bg-vault-800 border border-vault-700 rounded-2xl p-6">
        <h3 class="text-white font-semibold mb-4">{{ title }}</h3>

        <div v-if="sorted.length === 0" class="text-vault-400 text-sm py-4">No items yet</div>

        <div v-else class="space-y-3">
            <div v-for="item in sorted" :key="item.format || item.platform" class="space-y-1.5">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-vault-200 font-medium">{{ item.format || item.platform }}</span>
                    <span :class="colors.text">{{ item.count }}</span>
                </div>
                <div class="h-2 bg-vault-700 rounded-full overflow-hidden">
                    <div :class="[colors.bg, 'h-full rounded-full transition-all duration-700']"
                        :style="{ width: (total > 0 ? (item.count / total * 100) : 0) + '%' }"></div>
                </div>
            </div>
        </div>
    </div>
</template>