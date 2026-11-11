import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * This device's connection, as the browser reports it: true until the page
 * mounts (the server can't know), then kept up to date by the online/offline
 * events. Used by the menu's Online/Offline line and the assistant page.
 */
export function useOnline() {
    const online = ref(true);
    const up = () => (online.value = true);
    const down = () => (online.value = false);

    onMounted(() => {
        online.value = navigator.onLine;
        window.addEventListener('online', up);
        window.addEventListener('offline', down);
    });
    onBeforeUnmount(() => {
        window.removeEventListener('online', up);
        window.removeEventListener('offline', down);
    });

    return online;
}
