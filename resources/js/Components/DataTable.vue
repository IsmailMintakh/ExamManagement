<script setup>
import { ref, computed } from 'vue';
import { ChevronUpIcon, ChevronDownIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    columns: {
        type: Array,
        required: true,
        // { key, label, sortable? }
    },
    rows: {
        type: Array,
        default: () => [],
    },
    searchable: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['search', 'sort']);

const search = ref('');
const sortKey = ref('');
const sortDirection = ref('asc');
let debounceTimer = null;

function onSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        emit('search', search.value);
    }, 300);
}

function toggleSort(column) {
    if (!column.sortable) return;

    if (sortKey.value === column.key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = column.key;
        sortDirection.value = 'asc';
    }

    emit('sort', { key: sortKey.value, direction: sortDirection.value });
}

const skeletonRows = computed(() => Array.from({ length: 5 }));
</script>

<template>
    <div class="w-full">
        <!-- Search -->
        <div v-if="searchable" class="mb-4">
            <div class="relative">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-base-content/40" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search..."
                    class="input input-bordered w-full max-w-xs pl-10"
                    @input="onSearch"
                />
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto rounded-lg border border-base-300">
            <table class="table">
                <thead>
                    <tr class="bg-base-200">
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="select-none"
                            :class="{ 'cursor-pointer hover:bg-base-300': column.sortable }"
                            @click="toggleSort(column)"
                        >
                            <div class="flex items-center gap-1">
                                <span>{{ column.label }}</span>
                                <template v-if="column.sortable">
                                    <ChevronUpIcon
                                        v-if="sortKey === column.key && sortDirection === 'asc'"
                                        class="h-4 w-4"
                                    />
                                    <ChevronDownIcon
                                        v-else-if="sortKey === column.key && sortDirection === 'desc'"
                                        class="h-4 w-4"
                                    />
                                    <span v-else class="h-4 w-4 opacity-30">
                                        <ChevronUpIcon class="h-4 w-4" />
                                    </span>
                                </template>
                            </div>
                        </th>
                        <th v-if="$slots.actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Loading skeleton -->
                    <template v-if="loading">
                        <tr v-for="(_, i) in skeletonRows" :key="'skeleton-' + i">
                            <td v-for="column in columns" :key="column.key">
                                <div class="h-4 w-3/4 animate-pulse rounded bg-base-300"></div>
                            </td>
                            <td v-if="$slots.actions">
                                <div class="h-4 w-16 animate-pulse rounded bg-base-300"></div>
                            </td>
                        </tr>
                    </template>

                    <!-- Data rows -->
                    <template v-else-if="rows.length > 0">
                        <tr v-for="(row, index) in rows" :key="row.id || index" class="hover:bg-base-200/50">
                            <td v-for="column in columns" :key="column.key">
                                <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                                    {{ row[column.key] }}
                                </slot>
                            </td>
                            <td v-if="$slots.actions">
                                <slot name="actions" :row="row" />
                            </td>
                        </tr>
                    </template>

                    <!-- Empty state -->
                    <tr v-else>
                        <td :colspan="columns.length + ($slots.actions ? 1 : 0)">
                            <EmptyState title="No records found" description="Try adjusting your search or filters." />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
