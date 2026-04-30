import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

/**
 * Central permission + role helper.
 *
 * Usage in any component:
 *   import { usePermissions } from '@/Composables/usePermissions'
 *   const { can, hasRole, isSuperAdmin } = usePermissions()
 *   ...
 *   <button v-if="can('students.create')">Add Student</button>
 */
export function usePermissions() {
    const page = usePage()

    const user = computed(() => page.props.auth?.user)
    const roles = computed(() => user.value?.roles || [])
    const permissions = computed(() => user.value?.permissions || [])

    /**
     * Check if the current user has a specific permission.
     * Super-admin short-circuits to true for everything.
     */
    const can = (permission) => {
        if (!user.value) return false
        if (roles.value.includes('super-admin')) return true
        if (Array.isArray(permission)) {
            return permission.some(p => permissions.value.includes(p))
        }
        return permissions.value.includes(permission)
    }

    /**
     * Check if the user has a specific role.
     */
    const hasRole = (role) => {
        if (!user.value) return false
        if (Array.isArray(role)) {
            return role.some(r => roles.value.includes(r))
        }
        return roles.value.includes(role)
    }

    const isSuperAdmin = computed(() => roles.value.includes('super-admin'))
    const isSchoolAdmin = computed(() => roles.value.includes('school-admin'))
    const isClassTeacher = computed(() => roles.value.includes('class-teacher'))
    const isSubjectTeacher = computed(() => roles.value.includes('subject-teacher'))
    const isStudent = computed(() => roles.value.includes('student'))
    const isParent = computed(() => roles.value.includes('parent'))

    return {
        user,
        roles,
        permissions,
        can,
        hasRole,
        isSuperAdmin,
        isSchoolAdmin,
        isClassTeacher,
        isSubjectTeacher,
        isStudent,
        isParent,
    }
}
