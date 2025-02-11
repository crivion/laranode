import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";
import fs from "fs";

export default defineConfig({
    plugins: [
        laravel({
            input: "resources/js/app.jsx",
            refresh: true,
        }),
        react(),
    ],
    server: {
        host: "laranode.homevps",
        port: 5173,
        https: {
            key: fs.readFileSync(
                "/etc/apache2/mkcerts-ssl-homevps/laranode.homevps-key.pem",
            ),
            cert: fs.readFileSync(
                "/etc/apache2/mkcerts-ssl-homevps/laranode.homevps.pem",
            ),
        },
        cors: {
            origin: "https://laranode.homevps",
            credentials: true,
        },
    },
});
