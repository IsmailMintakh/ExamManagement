<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { onMounted } from 'vue'
import {
    AcademicCapIcon, EnvelopeIcon, LockClosedIcon, ArrowRightIcon,
    SparklesIcon, ChartBarIcon, ShieldCheckIcon, BuildingLibraryIcon,
    EyeIcon, EyeSlashIcon, CheckBadgeIcon,
} from '@heroicons/vue/24/outline'
import { ref } from 'vue'

defineProps({
    canResetPassword: Boolean,
    status: String,
})

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const showPassword = ref(false)

onMounted(() => {
    const saved = localStorage.getItem('theme') || 'light'
    document.documentElement.setAttribute('data-theme', saved)
})

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}

function quickLogin(email) {
    form.email = email
    form.password = 'password'
    submit()
}

const demoAccounts = [
    { label: 'DDO', sub: 'District Officer', email: 'ddo@exam.com', color: 'emerald' },
    { label: 'Principal', sub: 'School Admin', email: 'principal@gbhs-lhr.edu.pk', color: 'amber' },
    { label: 'Class Teacher', sub: 'Section Lead', email: 'gbhs-lhr-ct-class.6-a@school.edu.pk', color: 'sky' },
    { label: 'Subject Teacher', sub: 'Marks Entry', email: 'gbhs-lhr-st-math-6@school.edu.pk', color: 'violet' },
]

const stats = [
    { value: '72', label: 'Years of Excellence' },
    { value: '1,248', label: 'Students Enrolled' },
    { value: '#2', label: 'In Gilgit-Baltistan' },
]
</script>

<template>
    <Head title="Sign In" />

    <div class="min-h-screen flex bg-stone-50">
        <!-- ═══════════ LEFT: Hero ═══════════ -->
        <div class="relative hidden lg:flex w-1/2 flex-col justify-between overflow-hidden text-white p-12 xl:p-14">
            <!-- Layered backdrop -->
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900"></div>
            <div class="absolute inset-0 opacity-50">
                <div class="absolute -top-32 -right-32 w-[36rem] h-[36rem] bg-emerald-600/30 rounded-full blur-[140px] animate-float"></div>
                <div class="absolute -bottom-32 -left-32 w-[28rem] h-[28rem] bg-amber-500/15 rounded-full blur-[120px] animate-float" style="animation-delay: 2s"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[24rem] h-[24rem] bg-sky-500/10 rounded-full blur-[100px] animate-float" style="animation-delay: 4s"></div>
            </div>

            <!-- Mountain silhouette evoking Karakoram -->
            <svg class="absolute bottom-0 left-0 w-full h-56 opacity-25" viewBox="0 0 1440 400" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path fill="#0f172a" d="M0,400 L0,250 L120,180 L220,220 L340,120 L460,200 L560,90 L680,180 L800,60 L920,160 L1040,100 L1160,200 L1280,130 L1440,180 L1440,400 Z"/>
                <path fill="#020617" opacity="0.85" d="M0,400 L0,300 L80,260 L180,290 L280,220 L400,280 L520,230 L640,290 L780,250 L900,310 L1040,270 L1180,320 L1320,280 L1440,310 L1440,400 Z"/>
            </svg>

            <!-- Subtle grid -->
            <svg class="absolute inset-0 w-full h-full opacity-[0.04]" xmlns="http://www.w3.org/2000/svg">
                <pattern id="grid" width="60" height="60" patternUnits="userSpaceOnUse">
                    <path d="M 60 0 L 0 0 0 60" fill="none" stroke="white" stroke-width="0.5" />
                </pattern>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>

            <!-- Top brand row -->
            <div class="relative z-10 flex items-center gap-3">
                <div class="relative w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-800 flex items-center justify-center shadow-xl shadow-emerald-900/40">
                    <AcademicCapIcon class="w-6 h-6 text-white" />
                    <span class="absolute -top-1 -right-1 w-3 h-3 rounded-full bg-amber-400 ring-2 ring-slate-900"></span>
                </div>
                <div>
                    <div class="text-base font-bold leading-tight tracking-tight">GBHSS No.1 Skardu</div>
                    <div class="text-[10px] text-amber-300 font-semibold uppercase tracking-[0.2em] mt-0.5">Government Boys · Since 1954</div>
                </div>
            </div>

            <!-- Main message -->
            <div class="relative z-10 max-w-lg">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 backdrop-blur-sm text-[11px] font-semibold mb-7">
                    <span class="flex items-center gap-1.5">
                        <span class="relative flex w-1.5 h-1.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-400"></span></span>
                        <span class="text-emerald-100 tracking-wider uppercase">Examination Management System</span>
                    </span>
                </div>

                <h1 class="font-black text-5xl xl:text-6xl leading-[1.05] tracking-tight">
                    Where mountains
                    <br />
                    <span class="bg-gradient-to-r from-amber-300 via-amber-400 to-emerald-300 bg-clip-text text-transparent animate-gradient">meet excellence.</span>
                </h1>

                <p class="mt-7 text-lg text-stone-300 leading-relaxed font-light max-w-md">
                    The official examination management portal for Government Boys Higher Secondary School No.1, Skardu — Gilgit-Baltistan.
                </p>

                <!-- Live stats strip -->
                <div class="mt-10 grid grid-cols-3 gap-4 max-w-lg">
                    <div v-for="(s, i) in stats" :key="s.label"
                        class="border-l-2 pl-3"
                        :class="i === 0 ? 'border-amber-400' : i === 1 ? 'border-emerald-400' : 'border-sky-400'">
                        <div class="text-2xl font-extrabold tabular-nums">{{ s.value }}</div>
                        <div class="text-[10px] uppercase tracking-[0.15em] text-stone-400 font-semibold mt-1">{{ s.label }}</div>
                    </div>
                </div>
            </div>

            <!-- Bottom credits -->
            <div class="relative z-10 flex items-center justify-between text-[11px] text-stone-500 font-medium">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1.5"><CheckBadgeIcon class="w-3.5 h-3.5 text-amber-400" /> FBISE Affiliated</span>
                    <span class="hidden xl:flex items-center gap-1.5"><ShieldCheckIcon class="w-3.5 h-3.5 text-emerald-400" /> Government of GB</span>
                </div>
                <span>&copy; {{ new Date().getFullYear() }} GBHSS Skardu</span>
            </div>
        </div>

        <!-- ═══════════ RIGHT: Form ═══════════ -->
        <div class="flex flex-1 items-center justify-center px-6 py-12 sm:px-10 lg:px-12">
            <div class="w-full max-w-md">
                <!-- Mobile brand -->
                <div class="mb-10 text-center lg:hidden">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-900 shadow-xl shadow-emerald-900/30">
                        <AcademicCapIcon class="h-7 w-7 text-white" />
                    </div>
                    <h1 class="text-xl font-black tracking-tight">GBHSS No.1 Skardu</h1>
                    <p class="mt-1.5 text-[10px] uppercase tracking-[0.2em] font-bold text-emerald-700">Examination Portal</p>
                </div>

                <!-- Welcome -->
                <div class="mb-10 hidden lg:block">
                    <div class="text-[11px] uppercase tracking-[0.2em] font-bold text-emerald-700 mb-2">Welcome Back</div>
                    <h2 class="text-3xl font-black text-slate-900 tracking-tight">Sign in to continue.</h2>
                    <p class="mt-2 text-sm text-slate-500">Enter your credentials to access your role-specific dashboard.</p>
                </div>

                <!-- Status banner -->
                <div v-if="status" class="mb-5 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                    {{ status }}
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Email Address
                        </label>
                        <div class="relative">
                            <EnvelopeIcon class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="email" v-model="form.email"
                                class="w-full pl-11 pr-4 py-3 rounded-xl border bg-white text-sm transition-all focus:outline-none focus:ring-4 focus:ring-emerald-500/15 focus:border-emerald-500"
                                :class="form.errors.email ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500/15' : 'border-stone-200'"
                                placeholder="you@school.edu.pk"
                                required autofocus autocomplete="username"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-rose-600 font-medium">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Password</label>
                            <Link v-if="canResetPassword" :href="route('password.request')" class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 hover:underline">
                                Forgot?
                            </Link>
                        </div>
                        <div class="relative">
                            <LockClosedIcon class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                :type="showPassword ? 'text' : 'password'" v-model="form.password"
                                class="w-full pl-11 pr-11 py-3 rounded-xl border bg-white text-sm transition-all focus:outline-none focus:ring-4 focus:ring-emerald-500/15 focus:border-emerald-500"
                                :class="form.errors.password ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500/15' : 'border-stone-200'"
                                placeholder="Enter your password"
                                required autocomplete="current-password"
                            />
                            <button type="button" @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-700 transition-colors"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'">
                                <EyeSlashIcon v-if="showPassword" class="w-4 h-4" />
                                <EyeIcon v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1.5 text-xs text-rose-600 font-medium">{{ form.errors.password }}</p>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2.5 select-none pt-1">
                        <input type="checkbox" v-model="form.remember" class="w-4 h-4 rounded border-stone-300 text-emerald-600 focus:ring-2 focus:ring-emerald-500/30" />
                        <span class="text-sm text-slate-600">Keep me signed in for 30 days</span>
                    </label>

                    <button type="submit"
                        class="group w-full flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-6 py-3.5 rounded-xl font-semibold text-sm shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-60 disabled:cursor-not-allowed disabled:transform-none"
                        :disabled="form.processing">
                        <span v-if="form.processing" class="loading loading-spinner loading-sm" />
                        <template v-else>
                            Sign In to Dashboard
                            <ArrowRightIcon class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
                        </template>
                    </button>
                </form>

                <!-- Quick demo logins -->
                <div class="mt-10">
                    <div class="relative mb-5 flex items-center">
                        <div class="flex-1 border-t border-stone-200" />
                        <p class="px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Demo Accounts</p>
                        <div class="flex-1 border-t border-stone-200" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button v-for="d in demoAccounts" :key="d.label" @click="quickLogin(d.email)"
                            class="group rounded-xl border border-stone-200 hover:border-emerald-300 hover:bg-emerald-50/50 px-3 py-2.5 text-left transition-all duration-300 hover:shadow-md hover:-translate-y-0.5">
                            <div class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full"
                                    :class="{
                                        'bg-emerald-500': d.color === 'emerald',
                                        'bg-amber-500': d.color === 'amber',
                                        'bg-sky-500': d.color === 'sky',
                                        'bg-violet-500': d.color === 'violet',
                                    }"></span>
                                <p class="text-[12.5px] font-bold text-slate-900">{{ d.label }}</p>
                            </div>
                            <p class="text-[10px] text-slate-500 mt-0.5">{{ d.sub }}</p>
                        </button>
                    </div>
                    <p class="mt-3 text-center text-[10px] text-slate-400 font-medium">All demo accounts use password: <span class="font-bold text-slate-600">password</span></p>
                </div>

                <!-- Public site link -->
                <div class="mt-8 text-center">
                    <Link href="/" class="text-xs text-slate-500 hover:text-emerald-700 inline-flex items-center gap-1.5 transition-colors">
                        ← Back to school website
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes gradient-shift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}
.animate-gradient {
    background-size: 200% 200%;
    animation: gradient-shift 8s ease infinite;
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-12px); }
}
.animate-float { animation: float 5s ease-in-out infinite; }
</style>
