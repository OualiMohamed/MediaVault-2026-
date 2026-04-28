// resources/js/router/index.js
import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/',
    name: 'dashboard',
    component: () => import('../views/DashboardView.vue'),
    meta: { auth: true },
  },
  {
    path: '/movies',
    name: 'movies',
    component: () => import('../views/CollectionView.vue'),
    meta: { auth: true, type: 'movie' },
  },
  {
    path: '/books',
    name: 'books',
    component: () => import('../views/CollectionView.vue'),
    meta: { auth: true, type: 'book' },
  },
  {
    path: '/games',
    name: 'games',
    component: () => import('../views/CollectionView.vue'),
    meta: { auth: true, type: 'game' },
  },
  {
    path: '/music',
    name: 'music',
    component: () => import('../views/CollectionView.vue'),
    meta: { auth: true, type: 'music' },
  },
  {
    path: '/wishlist',
    name: 'wishlist',
    component: () => import('../views/WishlistView.vue'),
    meta: { auth: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('vault_token')
  if (to.meta.auth && !token) return next('/login')
  if (to.meta.guest && token) return next('/')
  next()
})

export default router