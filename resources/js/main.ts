import { createApp } from 'vue';
import '@fontsource/cormorant-garamond/400.css';
import '@fontsource/cormorant-garamond/500.css';
import '@fontsource/cormorant-garamond/600.css';
import '@fontsource/cormorant-garamond/400-italic.css';
import '@fontsource/cormorant-garamond/500-italic.css';
import '@fontsource-variable/inter';
import '@fontsource/caveat/500.css';
import App from './App.vue';
import router from './router';

/**
 * Espera a que estén las tipografías principales antes de mostrar la página,
 * para evitar el salto de "letra del sistema" a la tipografía final.
 * Si la conexión es lenta, se muestra igual tras un máximo de espera.
 */
function fontsReady(maxMs = 2500): Promise<unknown> {
    if (!('fonts' in document)) return Promise.resolve();
    const sample = 'Aaáéíóúñ';
    const faces = [
        '400 1em "Inter Variable"',
        '500 1em "Inter Variable"',
        '400 1em "Cormorant Garamond"',
        '500 1em "Cormorant Garamond"',
        'italic 400 1em "Cormorant Garamond"',
        '500 1em "Caveat"',
    ];
    const all = Promise.all(faces.map((f) => document.fonts.load(f, sample).catch(() => null)));
    return Promise.race([all, new Promise((r) => setTimeout(r, maxMs))]);
}

const app = createApp(App).use(router);
Promise.all([fontsReady(), router.isReady()]).finally(() => {
    app.mount('#app');
    document.documentElement.classList.add('fonts-ready');
});
