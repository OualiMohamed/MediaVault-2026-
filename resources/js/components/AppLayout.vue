<!-- resources/js/components/AppLayout.vue -->
<script setup>
import { useAuthStore } from '../stores/auth'
import { useRouter, useRoute } from 'vue-router'
import { computed } from 'vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const navItems = [
    { name: 'Dashboard', path: '/', icon: 'dashboard' },
    { name: 'Movies', path: '/movies', icon: 'movie' },
    { name: 'Books', path: '/books', icon: 'book' },
    { name: 'Games', path: '/games', icon: 'gamepad' },
    { name: 'Music', path: '/music', icon: 'music' },
    { name: 'Wishlist', path: '/wishlist', icon: 'heart' },
]

const currentPath = computed(() => route.path)
const isAuth = computed(() => auth.isAuthenticated)

async function handleLogout() {
    await auth.logout()
    router.push('/login')
}
</script>

<template>
    <div class="min-h-screen flex flex-col">
        <!-- barre de navigation supérieure -->
        <header v-if="isAuth"
            class="fixed top-0 left-0 right-0 z-50 bg-vault-900/90 backdrop-blur-xl border-b border-vault-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <router-link to="/" class="flex items-center gap-3 group">
                        <div
                            class="w-9 h-9 rounded-lg bg-gradient-to-br from-amber-500 to-ember-500 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <span
                            class="text-lg font-bold tracking-tight text-white group-hover:text-amber-400 transition-colors">
                            MediaVault
                        </span>
                    </router-link>

                    <!-- Liens de navigation -->
                    <nav class="hidden md:flex items-center gap-1">
                        <router-link v-for="item in navItems" :key="item.path" :to="item.path"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200" :class="currentPath === item.path
                                ? 'text-amber-400 bg-amber-500/10'
                                : 'text-vault-200 hover:text-white hover:bg-vault-700'">
                            {{ item.name }}
                        </router-link>
                    </nav>

                    <!-- Menu utilisateur -->
                    <div class="flex items-center gap-3">
                        <span v-if="auth.user" class="hidden sm:block text-sm text-vault-300">
                            {{ auth.user.name }}
                        </span>
                        <button @click="handleLogout"
                            class="px-3 py-1.5 rounded-lg text-sm text-vault-300 hover:text-white hover:bg-vault-700 transition-all">
                            Sign Out
                        </button>
                    </div>
                </div>
            </div>

            <!-- Navigation mobile en bas -->
            <nav
                class="md:hidden fixed bottom-0 left-0 right-0 bg-vault-900/95 backdrop-blur-xl border-t border-vault-700 z-50">
                <div class="flex justify-around py-2">
                    <router-link v-for="item in navItems.slice(0, 5)" :key="item.path" :to="item.path"
                        class="flex flex-col items-center gap-0.5 px-2 py-1 rounded-lg transition-colors"
                        :class="currentPath === item.path ? 'text-amber-400' : 'text-vault-400'">
                        <span class="text-lg">{{ navIcon(item.icon) }}</span>
                        <span class="text-[10px] font-medium">{{ item.name }}</span>
                    </router-link>
                </div>
            </nav>
        </header>

        <!-- Zone de contenu principale -->
        <main :class="isAuth ? 'pt-16 md:pt-16 pb-20 md:pb-0' : ''">
            <slot />
        </main>
    </div>
</template>

<script>
export default {
    methods: {
        navIcon(icon) {
            const icons = {
                dashboard: '\u2302',
                movie: '\u{1F3AC}',
                book: '\u{1F4D6}',
                gamepad: '\u{1F3AE}',
                music: '\u{1F3B5}',
                heart: '\u2665',
            }
            return icons[icon] || ''
        }
    }
}
</script>