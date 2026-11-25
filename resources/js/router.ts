import { createRouter, createWebHistory } from 'vue-router';
import { loadState } from './store';

const router = createRouter({
    history: createWebHistory(),
    scrollBehavior(to, _from, saved) {
        if (saved) return saved;
        if (to.hash) return { el: to.hash, behavior: 'smooth' };
        return { top: 0 };
    },
    routes: [
        { path: '/', component: () => import('./views/HomeView.vue') },
        { path: '/enlace-no-valido', component: () => import('./views/InvalidLinkView.vue') },

        // Invitados
        { path: '/participar', component: () => import('./views/participate/ParticipateView.vue'), meta: { guest: true } },
        { path: '/participar/mis-recuerdos', component: () => import('./views/participate/MyMemoriesView.vue'), meta: { guest: true } },

        // Cumpleañera
        { path: '/para-ti', component: () => import('./views/birthday/BirthdayView.vue') },

        // Administración
        { path: '/admin/login', component: () => import('./views/admin/AdminLogin.vue') },
        {
            path: '/admin',
            component: () => import('./views/admin/AdminLayout.vue'),
            meta: { admin: true },
            children: [
                { path: '', component: () => import('./views/admin/AdminOverview.vue') },
                { path: 'aportaciones', component: () => import('./views/admin/AdminContributions.vue') },
                { path: 'personas', component: () => import('./views/admin/AdminParticipants.vue') },
                { path: 'invitados', component: () => import('./views/admin/AdminGuests.vue') },
                { path: 'enlaces', component: () => import('./views/admin/AdminLinks.vue') },
                { path: 'configuracion', component: () => import('./views/admin/AdminSettings.vue') },
            ],
        },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
});

router.beforeEach(async (to) => {
    const state = await loadState().catch(() => null);
    if (to.meta.admin && !state?.admin) return '/admin/login';
    if (to.path === '/admin/login' && state?.admin) return '/admin';
    if (to.meta.guest && !state?.can_participate) return '/enlace-no-valido';
    return true;
});

export default router;
