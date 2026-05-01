<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import FileUpload from '@/Components/FileUpload.vue'
import { Head, useForm, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { ArrowLeftIcon, PhotoIcon, TrashIcon, CloudArrowUpIcon, PencilIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    album:  { type: Object, default: null },
    photos: { type: Array,  default: () => [] },
})

const isEdit = computed(() => !!props.album?.id)

const albumForm = useForm({
    title:       props.album?.title || '',
    description: props.album?.description || '',
    event_date:  props.album?.event_date?.slice(0, 10) || '',
    cover:       null,
    is_active:   props.album?.is_active ?? true,
    _method:     isEdit.value ? 'put' : 'post',
})

function saveAlbum() {
    const url = isEdit.value
        ? route('website.gallery.update', props.album.id)
        : route('website.gallery.store')
    albumForm.post(url, { forceFormData: true })
}

// Photo upload — supports multi-select
const fileInput = ref(null)
const uploading = ref(false)
function pickPhotos() { fileInput.value?.click() }
function uploadFiles(e) {
    const files = Array.from(e.target.files || [])
    if (!files.length) return
    uploading.value = true
    router.post(route('website.gallery.photos.upload', props.album.id),
        { photos: files },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                uploading.value = false
                if (fileInput.value) fileInput.value.value = ''
            },
        })
}

function deletePhoto(id) {
    if (!confirm('Remove this photo?')) return
    router.delete(route('website.gallery.photos.destroy', [props.album.id, id]), { preserveScroll: true })
}

const editingCaption = ref(null)
const captionDraft = ref('')
function startEditCaption(photo) {
    editingCaption.value = photo.id
    captionDraft.value = photo.caption || ''
}
function saveCaption(photoId) {
    router.put(route('website.gallery.photos.update', [props.album.id, photoId]),
        { caption: captionDraft.value },
        { preserveScroll: true, onSuccess: () => editingCaption.value = null })
}
</script>

<template>
    <Head :title="isEdit ? `Edit: ${album.title}` : 'New Album'" />
    <AppLayout :breadcrumbs="[
        { label: 'Website' },
        { label: 'Gallery', href: route('website.gallery.index') },
        { label: isEdit ? album.title : 'New Album' },
    ]">
        <div class="max-w-5xl mx-auto space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link :href="route('website.gallery.index')" class="btn btn-ghost btn-sm btn-square">
                        <ArrowLeftIcon class="w-4 h-4" />
                    </Link>
                    <h1 class="text-2xl font-bold">{{ isEdit ? album.title : 'New Album' }}</h1>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="albumForm.is_active" class="toggle toggle-success toggle-sm" />
                    <span class="text-xs font-semibold">{{ albumForm.is_active ? 'Live' : 'Hidden' }}</span>
                </label>
            </div>

            <!-- Album form -->
            <form @submit.prevent="saveAlbum" class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body space-y-5">
                    <h2 class="text-base font-bold">Album Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <FormInput v-model="albumForm.title" label="Album Title" required
                            placeholder="Annual Sports Gala 2026" :error="albumForm.errors.title" />
                        <div>
                            <label class="mb-1.5 flex items-center gap-1 text-[12px] font-semibold text-base-content/75">Event Date</label>
                            <input type="date" v-model="albumForm.event_date" class="input input-bordered w-full text-sm" />
                        </div>
                    </div>
                    <FormTextarea v-model="albumForm.description" label="Description" rows="3"
                        placeholder="Three days of cricket, football, volleyball, and traditional polo at the Skardu Municipal Ground."
                        :error="albumForm.errors.description" />

                    <div>
                        <label class="mb-2 flex items-center gap-1 text-[12px] font-semibold text-base-content/75">Cover Image</label>
                        <div v-if="album?.cover_url && !albumForm.cover" class="flex items-center gap-4 p-4 rounded-xl bg-base-200/40 mb-3">
                            <img :src="album.cover_url" alt="Current cover" class="h-20 w-32 object-cover rounded-lg" />
                            <span class="text-xs text-base-content/60">Current cover. Upload below to replace.</span>
                        </div>
                        <FileUpload v-model="albumForm.cover" accept="image/jpeg,image/png,image/webp"
                            :max-size="4" :preview="true" :error="albumForm.errors.cover" />
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <button type="submit" class="btn btn-primary btn-sm" :class="{ loading: albumForm.processing }" :disabled="albumForm.processing">
                            {{ isEdit ? 'Save Changes' : 'Create Album' }}
                        </button>
                    </div>
                </div>
            </form>

            <!-- Photos manager (only after album is saved) -->
            <section v-if="isEdit" class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body space-y-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-base font-bold">Photos ({{ photos.length }})</h2>
                            <p class="text-xs text-base-content/55 mt-1">
                                Upload multiple photos at once. Click any thumbnail to add a caption.
                            </p>
                        </div>
                        <button @click="pickPhotos" class="btn btn-primary btn-sm gap-2" :disabled="uploading">
                            <CloudArrowUpIcon class="w-4 h-4" />
                            {{ uploading ? 'Uploading…' : 'Add Photos' }}
                        </button>
                        <input ref="fileInput" type="file" multiple accept="image/*" @change="uploadFiles" class="hidden" />
                    </div>

                    <div v-if="!photos.length" class="text-center py-12 rounded-xl bg-base-200/40">
                        <PhotoIcon class="w-10 h-10 mx-auto text-base-content/30" />
                        <p class="mt-3 text-sm text-base-content/55">No photos yet. Click "Add Photos" to upload.</p>
                    </div>

                    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        <div v-for="photo in photos" :key="photo.id"
                            class="group relative aspect-square rounded-xl overflow-hidden bg-base-200">
                            <img :src="photo.image_url" :alt="photo.caption || 'Gallery photo'"
                                class="absolute inset-0 w-full h-full object-cover transition-transform group-hover:scale-105" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-end p-3">
                                <p v-if="photo.caption" class="text-xs text-white line-clamp-2 mb-2">{{ photo.caption }}</p>
                                <div class="flex items-center gap-1.5">
                                    <button @click="startEditCaption(photo)"
                                        class="btn btn-xs btn-square bg-white/20 hover:bg-white/30 border-0 text-white" title="Edit caption">
                                        <PencilIcon class="w-3.5 h-3.5" />
                                    </button>
                                    <button @click="deletePhoto(photo.id)"
                                        class="btn btn-xs btn-square bg-red-500/80 hover:bg-red-500 border-0 text-white" title="Delete">
                                        <TrashIcon class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div v-if="editingCaption === photo.id"
                                class="absolute inset-x-0 bottom-0 bg-black/90 p-2 flex gap-1.5">
                                <input v-model="captionDraft" type="text" placeholder="Caption…"
                                    class="input input-xs flex-1 bg-white text-slate-900"
                                    @keyup.enter="saveCaption(photo.id)" />
                                <button @click="saveCaption(photo.id)" class="btn btn-xs btn-primary">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
