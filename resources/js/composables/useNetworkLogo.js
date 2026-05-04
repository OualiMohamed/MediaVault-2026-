import { ref } from "vue";
import api from "../api";

const cache = new Map();

export function useNetworkLogo() {
    const logoUrl = ref(null);
    const loading = ref(false);
    let disposed = false;

    async function fetchLogo(showTitle, networkName) {
        if (!networkName || !showTitle || disposed) {
            return;
        }

        const cacheKey = networkName.toLowerCase().trim();

        if (cache.has(cacheKey)) {
            if (!disposed) logoUrl.value = cache.get(cacheKey);
            return;
        }

        if (!disposed) loading.value = true;

        try {
            const { data } = await api.get("/network-logo", {
                params: { show: showTitle, network: networkName },
            });

            if (!disposed) {
                logoUrl.value = data.logo || null;
                cache.set(cacheKey, data.logo || null);
            }
        } catch (e) {
            if (!disposed) logoUrl.value = null;
        } finally {
            if (!disposed) loading.value = false;
        }
    }

    function clear() {
        if (!disposed) {
            logoUrl.value = null;
            loading.value = false;
        }
    }

    function dispose() {
        disposed = true;
    }

    function proxyUrl(url) {
        if (!url) return null;
        try {
            const u = new URL(url);
            // Only proxy TMDB image URLs
            if (!u.hostname.includes("tmdb.org")) return url;
            return `/api/tmdb/poster?size=w185&path=${u.pathname}`;
        } catch (e) {
            return url;
        }
    }

    return { logoUrl, loading, fetchLogo, clear, dispose, proxyUrl };
}
