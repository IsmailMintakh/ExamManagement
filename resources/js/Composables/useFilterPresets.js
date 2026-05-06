import { ref, computed, watch } from 'vue'

/**
 * Persist named filter presets per page in localStorage.
 *
 * Why localStorage (not DB): zero migration cost, instant feedback, presets
 * are inherently per-device convenience — sharing them across devices isn't
 * a missing feature. If users ever ask for cross-device sync, swap the storage
 * adapter without changing any consumer code.
 *
 * Usage:
 *   const presets = useFilterPresets('students-index', () => ({
 *       schoolId: schoolId.value, classId: classId.value, sectionId: sectionId.value, status: status.value,
 *   }))
 *   presets.save('Class 10 Active')   // captures current filter state
 *   presets.apply(presetId)           // returns the saved object so caller can restore
 *   presets.remove(presetId)
 *   presets.list                      // reactive list of { id, name, filters, createdAt }
 */
export function useFilterPresets(scopeKey, captureCurrent) {
    const storageKey = `filter-presets:${scopeKey}`
    const list = ref(load())

    function load() {
        try {
            const raw = localStorage.getItem(storageKey)
            return raw ? JSON.parse(raw) : []
        } catch {
            return []
        }
    }

    function persist() {
        try {
            localStorage.setItem(storageKey, JSON.stringify(list.value))
        } catch {
            // Quota or private mode — silently ignore; presets are non-critical.
        }
    }

    function save(name) {
        const trimmed = (name || '').trim()
        if (!trimmed) return null
        const filters = captureCurrent()
        const preset = {
            id: Date.now().toString(36),
            name: trimmed,
            filters,
            createdAt: new Date().toISOString(),
        }
        list.value = [preset, ...list.value].slice(0, 20) // cap at 20 to keep storage small
        persist()
        return preset
    }

    function apply(id) {
        return list.value.find(p => p.id === id)?.filters || null
    }

    function remove(id) {
        list.value = list.value.filter(p => p.id !== id)
        persist()
    }

    function rename(id, newName) {
        const trimmed = (newName || '').trim()
        if (!trimmed) return
        list.value = list.value.map(p => p.id === id ? { ...p, name: trimmed } : p)
        persist()
    }

    return {
        list: computed(() => list.value),
        save,
        apply,
        remove,
        rename,
    }
}
