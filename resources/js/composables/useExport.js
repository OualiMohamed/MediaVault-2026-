import { ref } from "vue";
import api from "../api";

export function useExport() {
    const exporting = ref(null); // holds the type string while exporting, null otherwise

    async function exportCollection(type) {
        if (exporting.value) return;

        exporting.value = type;
        try {
            const response = await api.get(`/export/${type}`, {
                responseType: "blob",
            });

            // Extract filename from Content-Disposition header
            const disposition = response.headers["content-disposition"];
            let filename = `${type}_export`;
            if (disposition) {
                const match = disposition.match(/filename="?([^"]+)"?/);
                if (match) filename = match[1];
            }

            // Trigger browser download
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement("a");
            link.href = url;
            link.setAttribute("download", filename);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            console.error("Export failed:", err);
            alert("Export failed. Please try again.");
        } finally {
            exporting.value = null;
        }
    }

    // In composables/useExport.js, add:
    async function exportFullZip(type) {
        if (exporting.value) return;
        exporting.value = type;
        try {
            const response = await api.post(
                `/export/full/${type}`,
                {},
                { responseType: "blob" },
            );
            const disposition = response.headers["content-disposition"];
            let filename = `${type}_full_backup.zip`;
            if (disposition) {
                const match = disposition.match(/filename="?([^"]+)"?/);
                if (match) filename = match[1];
            }
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement("a");
            link.href = url;
            link.setAttribute("download", filename);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            console.error("Export failed:", err);
            alert("Export failed.");
        } finally {
            exporting.value = null;
        }
    }

    return { exporting, exportCollection, exportFullZip };
}
