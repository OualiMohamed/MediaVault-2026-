<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import Chart from 'chart.js/auto'

const props = defineProps({
    data: {
        type: Array,
        default: () => [],
        // Expected: [{ rating: 1, count: 5 }, { rating: 2, count: 3 }, ... { rating: null, count: 42 }]
    },
})

const canvas = ref(null)
let chart = null

function build() {
    if (!canvas.value || !props.data.length) return
    if (chart) chart.destroy()

    const labels = props.data.map(d => d.rating === null ? 'Unrated' : d.rating)
    const values = props.data.map(d => d.count)
    const colors = props.data.map(d =>
        d.rating === null ? '#44403c' : d.rating >= 8 ? '#f59e0b' : d.rating >= 5 ? '#78716c' : '#f43f5e'
    )

    chart = new Chart(canvas.value, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderRadius: 6,
                borderSkipped: false,
                maxBarThickness: 36,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#78716c',
                        font: { family: 'DM Sans', size: 11, weight: '500' },
                    },
                    border: { display: false },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255,255,255,0.04)', drawBorder: false },
                    ticks: {
                        color: '#78716c',
                        font: { family: 'DM Sans', size: 11 },
                        stepSize: 1,
                        precision: 0,
                    },
                    border: { display: false },
                },
            },
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
                        title(items) {
                            return items[0].label === 'Unrated' ? 'Unrated' : `Rating: ${items[0].label}/10`
                        },
                        label(ctx) {
                            return ` ${ctx.parsed.y} items`
                        },
                    },
                },
            },
            animation: { duration: 600, easing: 'easeOutQuart' },
        },
    })
}

onMounted(build)
onBeforeUnmount(() => { if (chart) chart.destroy() })
</script>

<template>
    <div>
        <div style="height: 200px;">
            <canvas ref="canvas"></canvas>
        </div>
        <div class="flex items-center gap-4 mt-3 text-xs text-vault-500">
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-sm" style="background: #f59e0b;"></span> 8-10
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-sm" style="background: #78716c;"></span> 5-7
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-sm" style="background: #f43f5e;"></span> 1-4
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-sm" style="background: #44403c;"></span> Unrated
            </span>
        </div>
    </div>
</template>