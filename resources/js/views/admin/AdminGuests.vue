<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Check, Copy, Link2, Trash2, Plus, Clock } from 'lucide-vue-next';
import { errorMessage, http } from '@/api';
import { store } from '@/store';

interface Guest {
    id: number;
    name: string;
    relationship: string | null;
    contact: string | null;
    notes: string | null;
    participated: boolean;
    participants_count: number;
    personal_link: string | null;
}

const list = ref<Guest[]>([]);
const bulk = ref('');
const show = ref<'all' | 'pending' | 'done'>('all');
const error = ref<string | null>(null);
const copied = ref<number | null>(null);

async function load() {
    list.value = (await http.get<Guest[]>('/admin/guests')).data;
}
onMounted(load);

const filtered = computed(() => list.value.filter((g) => (show.value === 'all' ? true : show.value === 'done' ? g.participated : !g.participated)));
const pending = computed(() => list.value.filter((g) => !g.participated).length);

async function add() {
    error.value = null;
    if (!bulk.value.trim()) return;
    try {
        list.value = (await http.post<Guest[]>('/admin/guests', { bulk: bulk.value })).data;
        bulk.value = '';
    } catch (e) {
        error.value = errorMessage(e);
    }
}
async function remove(g: Guest) {
    if (!confirm(`¿Quitar a ${g.name} de la lista? (No borra lo que haya dejado)`)) return;
    await http.delete(`/admin/guests/${g.id}`);
    load();
}
async function link(g: Guest) {
    if (g.personal_link && !confirm('Ya tiene un enlace. ¿Generar uno nuevo? El anterior dejará de funcionar.')) return;
    const { data } = await http.post(`/admin/guests/${g.id}/link`);
    g.personal_link = data.personal_link;
    copy(g);
}
async function copy(g: Guest) {
    if (!g.personal_link) return;
    const her = store.state?.event?.celebrant_short_name ?? '';
    const text = `Hola ${g.name.split(' ')[0]}, estamos preparando una sorpresa para el cumpleaños de ${her}. ¿Le dejarías un mensaje, foto o video? Es secreto: ${g.personal_link}`;
    await navigator.clipboard.writeText(text).catch(() => prompt('Copia el mensaje:', text));
    copied.value = g.id;
    setTimeout(() => (copied.value = null), 2500);
}
</script>

<template>
    <div>
        <h1 class="text-4xl">Lista de invitados</h1>
        <p class="mb-6 text-ink-soft">
            Anota a quién quieres invitar para saber quién falta por participar. Puedes generar un enlace personal para cada quien (precarga su nombre) o usar el enlace general.
        </p>

        <div class="paper mb-8 rounded-3xl p-5">
            <label class="field-label">Agregar invitados (uno por línea)</label>
            <textarea v-model="bulk" class="field min-h-24" placeholder="Ana López&#10;Mamá&#10;Luis (prepa)" />
            <p v-if="error" class="field-error">{{ error }}</p>
            <button class="btn btn-primary btn-sm mt-3" @click="add"><Plus class="size-4" /> Agregar</button>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <button class="chip" :aria-pressed="show === 'all'" @click="show = 'all'">Todos · {{ list.length }}</button>
            <button class="chip" :aria-pressed="show === 'pending'" @click="show = 'pending'">Faltan · {{ pending }}</button>
            <button class="chip" :aria-pressed="show === 'done'" @click="show = 'done'">Ya participaron · {{ list.length - pending }}</button>
        </div>
        <p class="mb-4 text-xs text-muted">
            Quien entra con su enlace personal queda vinculado automáticamente. Si usó el enlace general, vincúlalo desde “Personas”.
        </p>

        <div class="grid gap-2">
            <div v-for="g in filtered" :key="g.id" class="paper flex flex-wrap items-center gap-3 rounded-2xl px-4 py-3">
                <span class="grid size-8 place-items-center rounded-full" :class="g.participated ? 'bg-sage/25 text-sage' : 'bg-linen text-muted'">
                    <Check v-if="g.participated" class="size-4" />
                    <Clock v-else class="size-4" />
                </span>
                <div class="min-w-40 flex-1">
                    <p class="font-medium">{{ g.name }}</p>
                    <p class="text-xs text-muted">{{ g.participated ? 'Ya participó' : 'Aún no participa' }}</p>
                </div>
                <button v-if="g.personal_link" class="btn btn-sm btn-ghost" @click="copy(g)"><Copy class="size-3.5" /> {{ copied === g.id ? 'Mensaje copiado' : 'Copiar invitación' }}</button>
                <button class="btn btn-sm btn-ghost" :title="g.personal_link ? 'Generar nuevo enlace' : 'Generar enlace personal'" @click="link(g)">
                    <Link2 class="size-3.5" /> <span v-if="!g.personal_link">Enlace personal</span>
                </button>
                <button class="btn btn-sm btn-ghost" @click="remove(g)"><Trash2 class="size-3.5 text-rose-deep" /></button>
            </div>
        </div>
    </div>
</template>
