// resources/js/stores/auth.js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('vault_token'))

  const isAuthenticated = computed(() => !!token.value)

  function setAuth(userData, authToken) {
    user.value = userData
    token.value = authToken
    localStorage.setItem('vault_token', authToken)
    axios.defaults.headers.common['Authorization'] = `Bearer ${authToken}`
  }

  function clearAuth() {
    user.value = null
    token.value = null
    localStorage.removeItem('vault_token')
    delete axios.defaults.headers.common['Authorization']
  }

  async function login(email, password) {
    const { data } = await axios.post('/api/login', { email, password })
    setAuth(data.user, data.token)
    return data
  }

  async function register(name, email, password) {
    const { data } = await axios.post('/api/register', { name, email, password })
    setAuth(data.user, data.token)
    return data
  }

  async function fetchUser() {
    if (!token.value) return
    try {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
      const { data } = await axios.get('/api/me')
      user.value = data
    } catch {
      clearAuth()
    }
  }

  async function logout() {
    try {
      await axios.post('/api/logout')
    } finally {
      clearAuth()
    }
  }

  return { user, token, isAuthenticated, login, register, fetchUser, logout, clearAuth }
})