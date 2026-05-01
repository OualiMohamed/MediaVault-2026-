// resources/js/composables/useNetworkLogo.js
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

        // Return cached result immediately
        if (cache.has(cacheKey)) {
            logoUrl.value = cache.get(cacheKey);
            return;
        }

        loading.value = true;

        try {
            const res = await fetch(
                `https://api.tvmaze.com/search/shows?q=${encodeURIComponent(showTitle)}`,
            );
            const data = await res.json();

            // Find the best match — prefer exact network name match
            const match =
                data.find((item) => {
                    const netName = item.show?.network?.name;
                    if (!netName) return false;
                    return netName.toLowerCase() === cacheKey;
                }) ||
                data.find((item) => {
                    const netName = item.show?.network?.name;
                    if (!netName) return false;
                    return (
                        netName.toLowerCase().includes(cacheKey) ||
                        cacheKey.includes(netName.toLowerCase())
                    );
                });

            const url =
                match?.show?.network?.image?.medium ||
                match?.show?.network?.image?.original ||
                null;

            logoUrl.value = url;
            cache.set(cacheKey, url);
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
