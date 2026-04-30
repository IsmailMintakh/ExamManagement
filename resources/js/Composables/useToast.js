import { reactive } from 'vue';

const state = reactive({
    toasts: [],
    nextId: 0,
});

export function useToast() {
    function addToast(message, type = 'info', duration = 4000) {
        const id = state.nextId++;
        state.toasts.push({ id, message, type, duration });

        if (duration > 0) {
            setTimeout(() => removeToast(id), duration);
        }

        return id;
    }

    function removeToast(id) {
        const index = state.toasts.findIndex((t) => t.id === id);
        if (index !== -1) {
            state.toasts.splice(index, 1);
        }
    }

    function success(message, duration) {
        return addToast(message, 'success', duration);
    }

    function error(message, duration) {
        return addToast(message, 'error', duration);
    }

    function warning(message, duration) {
        return addToast(message, 'warning', duration);
    }

    function info(message, duration) {
        return addToast(message, 'info', duration);
    }

    return {
        toasts: state.toasts,
        addToast,
        removeToast,
        success,
        error,
        warning,
        info,
    };
}
