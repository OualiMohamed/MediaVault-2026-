<script setup>
import GlobalSearch from '../components/GlobalSearch.vue'
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useRouter, useRoute } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const mobileMenuOpen = ref(false)

const navItems = [
    { name: 'Dashboard', path: '/', icon: 'dashboard' },
    { name: 'Movies', path: '/movies', icon: 'movie' },
    { name: 'TV Shows', path: '/tv-shows', icon: 'tv' },
    { name: 'Books', path: '/books', icon: 'book' },
    { name: 'Games', path: '/games', icon: 'gamepad' },
    { name: 'Music', path: '/music', icon: 'music' },
    { name: 'Wishlist', path: '/wishlist', icon: 'heart' },
]
const currentPath = computed(() => route.path)
const isAuth = computed(() => auth.isAuthenticated)

// Close mobile menu when the route changes (after navigation completes)
watch(() => route.path, () => {
    mobileMenuOpen.value = false
})

function handleClickOutside() {
    mobileMenuOpen.value = false
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
})

function toggleMobileMenu(e) {
    e.stopPropagation()
    mobileMenuOpen.value = !mobileMenuOpen.value
}

async function handleLogout() {
    mobileMenuOpen.value = false
    await auth.logout()
    router.push('/login')
}

function navIcon(icon) {
    const icons = {
        dashboard: '\u2302',
        movie: '\u{1F3AC}',
        book: '\u{1F4D6}',
        gamepad: '\u{1F3AE}',
        tv: '\u{1F4FA}',        // <-- ADD TV icon
        music: '\u{1F3B5}',
        heart: '\u2665',
    }
    return icons[icon] || ''
}

const bottomNavItems = computed(() => navItems.slice(0, 5))
</script>

<template>
    <div class="min-h-screen flex flex-col">
        <!-- ═══ Top Navigation Bar ═══ -->
        <header v-if="isAuth"
            class="fixed top-0 left-0 right-0 z-50 bg-vault-900/90 backdrop-blur-xl border-b border-vault-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between h-14 sm:h-16 gap-3">
                    <!-- Logo -->
                    <router-link to="/" class="flex items-center gap-2.5 group flex-shrink-0">
                        <div
                            class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-gradient-to-br from-amber-500 to-ember-500 flex items-center justify-center">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <span
                            class="hidden sm:inline text-base sm:text-lg font-bold tracking-tight text-white group-hover:text-amber-400 transition-colors">
                            MediaVault
                        </span>
                    </router-link>

                    <!-- Desktop nav links -->
                    <nav class="hidden md:flex items-center gap-1">
                        <router-link v-for="item in navItems" :key="item.path" :to="item.path"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200" :class="currentPath === item.path
                                ? 'text-amber-400 bg-amber-500/10'
                                : 'text-vault-200 hover:text-white hover:bg-vault-700'">
                            {{ item.name }}
                        </router-link>
                    </nav>

                    <!-- Right side: Search + User Menu -->
                    <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                        <!-- Global Search (ALWAYS VISIBLE) -->
                        <GlobalSearch />

                        <!-- Desktop user menu -->
                        <div class="hidden sm:flex items-center gap-3">
                            <span v-if="auth.user" class="text-sm text-vault-300">
                                {{ auth.user.name }}
                            </span>
                            <button @click="handleLogout"
                                class="px-3 py-1.5 rounded-lg text-sm text-vault-300 hover:text-white hover:bg-vault-700 transition-all">
                                Sign Out
                            </button>
                        </div>

                        <!-- Mobile user menu button -->
                        <button @click="toggleMobileMenu"
                            class="sm:hidden w-9 h-9 rounded-lg flex items-center justify-center text-vault-300 hover:text-white hover:bg-vault-700 transition-all relative flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span v-if="mobileMenuOpen"
                                class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-amber-500"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile dropdown menu (unchanged) -->
            <transition name="mobile-menu">
                <div v-if="mobileMenuOpen" class="sm:hidden bg-vault-800 border-b border-vault-700 shadow-xl"
                    @click.stop>
                    <div class="px-4 py-3 border-b border-vault-700">
                        <p v-if="auth.user" class="text-white text-sm font-medium">{{ auth.user.name }}</p>
                        <p v-if="auth.user" class="text-vault-400 text-xs mt-0.5">{{ auth.user.email }}</p>
                    </div>

                    <router-link to="/music"
                        class="flex items-center gap-3 px-4 py-3 text-vault-200 hover:text-white hover:bg-vault-700 transition-colors"
                        :class="currentPath === '/music' ? 'text-amber-400 bg-amber-500/10' : ''">
                        <span class="text-base">{{ navIcon('music') }}</span>
                        <span class="text-sm font-medium">Music</span>
                    </router-link>

                    <router-link to="/wishlist"
                        class="flex items-center gap-3 px-4 py-3 text-vault-200 hover:text-white hover:bg-vault-700 transition-colors"
                        :class="currentPath === '/wishlist' ? 'text-amber-400 bg-amber-500/10' : ''">
                        <span class="text-base">{{ navIcon('heart') }}</span>
                        <span class="text-sm font-medium">Wishlist</span>
                    </router-link>

                    <button @click="handleLogout"
                        class="w-full flex items-center gap-3 px-4 py-3 text-rose-400 hover:text-rose-300 hover:bg-vault-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span class="text-sm font-medium">Sign Out</span>
                    </button>
                </div>
            </transition>
        </header>

        <!-- ═══ Mobile Bottom Navigation ═══ -->
        <nav v-if="isAuth"
            class="sm:hidden fixed bottom-0 left-0 right-0 bg-vault-900/95 backdrop-blur-xl border-t border-vault-700 z-50">
            <div class="flex justify-around py-1.5 px-1">
                <router-link v-for="item in bottomNavItems" :key="item.path" :to="item.path"
                    class="flex flex-col items-center gap-0.5 px-2 py-1.5 rounded-lg transition-colors min-w-0"
                    :class="currentPath === item.path ? 'text-amber-400' : 'text-vault-400'">
                    <span class="text-lg leading-none">{{ navIcon(item.icon) }}</span>
                    <span class="text-[10px] font-medium truncate w-full text-center">{{ item.name }}</span>
                </router-link>
            </div>
        </nav>

        <!-- ═══ Main Content ═══ -->
        <main :class="isAuth ? 'pt-14 sm:pt-16 pb-16 sm:pb-0' : ''">
            <slot />
        </main>
    </div>
</template>

<style scoped>
.mobile-menu-enter-active,
.mobile-menu-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
    transform-origin: top;
}

.mobile-menu-enter-from,
.mobile-menu-leave-to {
    opacity: 0;
    transform: scaleY(0.95) translateY(-4px);
}
</style>