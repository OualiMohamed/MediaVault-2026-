<!-- resources/js/views/LoginView.vue -->
<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

const isRegister = ref(false)
const form = ref({ name: '', email: '', password: '', password_confirmation: '' })
const errors = ref({})
const submitting = ref(false)

async function handleSubmit() {
    errors.value = {}
    submitting.value = true
    try {
        if (isRegister.value) {
            await auth.register(
                form.value.name,
                form.value.email,
                form.value.password,
                form.value.password_confirmation
            )
        } else {
            await auth.login(form.value.email, form.value.password)
        }
        router.push('/')
    } catch (err) {
        // Handle validation errors (422)
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors
        }
        // Handle all other errors — show a general message
        else if (err.response) {
            errors.value = {
                _general: [err.response.data.message || 'Something went wrong. Please try again.'],
            }
        }
        // Handle network/offline errors
        else {
            errors.value = {
                _general: ['Network error. Is the server running?'],
            }
        }
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-10">
                <div
                    class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-500 to-ember-500 mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-white tracking-tight">MediaVault</h1>
                <p class="text-vault-300 mt-2">Your personal media collection tracker</p>
            </div>

            <!-- 表单卡片 -->
            <div class="bg-vault-800 border border-vault-700 rounded-2xl p-8">
                <h2 class="text-xl font-semibold text-white mb-6">
                    {{ isRegister ? 'Create Account' : 'Welcome Back' }}
                </h2>

                <form @submit.prevent="handleSubmit" class="space-y-5">
                    <div v-if="isRegister">
                        <label class="block text-sm font-medium text-vault-200 mb-1.5">Name</label>
                        <input v-model="form.name" type="text"
                            class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all"
                            placeholder="Your name" />
                        <p v-if="errors.name" class="text-rose-500 text-sm mt-1">{{ errors.name[0] }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-1.5">Email</label>
                        <input v-model="form.email" type="email"
                            class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all"
                            placeholder="you@example.com" />
                        <p v-if="errors.email" class="text-rose-500 text-sm mt-1">{{ errors.email[0] }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-vault-200 mb-1.5">Password</label>
                        <input v-model="form.password" type="password"
                            class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all"
                            placeholder="Min. 8 characters" />
                        <p v-if="errors.password" class="text-rose-500 text-sm mt-1">{{ errors.password[0] }}</p>
                    </div>

                    <div v-if="isRegister">
                        <label class="block text-sm font-medium text-vault-200 mb-1.5">Confirm Password</label>
                        <input v-model="form.password_confirmation" type="password"
                            class="w-full px-4 py-2.5 bg-vault-700 border border-vault-600 rounded-xl text-white placeholder-vault-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all"
                            placeholder="Re-enter password" />
                    </div>
                    <!-- General error message -->
                    <div v-if="errors._general"
                        class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm">
                        {{ errors._general[0] }}
                    </div>
                    <button type="submit" :disabled="submitting"
                        class="w-full py-3 bg-gradient-to-r from-amber-500 to-ember-500 text-white font-semibold rounded-xl hover:from-amber-400 hover:to-ember-400 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ submitting ? 'Please wait...' : (isRegister ? 'Create Account' : 'Sign In') }}
                    </button>
                </form>

                <p class="text-center text-sm text-vault-300 mt-6">
                    {{ isRegister ? 'Already have an account?' : "Don't have an account?" }}
                    <button @click="isRegister = !isRegister"
                        class="text-amber-400 hover:text-amber-300 font-medium ml-1">
                        {{ isRegister ? 'Sign In' : 'Register' }}
                    </button>
                </p>
            </div>
        </div>
    </div>
</template>