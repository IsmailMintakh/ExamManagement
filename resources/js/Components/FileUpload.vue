<script setup>
import { ref, computed, watch } from 'vue';
import { CloudArrowUpIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: {
        type: [Object, File, null],
        default: null,
    },
    label: {
        type: String,
        default: '',
    },
    accept: {
        type: String,
        default: '',
    },
    maxSize: {
        type: Number,
        default: 5, // MB
    },
    preview: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const isDragging = ref(false);
const sizeError = ref('');
const previewUrl = ref('');
const fileInput = ref(null);

watch(
    () => props.modelValue,
    (file) => {
        if (file && props.preview && file instanceof File && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => (previewUrl.value = e.target.result);
            reader.readAsDataURL(file);
        } else if (!file) {
            previewUrl.value = '';
        }
    },
);

const fileName = computed(() => {
    if (props.modelValue instanceof File) {
        return props.modelValue.name;
    }
    return '';
});

function handleFile(file) {
    sizeError.value = '';

    if (!file) return;

    const sizeMB = file.size / (1024 * 1024);
    if (sizeMB > props.maxSize) {
        sizeError.value = `File size must be less than ${props.maxSize}MB. Selected file is ${sizeMB.toFixed(1)}MB.`;
        return;
    }

    emit('update:modelValue', file);
}

function onFileChange(e) {
    handleFile(e.target.files[0]);
}

function onDrop(e) {
    isDragging.value = false;
    handleFile(e.dataTransfer.files[0]);
}

function onDragOver() {
    isDragging.value = true;
}

function onDragLeave() {
    isDragging.value = false;
}

function removeFile() {
    emit('update:modelValue', null);
    previewUrl.value = '';
    sizeError.value = '';
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function openPicker() {
    fileInput.value?.click();
}
</script>

<template>
    <div class="form-control w-full">
        <label v-if="label" class="label">
            <span class="label-text">{{ label }}</span>
        </label>

        <div
            class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-6 transition-colors"
            :class="{
                'border-primary bg-primary/5': isDragging,
                'border-error': error || sizeError,
                'border-base-300 hover:border-primary/50': !isDragging && !error && !sizeError,
            }"
            @click="openPicker"
            @drop.prevent="onDrop"
            @dragover.prevent="onDragOver"
            @dragleave.prevent="onDragLeave"
        >
            <template v-if="previewUrl && preview">
                <img :src="previewUrl" class="mb-2 max-h-32 rounded" alt="Preview" />
            </template>
            <template v-if="!fileName">
                <CloudArrowUpIcon class="mb-2 h-10 w-10 text-base-content/40" />
                <p class="text-sm text-base-content/60">
                    Drag and drop or <span class="text-primary font-medium">browse</span>
                </p>
                <p v-if="accept" class="mt-1 text-xs text-base-content/40">
                    Accepted: {{ accept }}
                </p>
                <p class="text-xs text-base-content/40">Max size: {{ maxSize }}MB</p>
            </template>
            <template v-else>
                <p class="text-sm font-medium">{{ fileName }}</p>
            </template>
        </div>

        <div v-if="fileName" class="mt-2 flex items-center justify-end">
            <button type="button" class="btn btn-ghost btn-xs text-error" @click.stop="removeFile">
                <XMarkIcon class="h-4 w-4" />
                Remove
            </button>
        </div>

        <input
            ref="fileInput"
            type="file"
            class="hidden"
            :accept="accept"
            @change="onFileChange"
        />

        <label v-if="error || sizeError" class="label">
            <span class="label-text-alt text-error">{{ error || sizeError }}</span>
        </label>
    </div>
</template>
