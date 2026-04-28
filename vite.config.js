// vite.config.js
import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({ input: ["resources/js/app.js"], refresh: true }),
        vue(),
        tailwindcss(),
    ],
    server: {
        proxy: {
            "/api": {
                target: "http://localhost:8000",
                changeOrigin: true,
            },
            "/storage": {
                target: "http://localhost:8000",
                changeOrigin: true,
            },
            "/login": {
                target: "http://localhost:8000",
                changeOrigin: true,
            },
        },
    },
});
