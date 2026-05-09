import { ref } from "vue";

const lightColors = {
    "--color-vault-200": "#374151",
    "--color-vault-300": "#4b5563",
    "--color-vault-400": "#6b7280",
    "--color-vault-500": "#9ca3af",
    "--color-vault-600": "#d1d5db",
    "--color-vault-700": "#e5e7eb",
    "--color-vault-800": "#f9fafb",
    "--color-vault-900": "#ffffff",
};

const theme = ref(localStorage.getItem("theme") || "dark");
let styleTag = null;

export function useTheme() {
    function applyTheme(newTheme) {
        theme.value = newTheme;
        localStorage.setItem("theme", newTheme);

        const html = document.documentElement;

        // Always clean up first
        if (styleTag) {
            styleTag.remove();
            styleTag = null;
        }

        if (newTheme === "light") {
            // Set light mode variables
            Object.entries(lightColors).forEach(([prop, value]) => {
                html.style.setProperty(prop, value);
            });

            // Inject light mode overrides
            styleTag = document.createElement("style");
            styleTag.id = "light-mode-overrides";
            styleTag.textContent = `
                body, .min-h-screen {
                    background-color: #f3f4f6 !important;
                    color: #111827 !important;
                }

                .text-white { color: #111827 !important; }
                .text-vault-100, .text-vault-200 { color: #374151 !important; }
                .text-vault-300 { color: #4b5563 !important; }
                .text-vault-400 { color: #6b7280 !important; }
                .text-vault-500 { color: #9ca3af !important; }

                .placeholder-vault-400::placeholder,
                .placeholder-vault-500::placeholder { color: #9ca3af !important; }

                .media-card,
                .stat-card {
                    background-color: #ffffff !important;
                    border-color: transparent !important;
                    box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05), 0 1px 3px 0 rgba(0,0,0,0.08) !important;
                }
                .media-card:hover {
                    box-shadow: 0 4px 12px 0 rgba(0,0,0,0.1), 0 2px 4px 0 rgba(0,0,0,0.06) !important;
                }

                .bg-vault-700 {
                    background-color: #ffffff !important;
                    border-color: #d1d5db !important;
                }
                .bg-vault-800 {
                    background-color: #ffffff !important;
                    border-color: #d1d5db !important;
                }

                .bg-vault-600 {
                    background-color: #f3f4f6 !important;
                    border-color: #d1d5db !important;
                    color: #374151 !important;
                }
                .hover\\:bg-vault-700:hover,
                .hover\\:bg-vault-600:hover,
                .hover\\:bg-vault-500:hover {
                    background-color: #e5e7eb !important;
                }
                .hover\\:text-white:hover,
                .hover\\:text-vault-200:hover,
                .hover\\:text-vault-300:hover {
                    color: #111827 !important;
                }
                .group:hover .group-hover\\:text-vault-300,
                .group:hover .group-hover\\:text-white {
                    color: #d97706 !important;
                }
                .group:hover .group-hover\\:text-vault-200 {
                    color: #d97706 !important;
                }

                .border-vault-600 { border-color: #d1d5db !important; }
                .border-vault-700 { border-color: #e5e7eb !important; }
                .border-vault-500 { border-color: #d1d5db !important; }
                .border-vault-600\\/30 { border-color: rgba(209,213,219,0.5) !important; }
                .border-vault-600\\/50,
                .border-vault-700\\/50 { border-color: #e5e7eb !important; }
                .border-dashed { border-color: #d1d5db !important; }

                .focus\\:ring-amber-500\\/50 { --tw-ring-color: rgb(245 158 11 / 0.25) !important; }
                .focus\\:ring-sky-500\\/50 { --tw-ring-color: rgb(14 165 233 / 0.25) !important; }
                .focus\\:ring-violet-500\\/50 { --tw-ring-color: rgb(139 92 246 / 0.25) !important; }
                .focus\\:ring-emerald-500\\/50 { --tw-ring-color: rgb(16 185 129 / 0.25) !important; }
                .focus\\:ring-rose-500\\/50 { --tw-ring-color: rgb(244 63 94 / 0.25) !important; }
                .focus\\:border-amber-500 { border-color: #f59e0b !important; }

                .bg-amber-500\\/15 { background-color: rgb(245 158 11 / 0.1) !important; }
                .bg-emerald-500\\/15 { background-color: rgb(16 185 129 / 0.1) !important; }
                .bg-sky-500\\/15 { background-color: rgb(14 165 233 / 0.1) !important; }
                .bg-rose-500\\/15 { background-color: rgb(244 63 94 / 0.1) !important; }
                .bg-violet-500\\/15 { background-color: rgb(139 92 246 / 0.1) !important; }
                .bg-rose-500\\/30 { background-color: rgb(244 63 94 / 0.12) !important; }
                .bg-vault-600\\/20 { background-color: rgba(107,114,128,0.1) !important; }
                .bg-vault-600\\/30 { background-color: rgba(107,114,128,0.15) !important; }
                .bg-vault-700\\/50 { background-color: rgba(107,114,128,0.08) !important; }
                .bg-vault-700\\/70 { background-color: rgba(107,114,128,0.12) !important; }
                .bg-white\\/15 { background-color: rgba(255,255,255,0.6) !important; }

                .bg-black\\/60,
                .bg-black\\/70 {
                    background-color: rgba(0,0,0,0.05) !important;
                }
                .modal-backdrop {
                    background-color: rgba(0,0,0,0.25) !important;
                }

                .from-vault-950\\/60 { --tw-gradient-from: rgba(243,244,246,0.7) !important; }
                .via-vault-950\\/80 { --tw-gradient-via: rgba(243,244,246,0.85) !important; }
                .to-vault-950 { --tw-gradient-to: #f3f4f6 !important; }

                .bg-vault-900\\/90 {
                    background-color: rgba(255,255,255,0.85) !important;
                    backdrop-filter: blur(12px) saturate(180%) !important;
                }
                .bg-vault-900\\/95 {
                    background-color: rgba(255,255,255,0.92) !important;
                    backdrop-filter: blur(12px) saturate(180%) !important;
                }

                .border-b-vault-700,
                .border-t-vault-700 { border-color: #e5e7eb !important; }

                .from-vault-700 { --tw-gradient-from: #f3f4f6 !important; }
                .to-vault-800 { --tw-gradient-to: #e5e7eb !important; }

                ::-webkit-scrollbar-track { background: transparent !important; }
                ::-webkit-scrollbar-thumb { background: #d1d5db !important; border-radius: 4px !important; }
                ::-webkit-scrollbar-thumb:hover { background: #9ca3af !important; }

                .bg-vault-900 { background-color: #ffffff !important; }
                .bg-vault-950 { background-color: #f9fafb !important; }
                .divide-vault-700 > :not([hidden]) ~ :not([hidden]) { border-color: #e5e7eb !important; }
                /* ═══ Mobile Dropdown Menu ═══ */
                .sm\\:hidden.bg-vault-800 {
                    background-color: #ffffff !important;
                    border-color: #d1d5db !important;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.12) !important;
                }
                .sm\\:hidden .border-b-vault-700,
                .sm\\:hidden .border-t-vault-700 {
                    border-color: #e5e7eb !important;
                }
                .sm\\:hidden .text-vault-200 {
                    color: #111827 !important;
                }
                .sm\\:hidden .text-vault-400 {
                    color: #6b7280 !important;
                }
                .sm\\:hidden .hover\\:bg-vault-700:hover {
                    background-color: #f3f4f6 !important;
                }
                .sm\\:hidden .bg-amber-500\\/10 {
                    background-color: rgb(245 158 11 / 0.12) !important;
                }
                .sm\\:hidden .text-amber-400 {
                    color: #d97706 !important;
                }
                .sm\\:hidden .text-rose-400 {
                    color: #ef4444 !important;
                }
                .sm\\:hidden .hover\\:text-rose-300:hover {
                    color: #dc2626 !important;
                }
            `;
            document.head.appendChild(styleTag);
        } else {
            // Dark mode: remove ALL custom properties, let Tailwind defaults take over
            Object.keys(lightColors).forEach((prop) => {
                html.style.removeProperty(prop);
            });
        }
    }

    function toggleTheme() {
        applyTheme(theme.value === "dark" ? "light" : "dark");
    }

    applyTheme(theme.value);

    return { theme, toggleTheme, applyTheme };
}
