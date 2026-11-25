<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { ArrowLeft, Copy, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import ContributionCard from '@/components/ContributionCard.vue';
import ContributionForm from '@/components/ContributionForm.vue';
import Sheet from '@/components/Sheet.vue';
import Ornament from '@/components/Ornament.vue';
import { errorMessage, fieldErrors, http } from '@/api';
import { loadState, store, formatDate } from '@/store';
import type { Contribution, DraftItem, Question } from '@/types';

const state = computed(() => store.state!);
const her = computed(() => state.value.event?.celebrant_short_name ?? '');
const accepting = computed(() => state.value.event?.accepting ?? false);

const list = ref<Contribution[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const link = ref<string | null>(null);
const questions = ref<Question[]>([]);
const copied = ref(false);

const editing = ref<Contribution | null>(null);
const draft = ref<DraftItem | null>(null);
const editErrors = ref<Record<string, string>>({});
const saving = ref(false);
const uploading = ref(false);

async function load() {
    loading.value = true;
    try {
        const { data } = await http.get('/my/contributions');
        list.value = data.contributions;
        link.value = data.personal_link;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    load();
    http.get<Question[]>('/questions').then((r) => (questions.value = r.data)).catch(() => {});
});

function edit(c: Contribution) {
    editing.value = c;
    editErrors.value = {};
    const answers: Record<number, string> = {};
    c.answers.forEach((a) => a.question_id && (answers[a.question_id] = a.answer));
    draft.value = {
        type: c.type,
        title: c.title ?? '',
        artist: c.artist ?? '',
        link: c.link ?? '',
        body: c.body ?? '',
        signature: c.signature ?? '',
        prompt: c.prompt ?? '',
        media: [...c.media],
        answers,
        chosenQuestions: Object.keys(answers).map(Number),
    };
}

async function save() {
    if (!editing.value || !draft.value) return;
    saving.value = true;
    editErrors.value = {};
    const d = draft.value;
    try {
        const { data } = await http.put<Contribution>(`/my/contributions/${editing.value.id}`, {
            title: d.title,
            artist: d.artist,
            link: d.link,
            body: d.body,
            signature: d.signature,
            prompt: d.prompt,
            media_ids: d.media.map((m) => m.id),
            answers: d.chosenQuestions.map((id) => ({ question_id: id, answer: d.answers[id] ?? '' })),
        });
        list.value = list.value.map((c) => (c.id === data.id ? data : c));
        editing.value = null;
    } catch (e) {
        editErrors.value = fieldErrors(e);
        if (!Object.keys(editErrors.value).length) editErrors.value = { body: errorMessage(e) };
    } finally {
        saving.value = false;
    }
}

async function remove(c: Contribution) {
    if (!window.confirm('¿Seguro que quieres eliminar esto? No se puede deshacer.')) return;
    try {
        await http.delete(`/my/contributions/${c.id}`);
        list.value = list.value.filter((x) => x.id !== c.id);
        loadState(true);
    } catch (e) {
        window.alert(errorMessage(e));
    }
}

async function copyLink() {
    if (!link.value) return;
    try {
        await navigator.clipboard.writeText(link.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2500);
    } catch {
        window.prompt('Copia tu enlace:', link.value);
    }
}
</script>

<template>
    <main class="mx-auto min-h-dvh max-w-2xl px-5 pb-24 pt-8 sm:pt-14">
        <RouterLink to="/participar" class="mb-8 inline-flex items-center gap-2 text-sm text-ink-soft hover:text-ink"><ArrowLeft class="size-4" /> Volver</RouterLink>
        <p class="eyebrow mb-3">Tu espacio personal</p>
        <h1 class="mb-3 text-4xl sm:text-5xl">Lo que le dejaste a {{ her }}</h1>
        <p class="text-ink-soft">
            <template v-if="accepting">Puedes editar o eliminar tus recuerdos hasta antes del {{ formatDate(state.event?.submissions_close_at) }}.</template>
            <template v-else>La cápsula ya está cerrada. Gracias por tu cariño.</template>
        </p>

        <div v-if="link" class="mt-6 flex flex-wrap items-center gap-3">
            <button class="btn btn-sm btn-ghost" @click="copyLink"><Copy class="size-4" /> {{ copied ? '¡Copiado!' : 'Copiar mi enlace personal' }}</button>
            <RouterLink v-if="accepting" to="/participar" class="btn btn-sm btn-primary"><Plus class="size-4" /> Dejarle algo más</RouterLink>
        </div>

        <Ornament class="my-10" />

        <p v-if="loading" class="text-center text-muted">Cargando tus recuerdos…</p>
        <p v-else-if="error" class="field-error text-center">{{ error }}</p>
        <p v-else-if="!list.length" class="text-center text-ink-soft">Todavía no has dejado nada. <RouterLink to="/participar" class="text-rose-deep underline">Empieza aquí</RouterLink>.</p>

        <div class="space-y-12">
            <div v-for="c in list" :key="c.id">
                <ContributionCard :item="c" :from="state.participant?.name" />
                <div v-if="accepting" class="mt-3 flex gap-2">
                    <button class="btn btn-sm btn-ghost" @click="edit(c)"><Pencil class="size-3.5" /> Editar</button>
                    <button class="btn btn-sm btn-ghost text-rose-deep" @click="remove(c)"><Trash2 class="size-3.5" /> Eliminar</button>
                </div>
            </div>
        </div>

        <Sheet :open="!!editing" wide label="Editar" @close="editing = null">
            <template v-if="draft">
                <h2 class="mb-6 text-3xl">Editar</h2>
                <ContributionForm v-model="draft" :questions="questions" :errors="editErrors" :celebrant="her" @busy="uploading = $event" />
                <div class="mt-8 flex gap-3">
                    <button class="btn btn-ghost" @click="editing = null">Cancelar</button>
                    <button class="btn btn-primary flex-1" :disabled="saving || uploading" @click="save">{{ saving ? 'Guardando…' : 'Guardar cambios' }}</button>
                </div>
            </template>
        </Sheet>
    </main>
</template>
