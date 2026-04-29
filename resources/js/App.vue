<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from './stores/auth'
import AppLayout from './components/AppLayout.vue'

const auth = useAuthStore()
onMounted(() => { if (auth.token) auth.fetchUser() })
</script>

<template>
  <AppLayout>
    <router-view v-slot="{ Component, route }">
      <transition name="page" mode="out-in">
        <!-- :key forces a brand new component instance on every route change -->
        <component :is="Component" :key="route.fullPath" />
      </transition>
    </router-view>
  </AppLayout>
</template>