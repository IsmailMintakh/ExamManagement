<script setup>
import { ref, onMounted } from 'vue'
import { SunIcon, MoonIcon } from '@heroicons/vue/24/outline'

const isDark = ref(false)

function setTheme(dark) {
    isDark.value = dark
    const theme = dark ? 'dark' : 'light'
    document.documentElement.setAttribute('data-theme', theme)
    document.documentElement.classList.toggle('dark', dark)
    localStorage.setItem('theme', theme)
}

function toggle() {
    setTheme(!isDark.value)
}

onMounted(() => {
    const saved = localStorage.getItem('theme')
    if (saved) {
        setTheme(saved === 'dark')
    } else {
        setTheme(window.matchMedia('(prefers-color-scheme: dark)').matches)
    }
})
</script>

<template>
    <button
        @click="toggle"
        class="btn btn-ghost btn-sm btn-square relative overflow-hidden"
        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        :title="isDark ? 'Light mode' : 'Dark mode'"
    >
        <Transition
            mode="out-in"
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 rotate-180 scale-50"
            enter-to-class="opacity-100 rotate-0 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 rotate-0 scale-100"
            leave-to-class="opacity-0 -rotate-180 scale-50"
        >
            <SunIcon v-if="isDark" class="h-[18px] w-[18px] text-amber-400" />
            <MoonIcon v-else class="h-[18px] w-[18px] text-indigo-500" />
        </Transition>
    </button>
</template>
