// resources/js/api.js
import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

// Intercepteur de requêtes — attachement automatique du token
api.interceptors.request.use(config => {
  const token = localStorage.getItem('vault_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// intercepteur de réponse — 401 Redirection automatique de connexion
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