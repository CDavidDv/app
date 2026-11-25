<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { Download } from 'lucide-vue-next';
import { formatBytes, http } from '@/api';
import { formatDate, store, typeLabels } from '@/store';

interface Overview {
    participants: number;
    contributions: number;
    hidden: number;
    favorites: number;
    by_type: Record<string, number>;
    media: { photos: number; videos: number; bytes: number };
    guests: { total: number; participated: number };
    latest: { id: string; type: string; participant: string; created_at: string }[];
}

const data = ref<Overview | null>(null);
onMounted(async () => (data.value = (await http.get<Overview>('/admin/overview')).data));
</script>

<template>
    <div v-if="data">
        <h1 class="mb-1 text-4xl">Hola, {{ store.state?.admin?.name }}</h1>
        <p class="mb-8 text-ink-soft">
            Así va la sorpresa para {{ store.state?.event?.celebrant_short_name }}.
            <template v-if="store.state?.event?.submissions_close_at">Se reciben recuerdos hasta el {{ formatDate(store.state.event.submissions_close_at, { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }) }}.</template>
        </p>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="paper rounded-3xl p-5">
                <p class="eyebrow">Personas</p>
                <p class="mt-2 font-serif text-5xl">{{ data.participants }}</p>
            </div>
            <div class="paper rounded-3xl p-5">
                <p class="eyebrow">Recuerdos</p>
                <p class="mt-2 font-serif text-5xl">{{ data.contributions }}</p>
            </div>
            <div class="paper rounded-3xl p-5">
                <p class="eyebrow">Fotos · Videos</p>
                <p class="mt-2 font-serif text-5xl">{{ data.media.photos }} · {{ data.media.videos }}</p>
                <p class="mt-1 text-xs text-muted">{{ formatBytes(data.media.bytes) }} en total</p>
            </div>
            <div class="paper rounded-3xl p-5">
                <p class="eyebrow">Invitados que participaron</p>
                <p class="mt-2 font-serif text-5xl">{{ data.guests.participated }}<span class="text-2xl text-muted"> / {{ data.guests.total }}</span></p>
            </div>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <div class="paper rounded-3xl p-6">
                <h2 class="mb-4 text-2xl">Por tipo</h2>
                <ul class="space-y-2">
                    <li v-for="(l, t) in typeLabels" :key="t" class="flex justify-between border-b border-sand/50 pb-2 text-sm">
                        <span>{{ l.title }}</span><span class="font-medium">{{ data.by_type[t] ?? 0 }}</span>
                    </li>
                </ul>
                <p class="mt-4 text-xs text-muted">{{ data.favorites }} favoritas · {{ data.hidden }} ocultas</p>
            </div>
            <div class="paper rounded-3xl p-6">
                <h2 class="mb-4 text-2xl">Lo más reciente</h2>
                <p v-if="!data.latest.length" class="text-sm text-muted">Todavía no hay aportaciones. Comparte el enlace de invitación.</p>
                <ul class="space-y-2">
                    <li v-for="l in data.latest" :key="l.id" class="flex justify-between gap-3 border-b border-sand/50 pb-2 text-sm">
                        <span><strong class="font-medium">{{ l.participant }}</strong> · {{ typeLabels[l.type]?.short }}</span>
                        <span class="shrink-0 text-muted">{{ formatDate(l.created_at, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}</span>
                    </li>
                </ul>
                <RouterLink to="/admin/aportaciones" class="mt-4 inline-block text-sm text-rose-deep">Ver todas</RouterLink>
            </div>
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <RouterLink to="/admin/enlaces" class="btn btn-primary">Copiar enlaces</RouterLink>
            <a v-if="data.participants" href="/api/admin/export" class="btn btn-ghost"><Download class="size-4" /> Descargar respaldo (ZIP)</a>
        </div>
    </div>
</template>
