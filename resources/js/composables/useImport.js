import { ref } from "vue";
import api from "../api";

export function useImport() {
    const validating = ref(false);
    const importing = ref(false);
    const preview = ref(null); // Holds validation result
    const error = ref("");

    async function validateFile(type, file) {
        validating.value = true;
        error.value = "";
        preview.value = null;

        const formData = new FormData();
        formData.append("file", file);

        try {
            const { data } = await api.post(
                `/import/validate/${type}`,
                formData,
                {
                    headers: { "Content-Type": "multipart/form-data" },
                },
            );

            if (data.error) {
                error.value = data.error;
                return;
            }

            preview.value = data;
        } catch (err) {
            error.value =
                err.response?.data?.message ||
                err.response?.data?.error ||
                "Validation failed.";
        } finally {
            validating.value = false;
        }
    }

    async function executeImport(type) {
        if (!preview.value?.items?.length) return;

        importing.value = true;
        error.value = "";

        try {
            const { data } = await api.post(`/import/execute/${type}`, {
                items: preview.value.items,
                session_token: preview.value.session_token, // Add this
            });

            resetState();
            return data;
        } catch (err) {
            error.value = err.response?.data?.message || "Import failed.";
        } finally {
            importing.value = false;
        }
    }

    function resetState() {
        preview.value = null;
        error.value = "";
    }

    return {
        validating,
        importing,
        preview,
        error,
        validateFile,
        executeImport,
        resetState,
    };
}
