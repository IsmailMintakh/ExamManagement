<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import FileUpload from '@/Components/FileUpload.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { ArrowLeftIcon, PhotoIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    article:    { type: Object, default: null },
    categories: { type: Array,  default: () => [] },
})

const isEdit = computed(() => !!props.article?.id)

const form = useForm({
    title:        props.article?.title || '',
    category:     props.article?.category || 'Announcement',
    excerpt:      props.article?.excerpt || '',
    body:         props.article?.body || '',
    image:        null,
    image_gradient: props.article?.image_gradient || 'from-emerald-700 to-emerald-950',
    is_featured:  props.article?.is_featured ?? false,
    is_published: props.article?.is_published ?? true,
    published_at: props.article?.published_at?.slice(0, 16) || new Date().toISOString().slice(0, 16),
    _method:      isEdit.value ? 'put' : 'post',
})

function save() {
    const url = isEdit.value
        ? route('website.news.update', props.article.id)
        : route('website.news.store')
    form.post(url, { forceFormData: true })
}

const gradientOptions = [
    { value: 'from-emerald-700 to-emerald-950',  label: 'Emerald (default)' },
    { value: 'from-amber-600 to-orange-800',     label: 'Amber' },
    { value: 'from-sky-700 to-indigo-900',       label: 'Indigo' },
    { value: 'from-rose-600 to-pink-800',        label: 'Rose' },
    { value: 'from-violet-600 to-purple-800',    label: 'Violet' },
    { value: 'from-slate-700 to-slate-900',      label: 'Slate' },
]
</script>

<template>
    <Head :title="isEdit ? 'Edit Article' : 'New Article'" />
    <AppLayout :breadcrumbs="[
        { label: 'Website' },
        { label: 'News', href: route('website.news.index') },
        { label: isEdit ? 'Edit Article' : 'New Article' },
    ]">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link :href="route('website.news.index')" class="btn btn-ghost btn-sm btn-square">
                        <ArrowLeftIcon class="w-4 h-4" />
                    </Link>
                    <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Article' : 'New Article' }}</h1>
                </div>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="form.is_featured" class="checkbox checkbox-warning checkbox-sm" />
                        <span class="text-xs font-semibold">Featured</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="form.is_published" class="toggle toggle-success toggle-sm" />
                        <span class="text-xs font-semibold">{{ form.is_published ? 'Live' : 'Draft' }}</span>
                    </label>
                </div>
            </div>

            <form @submit.prevent="save" class="space-y-6">
                <!-- Content -->
                <section class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <FormInput v-model="form.title" label="Title" required
                            placeholder="Three Students Secure Top Positions in FBISE…"
                            :error="form.errors.title" />
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="mb-1.5 flex items-center gap-1 text-[12px] font-semibold text-base-content/75">Category</label>
                                <select v-model="form.category" class="select select-bordered w-full text-sm">
                                    <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                                </select>
                                <p v-if="form.errors.category" class="mt-1.5 text-xs text-error">{{ form.errors.category }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 flex items-center gap-1 text-[12px] font-semibold text-base-content/75">Publish Date</label>
                                <input type="datetime-local" v-model="form.published_at"
                                    class="input input-bordered w-full text-sm" />
                                <p v-if="form.errors.published_at" class="mt-1.5 text-xs text-error">{{ form.errors.published_at }}</p>
                            </div>
                        </div>
                        <FormTextarea v-model="form.excerpt" label="Excerpt (short summary)" rows="2"
                            placeholder="Two-line teaser shown in news listings and on the homepage."
                            :error="form.errors.excerpt" />
                        <FormTextarea v-model="form.body" label="Full Article Body" rows="10"
                            placeholder="The full story (plain text or basic HTML)…"
                            help-text="Plain paragraphs separated by blank lines render with proper spacing on the public page."
                            :error="form.errors.body" />
                    </div>
                </section>

                <!-- Cover image -->
                <section class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <h2 class="text-base font-bold">Cover Image</h2>
                        <div v-if="article?.image_url && !form.image" class="flex items-center gap-4 p-4 rounded-xl bg-base-200/40">
                            <img :src="article.image_url" alt="Current" class="h-24 w-32 object-cover rounded-lg" />
                            <div class="text-xs text-base-content/60">
                                <div class="font-semibold text-base-content">Current image</div>
                                <div class="mt-1">Upload a new file below to replace.</div>
                            </div>
                        </div>
                        <FileUpload v-model="form.image" accept="image/jpeg,image/png,image/webp"
                            :max-size="4" :preview="true" :error="form.errors.image" />
                        <div>
                            <label class="mb-1.5 flex items-center gap-1 text-[12px] font-semibold text-base-content/75">
                                Fallback Gradient (used when no image)
                            </label>
                            <select v-model="form.image_gradient" class="select select-bordered w-full text-sm">
                                <option v-for="g in gradientOptions" :key="g.value" :value="g.value">{{ g.label }}</option>
                            </select>
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-end gap-2">
                    <Link :href="route('website.news.index')" class="btn btn-ghost btn-sm">Cancel</Link>
                    <button type="submit" class="btn btn-primary btn-sm" :class="{ loading: form.processing }" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Update Article' : 'Publish Article') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
