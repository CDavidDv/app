<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { Copy, Download, EyeOff, Pencil, Trash2 } from 'lucide-vue-next';
import Sheet from '@/components/Sheet.vue';
import { errorMessage, http } from '@/api';
import { formatDate, store } from '@/store';
import type { Participant } from '@/types';

const list = ref<Participant[]>([]);
const guests = ref<{ id: number; name: string }[]>([]);
const editing = ref<Participant | null>(null);
const form = ref({ name: '', display_name: '', relationship: '', relationship_other: '', is_hidden: false, guest_id: null as number | null });
const error = ref<string | null>(null);
const copied = ref<string | null>(null);

async function load() {
    list.value = (await http.get<Participant[]>('/admin/participants')).data;
    guests.value = (await http.get('/admin/guests')).data;
}
onMounted(load);

function edit(p: Participant) {
    editing.value = p;
    error.value = null;
    form.value = {
        name: p.real_name ?? p.name,
        display_name: p.display_name ?? '',
        relationship: p.relationship,
        relationship_other: p.relationship_other ?? '',
        is_hidden: !!p.is_hidden,
        guest_id: p.guest_id ?? null,
    };
}
async function save() {
    if (!editing.value) return;
    try {
        await http.patch(`/admin/participants/${editing.value.id}`, form.value);
        editing.value = null;
        load();
    } catch (e) {
        error.value = errorMessage(e);
    }
}
async function remove(p: Participant) {
    if (!confirm(`¿Eliminar a ${p.name} y TODO lo que dejó? No se puede deshacer.`)) return;
    await http.delete(`/admin/participants/${p.id}`);
    load();
}
async function copy(p: Participant) {
    if (!p.personal_link) return;
    await navigator.clipboard.writeText(p.personal_link).catch(() => prompt('Enlace:', p.personal_link!));
    copied.value = p.id;
    setTimeout(() => (copied.value = null), 2000);
}
</script>

<template>
    <div>
        <h1 class="text-4xl">Personas que participaron</h1>
        <p class="mb-6 text-ink-soft">Edita cómo aparecen sus nombres y relaciones. Si alguien perdió su enlace personal, cópialo aquí y envíaselo.</p>

        <p v-if="!list.length" class="paper rounded-3xl p-8 text-center text-ink-soft">Aún no participa nadie.</p>
        <div class="grid gap-3">
            <div v-for="p in list" :key="p.id" class="paper flex flex-wrap items-center gap-4 rounded-2xl p-4" :class="p.is_hidden ? 'opacity-60' : ''">
                <div class="min-w-48 flex-1">
                    <p class="font-medium">
                        {{ p.real_name }}
                        <span v-if="p.display_name" class="font-normal text-muted">· aparece como “{{ p.display_name }}”</span>
                        <EyeOff v-if="p.is_hidden" class="ml-1 inline size-3.5 text-rose-deep" />
                    </p>
                    <p class="text-xs text-muted">{{ p.relationship_label }} · desde {{ formatDate(p.created_at, { day: 'numeric', month: 'short' }) }}</p>
                </div>
                <RouterLink :to="{ path: '/admin/aportaciones', query: { participant: p.id } }" class="text-sm text-rose-deep">{{ p.contributions_count }} aportaciones</RouterLink>
                <div class="flex gap-1">
                    <button v-if="p.personal_link" class="btn btn-sm btn-ghost" @click="copy(p)"><Copy class="size-3.5" /> {{ copied === p.id ? 'Copiado' : 'Enlace' }}</button>
                    <a v-if="p.contributions_count" :href="`/api/admin/export/${p.id}`" class="btn btn-sm btn-ghost" title="Descargar ZIP"><Download class="size-3.5" /></a>
                    <button class="btn btn-sm btn-ghost" @click="edit(p)"><Pencil class="size-3.5" /></button>
                    <button class="btn btn-sm btn-ghost" @click="remove(p)"><Trash2 class="size-3.5 text-rose-deep" /></button>
                </div>
            </div>
        </div>

        <Sheet :open="!!editing" label="Editar persona" @close="editing = null">
            <h2 class="mb-6 text-3xl">Editar persona</h2>
            <div class="space-y-4">
                <div>
                    <label class="field-label">Nombre</label>
                    <input v-model="form.name" class="field" />
                </div>
                <div>
                    <label class="field-label">Cómo la verá ella (opcional)</label>
                    <input v-model="form.display_name" class="field" />
                </div>
                <div>
                    <label class="field-label">Relación</label>
                    <select v-model="form.relationship" class="field">
                        <option v-for="(l, k) in store.state?.relationships" :key="k" :value="k">{{ l }}</option>
                    </select>
                    <input v-if="form.relationship === 'other'" v-model="form.relationship_other" class="field mt-2" placeholder="¿Cuál?" />
                </div>
                <div>
                    <label class="field-label">Vincular con invitado de la lista</label>
                    <select v-model="form.guest_id" class="field">
                        <option :value="null">— Ninguno —</option>
                        <option v-for="g in guests" :key="g.id" :value="g.id">{{ g.name }}</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_hidden" type="checkbox" class="size-4 accent-rose" /> Ocultar a esta persona temporalmente</label>
                <p v-if="error" class="field-error">{{ error }}</p>
                <button class="btn btn-primary w-full" @click="save">Guardar</button>
            </div>
        </Sheet>
    </div>
</template>
