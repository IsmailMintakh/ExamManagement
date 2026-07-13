<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import {
    PlusIcon, NewspaperIcon, PencilSquareIcon, TrashIcon,
    StarIcon, EyeIcon, EyeSlashIcon, MagnifyingGlassIcon, PhotoIcon,
} from '@heroicons/vue/24/outline'
import { formatDate } from '@/Utils/format'
import { confirmDelete } from '@/lib/swal'
import { usePreservedFocus } from '@/Composables/usePreservedFocus'

const props = defineProps({
    articles:   { type: Object, default: () => ({ data: [] }) },
    filters:    { type: Object, default: () => ({}) },
    categories: { type: Array,  default: () => [] },
})

const search = ref(props.filters.search || '')
const searchEl = ref(null)
usePreservedFocus(searchEl)
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

async function destroy(id) {
    if (!await confirmDelete({ title: 'Delete this article?' })) return
    router.delete(route('website.news.destroy', id), { preserveScroll: true })
}

</script>

<template>
    <Head title="News & Articles" />
    <AppLayout :breadcrumbs="[{ label: 'Website' }, { label: 'News' }]">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">News &amp; Articles</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        {{ articles?.total || 0 }} article{{ (articles?.total || 0) === 1 ? '' : 's' }} · public News page &amp; homepage
                    </p>
                </div>
                <Link :href="route('website.news.create')" class="btn btn-primary btn-sm gap-1.5">
                    <PlusIcon class="w-4 h-4" /> New Article
                </Link>
            </div>

            <section class="surface overflow-hidden">
                <header class="surface-header">
                    <div class="relative flex-1 max-w-md">
                        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40" />
                        <input ref="searchEl" v-model="search" type="text" placeholder="Search title or excerpt…"
                            class="input input-bordered input-sm w-full pl-9 text-sm" />
                    </div>
                    <div class="min-w-[180px]">
                        <SearchableSelect v-model="category" size="sm"
                            :options="[{ value: '', label: 'All categories' }, ...(categories || []).map(c => ({ value: c, label: c }))]"
                            placeholder="All categories" />
                    </div>
                </header>

                <EmptyState v-if="!articles.data?.length"
                    :icon="NewspaperIcon"
                    title="No articles yet"
                    description="Publish your first article. It'll show on the public News page and (if featured) on the homepage."
                    action-text="Publish First Article"
                    :action-href="route('website.news.create')" />

                <div v-else class="table-sticky-wrap" style="--table-max-h: 65vh;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-16"></th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Published</th>
                                <th class="w-24 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in articles.data" :key="a.id">
                                <td>
                                    <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-emerald-600 to-emerald-800 overflow-hidden flex items-center justify-center">
                                        <img v-if="a.image_url" :src="a.image_url" :alt="a.title" class="w-full h-full object-cover" />
                                        <PhotoIcon v-else class="w-5 h-5 text-white/50" />
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <StarIcon v-if="a.is_featured" class="w-4 h-4 text-amber-500 shrink-0" />
                                        <div class="min-w-0">
                                            <Link :href="route('website.news.edit', a.id)" class="font-bold text-sm truncate max-w-md hover:text-primary transition-colors">{{ a.title }}</Link>
                                            <div v-if="a.excerpt" class="text-[11px] text-base-content/55 truncate max-w-md mt-0.5">
                                                {{ a.excerpt }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-outline badge-sm">{{ a.category }}</span>
                                </td>
                                <td>
                                    <span v-if="a.is_published" class="inline-flex items-center gap-1 text-[11px] font-bold text-success">
                                        <EyeIcon class="w-3.5 h-3.5" /> Live
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 text-[11px] font-bold text-base-content/40">
                                        <EyeSlashIcon class="w-3.5 h-3.5" /> Draft
                                    </span>
                                </td>
                                <td class="text-[12px] text-base-content/65 whitespace-nowrap tabular-nums">{{ formatDate(a.published_at) }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="flex gap-0.5 justify-end">
                                        <Link :href="route('website.news.edit', a.id)" class="btn btn-ghost btn-xs btn-square" title="Edit">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </Link>
                                        <button @click="destroy(a.id)" class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <footer v-if="articles.data?.length && articles.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ articles.from }}–{{ articles.to }}</span>
                        of <span class="text-base-content font-bold">{{ articles.total }}</span>
                    </span>
                    <Pagination :links="articles.links" :from="articles.from" :to="articles.to" :total="articles.total" />
                </footer>
            </section>
        </div>
    </AppLayout>
</template>
