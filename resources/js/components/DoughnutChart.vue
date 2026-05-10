<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import Chart from 'chart.js/auto'

const props = defineProps({
    movie: { type: Number, default: 0 },
    tvShow: { type: Number, default: 0 },
    game: { type: Number, default: 0 },
    book: { type: Number, default: 0 },
    music: { type: Number, default: 0 },
})

const canvas = ref(null)
let chart = null

const total = computed(() => props.movie + props.tvShow + props.game + props.book + props.music)

const segments = computed(() => [
    { label: 'Movies', value: props.movie, color: '#f59e0b' },
    { label: 'TV Shows', value: props.tvShow, color: '#f43f5e' },
    { label: 'Games', value: props.game, color: '#0ea5e9' },
    { label: 'Books', value: props.book, color: '#10b981' },
    { label: 'Music', value: props.music, color: '#8b5cf6' },
])

function build() {
    if (!canvas.value) return
    if (chart) chart.destroy()

    chart = new Chart(canvas.value, {
        type: 'doughnut',
        data: {
            labels: segments.value.map(s => s.label),
            datasets: [{
                data: segments.value.map(s => s.value),
                backgroundColor: segments.value.map(s => s.color),
                borderWidth: 0,
                hoverOffset: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '72%',
            layout: { padding: 4 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1c1917',
                    titleColor: '#fafaf9',
                    bodyColor: '#a8a29e',
                    borderColor: '#292524',
                    borderWidth: 1,
                    cornerRadius: 10,
                    padding: 12,
                    bodyFont: { family: 'DM Sans', size: 12 },
                    titleFont: { family: 'Space Grotesk', weight: '600', size: 13 },
                    callbacks: {
                        label(ctx) {
                            const pct = total.value ? ((ctx.parsed / total.value) * 100).toFixed(1) : 0
                            return ` ${ctx.parsed} items (${pct}%)`
                        },
                    },
                },
            },
            animation: { duration: 700, easing: 'easeOutQuart' },
        },
        // Center text plugin
        plugins: [{
            id: 'centerText',
            afterDraw(chart) {
                const { ctx, chartArea: { width, height, top, left } } = chart
                const cx = left + width / 2
                const cy = top + height / 2

                ctx.save()
                ctx.textAlign = 'center'
                ctx.textBaseline = 'middle'

                ctx.font = '700 26px "Space Grotesk", sans-serif'
                ctx.fillStyle = '#fafaf9'
                ctx.fillText(total.value, cx, cy - 8)

                ctx.font = '400 11px "DM Sans", sans-serif'
                ctx.fillStyle = '#78716c'
                ctx.fillText('total items', cx, cy + 14)

                ctx.restore()
            },
        }],
    })
}

onMounted(build)
onBeforeUnmount(() => { if (chart) chart.destroy() })
</script>

<template>
    <div>
        <div class="flex justify-center" style="max-width: 210px; margin: 0 auto;">
            <canvas ref="canvas"></canvas>
        </div>

        <!-- Legend -->
        <div class="grid grid-cols-2 gap-x-4 gap-y-2 mt-5">
            <div v-for="seg in segments" :key="seg.label" class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-sm flex-shrink-0" :style="{ background: seg.color }"></span>
                <span class="text-xs text-vault-400 truncate">{{ seg.label }}</span>
                <span class="text-xs font-semibold text-white ml-auto tabular-nums">{{ seg.value }}</span>
            </div>
        </div>
    </div>
</template>