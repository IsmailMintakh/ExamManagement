<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { ArrowLeftIcon, HomeIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    status: Number,
    message: String,
})

const info = computed(() => {
    const map = {
        500: {
            title: 'Server Error',
            subtitle: 'Something went wrong on our end.',
            description: 'We\'re sorry — an unexpected error occurred. Our team has been notified. Please try again in a moment.',
            code: '500',
            accent: 'error',
        },
        503: {
            title: 'Service Unavailable',
            subtitle: 'We\'ll be right back.',
            description: 'The system is down for maintenance. Please check back in a few minutes.',
            code: '503',
            accent: 'warning',
        },
        404: {
            title: 'Page Not Found',
            subtitle: 'This page doesn\'t exist.',
            description: 'The page you\'re looking for may have been moved or deleted. Check the URL or go back to dashboard.',
            code: '404',
            accent: 'info',
        },
        403: {
            title: 'Access Denied',
            subtitle: 'You don\'t have permission.',
            description: 'Your current role doesn\'t allow access to this resource. Contact your administrator if you think this is a mistake.',
            code: '403',
            accent: 'warning',
        },
        419: {
            title: 'Session Expired',
            subtitle: 'Please refresh the page.',
            description: 'Your session has timed out for security. Refresh the page and try again.',
            code: '419',
            accent: 'warning',
        },
        429: {
            title: 'Too Many Requests',
            subtitle: 'Slow down a bit.',
            description: 'You\'re making requests too fast. Please wait a moment and try again.',
            code: '429',
            accent: 'warning',
        },
    }
    return map[props.status] || map[500]
})

function goBack() {
    if (window.history.length > 1) window.history.back()
    else window.location.href = '/dashboard'
}

function refresh() {
    window.location.reload()
}
</script>

<template>
    <Head :title="info.title" />
    <div class="min-h-screen flex items-center justify-center bg-base-200 px-4 py-8">
        <div class="w-full max-w-md text-center">
            <!-- Status code -->
            <div class="relative mx-auto mb-6">
                <div class="text-[140px] font-extrabold leading-none tracking-tight text-gradient-primary" style="letter-spacing: -0.05em;">
                    {{ info.code }}
                </div>
                <div class="absolute inset-0 flex items-center justify-center opacity-[0.04] text-[200px] font-black pointer-events-none" style="line-height: 1;">
                    {{ info.code }}
                </div>
            </div>

            <h1 class="text-2xl font-extrabold tracking-tight">{{ info.title }}</h1>
            <p class="mt-2 text-sm font-semibold text-base-content/70">{{ info.subtitle }}</p>
            <p class="mt-4 text-[13px] leading-relaxed text-base-content/55">{{ info.description }}</p>

            <!-- Message from server (in dev only) -->
            <div v-if="message && message !== info.description" class="mt-5 rounded-xl border border-base-300/60 bg-base-100 p-3 text-xs text-base-content/55 text-left font-mono">
                {{ message }}
            </div>

            <!-- Actions -->
            <div class="mt-8 flex flex-wrap items-center justify-center gap-2">
                <button @click="goBack" class="btn btn-ghost btn-sm gap-1.5">
                    <ArrowLeftIcon class="w-4 h-4" /> Go Back
                </button>
                <button v-if="status === 419 || status === 500" @click="refresh" class="btn btn-outline btn-sm gap-1.5">
                    <ArrowPathIcon class="w-4 h-4" /> Refresh
                </button>
                <Link :href="route('dashboard')" class="btn btn-primary btn-sm gap-1.5">
                    <HomeIcon class="w-4 h-4" /> Dashboard
                </Link>
            </div>
        </div>
    </div>
</template>
