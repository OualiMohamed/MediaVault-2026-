<!-- resources/js/components/StatCard.vue -->
<script setup>
defineProps({
  label: String,
  value: [String, Number],
  color: { type: String, default: 'amber' },
  glow: { type: String, default: '' },
})
</script>

<template>
  <div :class="['bg-vault-800 border border-vault-700 rounded-xl p-5', glow]">
    <p class="text-vault-300 text-sm font-medium">{{ label }}</p>
    <p class="text-2xl font-bold mt-1" :class="{
      'text-amber-400': color === 'amber',
      'text-ember-500': color === 'ember',
      'text-rose-500': color === 'rose',
      'text-emerald-500': color === 'emerald',
      'text-sky-500': color === 'sky',
      'text-violet-500': color === 'violet',
    }">{{ value }}</p>
  </div>
</template>