// resources/js/stores/dashboard.js
import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const useDashboardStore = defineStore('dashboard', () => {
  const stats = ref(null)
  const loading = ref(false)

  async function fetchStats() {
    loading.value = true
    try {
      const { data } = await axios.get('/api/dashboard/stats')
      stats.value = data
    } catch (err) {
      console.error('Failed to fetch dashboard stats:', err)
    } finally {
      loading.value = false
    }
  }

  return { stats, loading, fetchStats }
})