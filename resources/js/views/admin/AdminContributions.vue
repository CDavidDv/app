<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Download, Eye, EyeOff, Pencil, Star, Trash2 } from 'lucide-vue-next';
import ContributionCard from '@/components/ContributionCard.vue';
import Lightbox, { type LightboxItem } from '@/components/Lightbox.vue';
import Sheet from '@/components/Sheet.vue';
import { errorMessage, http } from '@/api';
import { formatDate, typeLabels } from '@/store';
import type { Contribution } from '@/types';

const route = useRoute();
const list = ref<Contribution[]>([]);
const loading = ref(true);
const type = ref('');
const filter = ref<'all' | 'favorites' | 'hidden'>('all');
const participant = ref((route.query.participant as string) || '');
const editing = ref<Contribution | null>(null);
const form = ref({ title: '', body: '', signature: '', artist: '', link: '' });
const lightbox = ref<LightboxItem[]>([]);
const lbIndex = ref<number | null>(null);

async function load() {
    loading.value = true;
    const params: Record<string, string> = {};
    if (type.value) params.type = type.value;
    if (participant.value) params.participant = participant.value;
    if (filter.value === 'favorites') params.favorites = '1';
    if (filter.value === 'hidden') params.hidden = '1';
    list.value = (await http.get<Contribution[]>('/admin/contributions', { params })).data;
    loading.value = false;
}
onMounted(load);
watch([type, filter, participant], load);

async function patch(c: Contribution, body: Partial<Contribution>) {
    try {
        const { data } = await http.patch<Contribution>(`/admin/contributions/${c.id}`, body);
        Object.assign(c, data);
    } catch (e) {
        alert(errorMessage(e));
    }
}
async function remove(c: Contribution) {
    if (!confirm('¿Eliminar esta aportación y sus archivos? No se puede deshacer.')) return;
    await http.delete(`/admin/contributions/${c.id}`);
    list.value = list.value.filter((x) => x.id !== c.id);
}
function edit(c: Contribution) {
    editing.value = c;
    form.value = { title: c.title ?? '', body: c.body ?? '', signature: c.signature ?? '', artist: c.artist ?? '', link: c.link ?? '' };
}
async function saveEdit() {
    if (!editing.value) return;
    const { artist, link, ...rest } = form.value;
    await patch(editing.value, editing.value.type === 'song' ? { title: rest.title, body: rest.body, artist, link } : rest);
    editing.value = null;
}
function photos(c: Contribution, i: number) {
    lightbox.value = c.media.map((m) => ({ media: m, title: c.title, caption: c.body, author: c.participant?.name }));
    lbIndex.value = i;
}
const hasText = computed(() => editing.value && !['questions'].includes(editing.value.type));
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-4xl">Aportaciones</h1>
                <p class="text-ink-soft">{{ list.length }} {{ list.length === 1 ? 'resultado' : 'resultados' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <select v-model="type" class="field !w-auto !py-2 text-sm">
                    <option value="">Todos los tipos</option>
                    <option v-for="(l, t) in typeLabels" :key="t" :value="t">{{ l.short }}</option>
                </select>
                <select v-model="filter" class="field !w-auto !py-2 text-sm">
                    <option value="all">Todas</option>
                    <option value="favorites">Favoritas</option>
                    <option value="hidden">Ocultas</option>
                </select>
                <button v-if="participant" class="chip" aria-pressed="true" @click="participant = ''">Quitar filtro de persona ✕</button>
            </div>
        </div>

        <p v-if="loading" class="text-muted">Cargando…</p>
        <p v-else-if="!list.length" class="paper rounded-3xl p-8 text-center text-ink-soft">No hay aportaciones con estos filtros.</p>

        <div class="grid gap-6 lg:grid-cols-2">
            <article v-for="c in list" :key="c.id" class="paper rounded-3xl p-5" :class="c.is_hidden ? 'opacity-60' : ''">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="font-medium">{{ c.participant?.name }} <span v-if="c.participant?.display_name" class="text-muted">({{ c.participant?.real_name }})</span></p>
                        <p class="text-xs text-muted">{{ c.participant?.relationship_label }} · {{ formatDate(c.created_at, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}</p>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <button class="grid size-9 place-items-center rounded-full hover:bg-linen" :title="c.is_favorite ? 'Quitar de favoritas' : 'Marcar como favorita'" @click="patch(c, { is_favorite: !c.is_favorite })">
                            <Star class="size-4" :class="c.is_favorite ? 'fill-gold text-gold' : 'text-muted'" />
                        </button>
                        <button class="grid size-9 place-items-center rounded-full hover:bg-linen" :title="c.is_hidden ? 'Mostrar' : 'Ocultar temporalmente'" @click="patch(c, { is_hidden: !c.is_hidden })">
                            <EyeOff v-if="c.is_hidden" class="size-4 text-rose-deep" />
                            <Eye v-else class="size-4 text-muted" />
                        </button>
                        <button class="grid size-9 place-items-center rounded-full hover:bg-linen" title="Editar texto" @click="edit(c)"><Pencil class="size-4 text-muted" /></button>
                        <button class="grid size-9 place-items-center rounded-full hover:bg-linen" title="Eliminar" @click="remove(c)"><Trash2 class="size-4 text-rose-deep" /></button>
                    </div>
                </div>
                <div class="max-h-[34rem] overflow-y-auto pr-1">
                    <ContributionCard :item="c" :from="c.participant?.name" compact @photo="(i) => photos(c, i)" @letter="edit(c)" />
                </div>
                <div v-if="c.media.length" class="mt-4 flex flex-wrap gap-2">
                    <a v-for="(m, i) in c.media" :key="m.id" :href="`/api/admin/media/${m.id}/download`" class="btn btn-sm btn-ghost">
                        <Download class="size-3.5" /> {{ m.kind === 'video' ? 'Video' : 'Foto' }} {{ i + 1 }}
                    </a>
                </div>
            </article>
        </div>

        <Sheet :open="!!editing" wide label="Editar aportación" @close="editing = null">
            <template v-if="editing">
                <h2 class="mb-1 text-3xl">{{ typeLabels[editing.type].title }}</h2>
                <p class="mb-6 text-sm text-muted">De {{ editing.participant?.name }}. Corrige solo lo necesario (por ejemplo, un error de dedo).</p>
                <template v-if="editing.type === 'song'">
                    <label class="field-label">Canción</label>
                    <input v-model="form.title" class="field mb-4" />
                    <label class="field-label">Artista</label>
                    <input v-model="form.artist" class="field mb-4" />
                    <label class="field-label">Enlace</label>
                    <input v-model="form.link" type="url" class="field mb-4" />
                    <label class="field-label">Por qué le recuerda a ella</label>
                    <textarea v-model="form.body" class="field min-h-40" />
                    <button class="btn btn-primary mt-6 w-full" @click="saveEdit">Guardar</button>
                </template>
                <template v-else-if="hasText">
                    <label class="field-label">Título</label>
                    <input v-model="form.title" class="field mb-4" />
                    <label class="field-label">Texto</label>
                    <textarea v-model="form.body" class="field mb-4 min-h-72 font-serif text-lg" />
                    <label class="field-label">Firma</label>
                    <input v-model="form.signature" class="field" />
                    <button class="btn btn-primary mt-6 w-full" @click="saveEdit">Guardar</button>
                </template>
                <ContributionCard v-else :item="editing" />
            </template>
        </Sheet>
        <Lightbox :items="lightbox" :index="lbIndex" @close="lbIndex = null" @update:index="lbIndex = $event" />
    </div>
</template>
