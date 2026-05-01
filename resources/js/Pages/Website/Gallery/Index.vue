<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    PlusIcon, PhotoIcon, PencilSquareIcon, TrashIcon,
    EyeIcon, EyeSlashIcon, CalendarDaysIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    albums: { type: Array, default: () => [] },
})

function destroy(id) {
    if (!confirm('Delete this album and all its photos?')) return
    router.delete(route('website.gallery.destroy', id), { preserveScroll: true })
}

function fmt(d) {
    if (!d) return null
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
    <Head title="Photo Gallery" />
    <AppLayout :breadcrumbs="[{ label: 'Website' }, { label: 'Gallery' }]">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold">Photo Gallery</h1>
                    <p class="text-sm text-base-content/60 mt-1">
                        Albums of school events, ceremonies, and campus life.
                    </p>
                </div>
                <Link :href="route('website.gallery.create')" class="btn btn-primary btn-sm gap-2">
                    <PlusIcon class="w-4 h-4" />
                    New Album
                </Link>
            </div>

            <EmptyState v-if="!albums.length"
                :icon="PhotoIcon"
                title="No albums yet"
                description="Create your first photo album. Each album can hold many photos with captions."
                action-text="Create First Album"
                :action-href="route('website.gallery.create')" />

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-for="album in albums" :key="album.id"
                    class="card bg-base-100 shadow-sm border border-base-200 hover:shadow-md hover:border-primary/30 transition-all overflow-hidden">
                    <div class="aspect-video relative bg-gradient-to-br from-emerald-700 to-slate-900 overflow-hidden">
                        <img v-if="album.cover_url" :src="album.cover_url" :alt="album.title"
                            class="absolute inset-0 w-full h-full object-cover" />
                        <PhotoIcon v-else class="absolute inset-0 m-auto w-16 h-16 text-white/30" />
                        <div v-if="!album.is_active" class="absolute inset-0 bg-black/60 flex items-center justify-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-white/80">Hidden</span>
                        </div>
                        <div class="absolute top-2 right-2 bg-black/60 backdrop-blur text-white text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-full">
                            {{ album.photos_count }} {{ album.photos_count === 1 ? 'photo' : 'photos' }}
                        </div>
                    </div>
                    <div class="card-body p-4 space-y-2">
                        <h3 class="font-bold text-base truncate">{{ album.title }}</h3>
                        <div class="flex items-center justify-between text-xs text-base-content/55">
                            <span v-if="album.event_date" class="inline-flex items-center gap-1">
                                <CalendarDaysIcon class="w-3.5 h-3.5" />
                                {{ fmt(album.event_date) }}
                            </span>
                            <span class="inline-flex items-center gap-1 font-bold"
                                :class="album.is_active ? 'text-success' : 'text-base-content/40'">
                                <component :is="album.is_active ? EyeIcon : EyeSlashIcon" class="w-3.5 h-3.5" />
                                {{ album.is_active ? 'Live' : 'Hidden' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-end gap-1 pt-2 border-t border-base-200">
                            <Link :href="route('website.gallery.edit', album.id)" class="btn btn-ghost btn-xs gap-1">
                                <PencilSquareIcon class="w-3.5 h-3.5" /> Manage
                            </Link>
                            <button @click="destroy(album.id)" class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                <TrashIcon class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
