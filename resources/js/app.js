import './bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import '../css/app.css';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { initGlobalScrollLock } from './utils/scrollLock';

const app = createApp(App);
app.use(router);
app.mount('#app');

// Initialize global background scroll lock for all modals across the app
initGlobalScrollLock(router);
