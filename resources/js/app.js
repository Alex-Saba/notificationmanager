import './bootstrap';

import { createApp } from 'vue';
import TemplatesApp from './Components/TemplatesApp.vue';
import NotificationsDemoApp from './Components/NotificationsDemoApp.vue';

const root = document.getElementById('app');
const uiConfig = window.__ACL_COMMUNICATIONS_UI__ ?? {};
const component = (root?.dataset.communicationsPage ?? uiConfig.page) === 'notifications'
    ? NotificationsDemoApp
    : TemplatesApp;

createApp(component).mount('#app');
