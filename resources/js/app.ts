import '../css/app.css';

import { createPinia } from 'pinia';
import { createApp } from 'vue';

import App from './App.vue';
import router from './router';
import { usePortalStore } from './stores/portal';

const pinia = createPinia();
const app = createApp(App);

app.use(pinia);

router.beforeEach(async (to) => {
    const store = usePortalStore();

    if (!store.authChecked) {
        await store.checkSession();
    }

    if (to.meta.guest) {
        return store.authenticated ? { name: 'dashboard' } : true;
    }

    if (!store.authenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    const permission = to.meta.permission;
    if (typeof permission === 'string' && !store.can(permission)) {
        return { name: 'dashboard' };
    }

    if (!store.loaded && !store.loading) {
        await store.load();
    }

    return true;
});

app.use(router).mount('#app');
