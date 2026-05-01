<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import {
    PlusIcon, NewspaperIcon, PencilSquareIcon, TrashIcon,
    StarIcon, EyeIcon, EyeSlashIcon, MagnifyingGlassIcon, PhotoIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    articles:   { type: Object, default: () => ({ data: [] }) },
    filters:    { type: Object, default: () => ({}) },
    categories: { type: Array,  default: () => [] },
})

const search = ref(props.filters.search || '')
const category = ref(props.filters.category || '')

let debounceTimer = null
watch([search, category], () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('website.news.index'),
            { search: search.value || undefined, category: category.value || undefined },
            { preserveState: true, preserveScroll: true, replace: true })
    }, 300)
})

function destroy(id) {
    if (!confirm('Delete this article?')) return
    router.delete(route('website.news.destroy', id), { preserveScroll: true })
}

function fmt(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
    <Head title="News & Articles" />
    <AppLayout :breadcrumbs="[{ label: 'Website' }, { label: 'News' }]">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold">News &amp; Articles</h1>
                    <p class="text-sm text-base-content/60 mt-1">
                        Articles shown on the public News page and homepage.
                    </p>
                </div>
                <Link :href="route('website.news.create')" class="btn btn-primary btn-sm gap-2">
                    <PlusIcon class="w-4 h-4" />
                    New Article
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[220px] max-w-md">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40" />
                    <input v-model="search" type="text" placeholder="Search title or excerpt…"
                        class="input input-bordered input-sm w-full pl-9 text-sm" />
                </div>
                <select v-model="category" class="select select-bordered select-sm text-sm">
                    <option value="">All categories</option>
                    <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                </select>
            </div>

            <EmptyState v-if="!articles.data?.length"
                :icon="NewspaperIcon"
                title="No articles yet"
                description="Publish your first article. It'll show on the public News page and (if featured) on the homepage."
                action-text="Publish First Article"
                :action-href="route('website.news.create')" />

            <template v-else>
                <div class="card bg-base-100 shadow-sm border border-base-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider text-base-content/50">
                                    <th class="w-16"></th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Published</th>
                                    <th class="w-24 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="a in articles.data" :key="a.id" class="hover">
                                    <td>
                                        <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-emerald-700 to-slate-900 overflow-hidden flex items-center justify-center">
                                            <img v-if="a.image_url" :src="a.image_url" :alt="a.title" class="w-full h-full object-cover" />
                                            <PhotoIcon v-else class="w-5 h-5 text-white/40" />
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <StarIcon v-if="a.is_featured" class="w-4 h-4 text-amber-500 shrink-0" />
                                            <div class="min-w-0">
                                                <div class="font-semibold text-sm truncate max-w-md">{{ a.title }}</div>
                                                <div v-if="a.excerpt" class="text-xs text-base-content/50 truncate max-w-md mt-0.5">
                                                    {{ a.excerpt }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-ghost badge-sm">{{ a.category }}</span>
                                    </td>
                                    <td>
                                        <span v-if="a.is_published" class="inline-flex items-center gap-1 text-[11px] font-bold text-success">
                                            <EyeIcon class="w-3.5 h-3.5" /> Live
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1 text-[11px] font-bold text-base-content/40">
                                            <EyeSlashIcon class="w-3.5 h-3.5" /> Draft
                                        </span>
                                    </td>
                                    <td class="text-xs text-base-content/60 whitespace-nowrap">{{ fmt(a.published_at) }}</td>
                                    <td class="text-right">
                                        <Link :href="route('website.news.edit', a.id)" class="btn btn-ghost btn-xs btn-square" title="Edit">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </Link>
                                        <button @click="destroy(a.id)" class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="articles.links" :from="articles.from" :to="articles.to" :total="articles.total" />
            </template>
        </div>
    </AppLayout>
</template>
