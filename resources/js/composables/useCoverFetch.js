import { ref } from "vue";
import api from "../api";

export function useCoverFetch() {
    const fetching = ref(false);
    const progress = ref({ fetched: 0, failed: 0, remaining: 0, total: 0 });
    const error = ref("");
    const cancelled = ref(false);
    const attempted = ref(new Set());

    async function start(type) {
        fetching.value = true;
        error.value = "";
        cancelled.value = false;
        attempted.value = new Set();
        progress.value = { fetched: 0, failed: 0, remaining: 0, total: 0 };

        try {
            const { data: initial } = await api.get(`/covers/missing/${type}`);
            progress.value.total = initial.total_missing;
            progress.value.remaining = initial.total_missing;

            if (
                initial.total_missing === 0 ||
                initial.next_batch.length === 0
            ) {
                fetching.value = false;
                emit("done");
                return;
            }

            let batch = initial.next_batch;

            while (batch.length > 0 && !cancelled.value) {
                const freshIds = batch.filter((id) => !attempted.value.has(id));
                freshIds.forEach((id) => attempted.value.add(id));

                if (freshIds.length === 0) break;

                const { data: result } = await api.post(
                    `/covers/fetch/${type}`,
                    { item_ids: freshIds },
                );
                progress.value.fetched += result.fetched;
                progress.value.failed += result.failed;
                progress.value.remaining = result.remaining;

                if (result.remaining === 0) break;

                if (cancelled.value) break;

                const { data: next } = await api.get(`/covers/missing/${type}`);
                batch = next.next_batch;
            }
        } catch (err) {
            if (!cancelled.value) {
                if (err.response?.status === 429) {
                    // Daily rate limit — skip these items instead of retrying
                    progress.value.failed += freshIds.length;
                    progress.value.remaining = result.remaining;
                    freshIds.forEach((id) => attempted.value.add(id));
                    error.value =
                        "Google Books daily limit reached. These items will be skipped.";
                } else {
                    error.value =
                        err.response?.data?.message || "Cover fetch failed.";
                }
            }
        } finally {
            fetching.value = false;
        }
    }

    function cancel() {
        cancelled.value = true;
    }

    function resetState() {
        fetching.value = false;
        error.value = "";
        cancelled.value = false;
        attempted.value = new Set();
        progress.value = { fetched: 0, failed: 0, remaining: 0, total: 0 };
    }

    return { fetching, progress, error, start, cancel, resetState };
}
