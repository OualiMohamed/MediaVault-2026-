import axios from "axios";

const api = axios.create({
    baseURL: "/api",
    headers: {
        Accept: "application/json",
        // Do NOT set Content-Type here — let axios auto-detect it
    },
    withCredentials: false,
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem("vault_token");
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem("vault_token");
            window.location.href = "/login";
        }
        return Promise.reject(error);
    },
);

export default api;
