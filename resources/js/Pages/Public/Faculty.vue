<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import PublicHero from '@/Components/PublicHero.vue'
import PageBlocks from '@/Components/PageBlocks.vue'
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AcademicCapIcon, StarIcon, EnvelopeIcon, PhoneIcon, UserGroupIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    site:      { type: Object, default: () => ({}) },
    hero:      { type: Object, default: () => ({}) },
    members:   { type: Array,  default: () => [] },
    principal: { type: Object, default: null },
    blocks:    { type: Array,  default: () => [] },
})

// Group remaining (non-principal) members by department
const grouped = computed(() => {
    const groups = {}
    props.members.filter(m => !m.is_principal).forEach(m => {
        const key = m.department || 'Other'
        if (!groups[key]) groups[key] = []
        groups[key].push(m)
    })
    return groups
})

function initials(name) {
    return (name || '?').split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase()
}

const gradients = [
    'from-emerald-600 to-emerald-900',
    'from-amber-600 to-amber-900',
    'from-sky-600 to-indigo-900',
    'from-violet-600 to-purple-900',
    'from-rose-600 to-pink-900',
    'from-teal-600 to-emerald-900',
    'from-indigo-600 to-blue-900',
    'from-green-700 to-teal-900',
]
</script>

<template>
    <Head :title="hero.meta_title || 'Faculty'">
        <meta v-if="hero.meta_description" name="description" :content="hero.meta_description" />
    </Head>
    <PublicLayout>
        <PublicHero :hero="hero" :fallback="{
            eyebrow: 'Our Educators',
            title: members.length ? `${members.length} teachers.` : 'Our teachers.',
            accent: 'One mission.',
            subtitle: 'Every member of our faculty is a mentor, a guide, and a friend.',
        }" />

        <!-- Empty state -->
        <section v-if="!members.length" class="py-32 bg-white">
            <div class="max-w-3xl mx-auto px-6 text-center">
                <UserGroupIcon class="w-16 h-16 mx-auto text-stone-300" />
                <h2 class="mt-6 text-2xl font-bold text-slate-900">Faculty profiles coming soon</h2>
                <p class="mt-3 text-slate-600">We're updating our records — please check back shortly.</p>
            </div>
        </section>

        <!-- Principal -->
        <section v-if="principal" class="py-20 bg-white">
            <div class="max-w-5xl mx-auto px-6 lg:px-10">
                <div class="text-center mb-12 reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">Leadership</div>
                    <h2 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">A message of leadership</h2>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-10 items-center reveal">
                    <div class="lg:col-span-2">
                        <div class="aspect-[4/5] rounded-3xl overflow-hidden bg-gradient-to-br from-emerald-700 to-slate-900 shadow-2xl flex items-center justify-center text-white text-6xl font-black">
                            <img v-if="principal.photo_url" :src="principal.photo_url" :alt="principal.name" class="w-full h-full object-cover" />
                            <span v-else>{{ initials(principal.name) }}</span>
                        </div>
                    </div>
                    <div class="lg:col-span-3">
                        <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-3">Principal</div>
                        <h3 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">{{ principal.name }}</h3>
                        <p v-if="principal.designation" class="mt-2 text-emerald-700 font-semibold uppercase tracking-wider text-sm">{{ principal.designation }}</p>
                        <div v-if="principal.qualification" class="mt-3 text-sm text-slate-600">{{ principal.qualification }}</div>
                        <p v-if="principal.bio" class="mt-6 text-[15px] leading-[1.85] text-slate-700 whitespace-pre-line">{{ principal.bio }}</p>
                        <div class="mt-6 flex flex-wrap gap-3 text-sm">
                            <a v-if="principal.email" :href="`mailto:${principal.email}`" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-stone-100 text-slate-700 hover:bg-stone-200 font-medium">
                                <EnvelopeIcon class="w-4 h-4" /> {{ principal.email }}
                            </a>
                            <a v-if="principal.phone" :href="`tel:${principal.phone}`" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-stone-100 text-slate-700 hover:bg-stone-200 font-medium">
                                <PhoneIcon class="w-4 h-4" /> {{ principal.phone }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Faculty by department -->
        <section v-for="(deptMembers, dept, di) in grouped" :key="dept"
                 class="py-16"
                 :class="di % 2 === 0 ? 'bg-stone-50' : 'bg-white'">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="flex items-end justify-between gap-4 mb-10 reveal">
                    <div>
                        <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-3">Department</div>
                        <h2 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">{{ dept }}</h2>
                    </div>
                    <div class="text-sm text-slate-500 hidden sm:block">{{ deptMembers.length }} {{ deptMembers.length === 1 ? 'member' : 'members' }}</div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div v-for="(m, i) in deptMembers" :key="m.id"
                         class="group reveal-stagger" :style="`--delay: ${(i % 4) * 80}ms`">
                        <div class="bg-white rounded-3xl border border-stone-100 shadow-sm group-hover:shadow-2xl group-hover:-translate-y-1 transition-all duration-500 overflow-hidden">
                            <div class="aspect-square bg-gradient-to-br relative overflow-hidden flex items-center justify-center text-white text-5xl font-black"
                                 :class="gradients[(m.id || i) % gradients.length]">
                                <img v-if="m.photo_url" :src="m.photo_url" :alt="m.name" class="w-full h-full object-cover" />
                                <span v-else>{{ initials(m.name) }}</span>
                                <StarIcon v-if="m.is_featured" class="absolute top-3 right-3 w-5 h-5 text-amber-300 fill-amber-300" />
                            </div>
                            <div class="p-5">
                                <h3 class="font-bold text-slate-900 text-base leading-tight">{{ m.name }}</h3>
                                <p v-if="m.designation" class="text-[12px] text-emerald-700 font-semibold mt-1">{{ m.designation }}</p>
                                <p v-if="m.qualification" class="text-[11px] text-slate-500 mt-2 line-clamp-2">{{ m.qualification }}</p>
                                <div v-if="m.years_experience" class="mt-3 inline-flex items-center gap-1 text-[10px] uppercase tracking-wider text-amber-600 font-bold">
                                    <AcademicCapIcon class="w-3 h-3" />
                                    {{ m.years_experience }}+ years experience
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Custom admin-managed blocks -->
        <PageBlocks :blocks="blocks" />
    </PublicLayout>
</template>
