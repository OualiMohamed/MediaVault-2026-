// resources/js/api.js
import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  withCredentials: false,
})

// Attach token from localStorage on every request
api.interceptors.request.use(config => {
  const token = localStorage.getItem('vault_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// 401 → clear token and redirect to login
api.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      localStorage.removeItem('vault_token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api