import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../api'          // <-- changed from 'axios'

export const useCollectionStore = defineStore('collection', () => {
  const items = ref([])
  const loading = ref(false)
  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 24,
    total: 0,
  })

  async function fetchItems(type, params = {}) {
    loading.value = true
    try {
      const { data } = await api.get(`/collection/${type}`, { params })
      items.value = data.data
      pagination.value = {
        current_page: data.current_page,
        last_page: data.last_page,
        per_page: data.per_page,
        total: data.total,
      }
    } catch (err) {
      console.error('Failed to fetch items:', err)
    } finally {
      loading.value = false
    }
  }

  async function createItem(type, formData) {
    const { data } = await api.post(`/collection/${type}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    items.value.unshift(data)
    return data
  }

  async function updateItem(type, id, formData) {
    // _method must go IN the form body, not as a query parameter
    formData.append('_method', 'PUT')
    const { data } = await api.post(`/collection/${type}/${id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    const idx = items.value.findIndex(i => i.id === id)
    if (idx !== -1) items.value[idx] = data
    return data
  }

  async function deleteItem(type, id) {
    await api.delete(`/collection/${type}/${id}`)
    items.value = items.value.filter(i => i.id !== id)
  }

  return { items, loading, pagination, fetchItems, createItem, updateItem, deleteItem }
})