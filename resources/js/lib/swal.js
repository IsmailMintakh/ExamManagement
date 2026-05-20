// Shared SweetAlert2 helpers. Use these instead of window.confirm/alert.
//
//   confirmDelete({ title, text, confirmText }) → Promise<boolean>   // red, destructive
//   confirmAction({ title, text, confirmText, icon }) → Promise<bool> // neutral primary
//   swalToast({ message, icon })                                      // small corner toast
//
// Buttons are styled via the daisyUI classes already in the app so the
// dialog blends with the surrounding UI in both light and dark themes.

import Swal from 'sweetalert2'

const baseOptions = {
    buttonsStyling: false,
    reverseButtons: true,
    focusCancel: true,
    customClass: {
        popup: 'rounded-2xl border border-base-300 bg-base-100 text-base-content shadow-2xl',
        title: 'text-base font-semibold',
        htmlContainer: 'text-sm text-base-content/70',
        actions: 'gap-2',
        confirmButton: 'btn btn-sm',
        cancelButton: 'btn btn-sm btn-ghost',
    },
}

export function confirmDelete({
    title = 'Are you sure?',
    text = 'This action cannot be undone.',
    confirmText = 'Yes, delete',
    cancelText = 'Cancel',
} = {}) {
    return Swal.fire({
        ...baseOptions,
        icon: 'warning',
        title,
        text,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        customClass: {
            ...baseOptions.customClass,
            confirmButton: 'btn btn-sm btn-error text-white',
        },
    }).then((r) => r.isConfirmed)
}

export function confirmAction({
    title = 'Confirm',
    text = '',
    confirmText = 'Continue',
    cancelText = 'Cancel',
    icon = 'question',
    danger = false,
} = {}) {
    return Swal.fire({
        ...baseOptions,
        icon,
        title,
        text,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        customClass: {
            ...baseOptions.customClass,
            confirmButton: danger ? 'btn btn-sm btn-error text-white' : 'btn btn-sm btn-primary',
        },
    }).then((r) => r.isConfirmed)
}

export function swalToast({ message, icon = 'success', duration = 2500 } = {}) {
    return Swal.fire({
        toast: true,
        position: 'top-end',
        icon,
        title: message,
        showConfirmButton: false,
        timer: duration,
        timerProgressBar: true,
        customClass: {
            popup: 'rounded-xl border border-base-300 bg-base-100 text-base-content shadow-lg',
            title: 'text-sm',
        },
    })
}

export default { confirmDelete, confirmAction, swalToast }
