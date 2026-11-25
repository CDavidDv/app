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

createApp(App).use(router).mount('#app');
