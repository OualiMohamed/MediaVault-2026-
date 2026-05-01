// resources/js/composables/useNetworkLogo.js
import { ref } from "vue";
import api from "../api";

const cache = new Map();

export function useNetworkLogo() {
    const logoUrl = ref(null);
    const loading = ref(false);

    async function fetchLogo(showTitle, networkName) {
        if (!networkName || !showTitle) {
            logoUrl.value = null;
            return;
        }

        const cacheKey = networkName.toLowerCase().trim();

        if (cache.has(cacheKey)) {
            logoUrl.value = cache.get(cacheKey);
            return;
        }

        loading.value = true;

        try {
            const { data } = await api.get("/network-logo", {
                params: {
                    show: showTitle,
                    network: networkName,
                },
            });

            logoUrl.value = data.logo || null;
            cache.set(cacheKey, data.logo || null);
        } catch (e) {
            logoUrl.value = null;
        } finally {
            loading.value = false;
        }
    }

    function clear() {
        logoUrl.value = null;
        loading.value = false;
    }

    return { logoUrl, loading, fetchLogo, clear };
}
