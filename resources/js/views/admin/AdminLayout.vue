<script setup lang="ts">
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { Home, Inbox, Users, ListChecks, Link2, Settings, LogOut, Eye } from 'lucide-vue-next';
import { http } from '@/api';
import { loadState, store } from '@/store';

const router = useRouter();
const nav = [
    { to: '/admin', label: 'Resumen', icon: Home, exact: true },
    { to: '/admin/aportaciones', label: 'Aportaciones', icon: Inbox },
    { to: '/admin/personas', label: 'Personas', icon: Users },
    { to: '/admin/invitados', label: 'Invitados', icon: ListChecks },
    { to: '/admin/enlaces', label: 'Enlaces', icon: Link2 },
    { to: '/admin/configuracion', label: 'Configuración', icon: Settings },
];

async function logout() {
    await http.post('/admin/logout').catch(() => {});
    await loadState(true);
    router.replace('/admin/login');
}
</script>

<template>
    <div class="min-h-dvh">
        <header class="sticky top-0 z-30 border-b border-sand/60 bg-cream/90 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <div>
                    <p class="font-serif text-xl leading-none">Un pedacito de todos</p>
                    <p class="text-xs text-muted">Para {{ store.state?.event?.celebrant_short_name }}</p>
                </div>
                <div class="flex items-center gap-1">
                    <RouterLink to="/para-ti" class="btn btn-sm btn-ghost" title="Ver la página de la cumpleañera"><Eye class="size-4" /><span class="hidden sm:inline">Ver su página</span></RouterLink>
                    <button class="btn btn-sm btn-ghost" title="Cerrar sesión" @click="logout"><LogOut class="size-4" /></button>
                </div>
            </div>
            <nav class="scrollbar-none mx-auto flex max-w-6xl gap-1 overflow-x-auto px-3 pb-2">
                <RouterLink
                    v-for="n in nav"
                    :key="n.to"
                    :to="n.to"
                    class="flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-2 text-sm text-ink-soft transition hover:bg-paper"
                    :exact-active-class="n.exact ? '!bg-ink !text-paper' : ''"
                    :active-class="n.exact ? '' : '!bg-ink !text-paper'"
                >
                    <component :is="n.icon" class="size-4" :stroke-width="1.5" /> {{ n.label }}
                </RouterLink>
            </nav>
        </header>
        <main class="mx-auto max-w-6xl px-4 py-8 pb-24">
            <RouterView />
        </main>
    </div>
</template>
