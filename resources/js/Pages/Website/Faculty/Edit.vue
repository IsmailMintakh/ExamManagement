<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import FileUpload from '@/Components/FileUpload.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    member:      { type: Object, default: null },
    departments: { type: Array,  default: () => [] },
})

const isEdit = computed(() => !!props.member?.id)

const form = useForm({
    name:             props.member?.name || '',
    designation:      props.member?.designation || '',
    department:       props.member?.department || '',
    qualification:    props.member?.qualification || '',
    photo:            null,
    bio:              props.member?.bio || '',
    email:            props.member?.email || '',
    phone:            props.member?.phone || '',
    years_experience: props.member?.years_experience ?? null,
    is_principal:     props.member?.is_principal ?? false,
    is_featured:      props.member?.is_featured ?? false,
    is_active:        props.member?.is_active ?? true,
    sort_order:       props.member?.sort_order ?? 0,
    _method:          isEdit.value ? 'put' : 'post',
})

function save() {
    const url = isEdit.value
        ? route('website.faculty.update', props.member.id)
        : route('website.faculty.store')
    form.post(url, { forceFormData: true })
}
</script>

<template>
    <Head :title="isEdit ? `Edit: ${member.name}` : 'Add Faculty Member'" />
    <AppLayout :breadcrumbs="[
        { label: 'Website' },
        { label: 'Faculty', href: route('website.faculty.index') },
        { label: isEdit ? member.name : 'New Member' },
    ]">
        <div class="max-w-3xl mx-auto space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link :href="route('website.faculty.index')" class="btn btn-ghost btn-sm btn-square">
                        <ArrowLeftIcon class="w-4 h-4" />
                    </Link>
                    <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Faculty Member' : 'Add Faculty Member' }}</h1>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="form.is_active" class="toggle toggle-success toggle-sm" />
                    <span class="text-xs font-semibold">{{ form.is_active ? 'Active' : 'Hidden' }}</span>
                </label>
            </div>

            <form @submit.prevent="save" class="space-y-6">
                <!-- Basic info -->
                <section class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <h2 class="text-base font-bold">Basic Info</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <FormInput v-model="form.name" label="Full Name" required
                                placeholder="Mr. Muhammad Ismail" :error="form.errors.name" />
                            <FormInput v-model="form.designation" label="Designation"
                                placeholder="Senior English Teacher" :error="form.errors.designation" />
                            <div>
                                <label class="mb-1.5 flex items-center gap-1 text-[12px] font-semibold text-base-content/75">Department</label>
                                <select v-model="form.department" class="select select-bordered w-full text-sm">
                                    <option value="">— None —</option>
                                    <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                                </select>
                            </div>
                            <FormInput v-model="form.qualification" label="Qualification"
                                placeholder="MA English (Punjab University), B.Ed" :error="form.errors.qualification" />
                            <FormInput v-model.number="form.years_experience" type="number" label="Years of Experience"
                                placeholder="15" :error="form.errors.years_experience" />
                            <FormInput v-model.number="form.sort_order" type="number" label="Sort Order"
                                placeholder="0" help-text="Lower number = shown first" :error="form.errors.sort_order" />
                        </div>
                    </div>
                </section>

                <!-- Photo -->
                <section class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <h2 class="text-base font-bold">Photo</h2>
                        <div v-if="member?.photo_url && !form.photo" class="flex items-center gap-4 p-4 rounded-xl bg-base-200/40">
                            <img :src="member.photo_url" alt="Current" class="h-24 w-24 object-cover rounded-2xl" />
                            <span class="text-xs text-base-content/60">Current photo. Upload below to replace.</span>
                        </div>
                        <FileUpload v-model="form.photo" accept="image/jpeg,image/png,image/webp"
                            :max-size="3" :preview="true" :error="form.errors.photo" />
                    </div>
                </section>

                <!-- Contact + bio -->
                <section class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <h2 class="text-base font-bold">Bio &amp; Contact (optional)</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <FormInput v-model="form.email" type="email" label="Email"
                                placeholder="ismail@gbhss-skardu.edu.pk" :error="form.errors.email" />
                            <FormInput v-model="form.phone" label="Phone"
                                placeholder="+92 300 0000000" :error="form.errors.phone" />
                        </div>
                        <FormTextarea v-model="form.bio" label="Short Bio" rows="4"
                            placeholder="Mr. Ismail has been teaching English at GBHSS for 15 years…"
                            :error="form.errors.bio" />
                    </div>
                </section>

                <!-- Flags -->
                <section class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-3">
                        <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl hover:bg-base-200/40">
                            <input type="checkbox" v-model="form.is_principal" class="checkbox checkbox-warning checkbox-sm" />
                            <div>
                                <div class="text-sm font-semibold">Principal</div>
                                <div class="text-xs text-base-content/55">Pinned at the top of the public Faculty page.</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl hover:bg-base-200/40">
                            <input type="checkbox" v-model="form.is_featured" class="checkbox checkbox-warning checkbox-sm" />
                            <div>
                                <div class="text-sm font-semibold">Featured</div>
                                <div class="text-xs text-base-content/55">Highlighted with a star badge.</div>
                            </div>
                        </label>
                    </div>
                </section>

                <div class="flex items-center justify-end gap-2">
                    <Link :href="route('website.faculty.index')" class="btn btn-ghost btn-sm">Cancel</Link>
                    <button type="submit" class="btn btn-primary btn-sm" :class="{ loading: form.processing }" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Save Changes' : 'Add Member') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
