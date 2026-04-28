import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../api'          // <-- changed from 'axios'

export const useDashboardStore = defineStore('dashboard', () => {
  const stats = ref(null)
  const loading = ref(false)

  async function fetchStats() {
    loading.value = true
    try {
      const { data } = await api.get('/dashboard/stats')
      stats.value = data
    } catch (err) {
      console.error('Failed to fetch dashboard stats:', err)
    } finally {
      loading.value = false
    }
  }

  return { stats, loading, fetchStats }
})