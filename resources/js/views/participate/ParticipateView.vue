<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { ArrowLeft, ArrowRight, Camera, Check, Feather, HelpCircle, Mail, MessageCircle, Music, Pencil, Video, Copy, Heart } from 'lucide-vue-next';
import Ornament from '@/components/Ornament.vue';
import ContributionForm from '@/components/ContributionForm.vue';
import ContributionCard from '@/components/ContributionCard.vue';
import { errorMessage, fieldErrors, http } from '@/api';
import { loadState, safeUrl, store, typeLabels, formatDate } from '@/store';
import type { ContributionType, DraftItem, Question } from '@/types';

type Step = 'welcome' | 'identity' | 'choose' | 'form' | 'review' | 'done';
const ORDER: ContributionType[] = ['letter', 'video', 'photo', 'message', 'song', 'secret', 'questions'];
const DRAFT_KEY = 'pedacito:draft:v1';

const state = computed(() => store.state!);
const event = computed(() => state.value.event!);
const her = computed(() => event.value.celebrant_short_name);

const step = ref<Step>('welcome');
const identity = reactive({ name: '', relationship: '', relationship_other: '', display_name: '' });
const selected = ref<ContributionType[]>([]);
const items = reactive<Record<string, DraftItem>>({});
const formIndex = ref(0);
const questions = ref<Question[]>([]);
const errors = ref<Record<string, string>>({});
const formErrors = reactive<Record<string, Record<string, string>>>({});
const error = ref<string | null>(null);
const saving = ref(false);
const uploading = ref(false);
const personalLink = ref<string | null>(null);
const copied = ref(false);

const cards = [
    { type: 'letter', icon: Mail },
    { type: 'video', icon: Video },
    { type: 'photo', icon: Camera },
    { type: 'message', icon: MessageCircle },
    { type: 'song', icon: Music },
    { type: 'secret', icon: Feather },
    { type: 'questions', icon: HelpCircle },
] as const;

function blank(type: ContributionType): DraftItem {
    return { type, title: '', artist: '', link: '', body: '', signature: '', prompt: '', media: [], answers: {}, chosenQuestions: [] };
}

const currentType = computed(() => selected.value[formIndex.value]);
const stepNumber = computed(() => ({ welcome: 0, identity: 1, choose: 2, form: 3, review: 4, done: 4 })[step.value]);

// ---------- Guardado temporal en el dispositivo ----------
function saveDraft() {
    try {
        localStorage.setItem(
            DRAFT_KEY,
            JSON.stringify({ step: step.value === 'done' ? 'welcome' : step.value, identity, selected: selected.value, items, formIndex: formIndex.value }),
        );
    } catch {
        /* almacenamiento no disponible */
    }
}
function restoreDraft() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return;
        const d = JSON.parse(raw);
        Object.assign(identity, d.identity ?? {});
        selected.value = (d.selected ?? []).filter((t: string) => ORDER.includes(t as ContributionType));
        for (const t of selected.value) items[t] = { ...blank(t), ...(d.items?.[t] ?? {}) };
        formIndex.value = Math.min(d.formIndex ?? 0, Math.max(0, selected.value.length - 1));
        if (state.value.participant && ['form', 'review', 'choose'].includes(d.step) && selected.value.length) step.value = d.step;
    } catch {
        /* borrador ilegible */
    }
}
function clearDraft() {
    try {
        localStorage.removeItem(DRAFT_KEY);
    } catch {
        /* */
    }
}
watch([step, identity, selected, items, formIndex], saveDraft, { deep: true });

onMounted(async () => {
    const p = state.value.participant;
    if (p) {
        identity.name = p.real_name ?? p.name;
        identity.relationship = p.relationship;
        identity.relationship_other = p.relationship_other ?? '';
        identity.display_name = p.display_name ?? '';
    } else if (state.value.prefill) {
        identity.name = state.value.prefill.name;
        identity.relationship = state.value.prefill.relationship ?? '';
    }
    restoreDraft();
    http.get<Question[]>('/questions').then((r) => (questions.value = r.data)).catch(() => {});
});

function go(s: Step) {
    error.value = null;
    step.value = s;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ---------- Paso 1: identificación ----------
async function saveIdentity() {
    errors.value = {};
    error.value = null;
    if (identity.name.trim().length < 2) errors.value.name = 'Escribe tu nombre.';
    if (!identity.relationship) errors.value.relationship = 'Elige tu relación con ella.';
    if (identity.relationship === 'other' && !identity.relationship_other.trim()) errors.value.relationship_other = 'Cuéntanos cuál es su relación.';
    if (Object.keys(errors.value).length) return;
    saving.value = true;
    try {
        const { data } = await http.post('/participant', identity);
        personalLink.value = data.personal_link;
        await loadState(true);
        go('choose');
    } catch (e) {
        errors.value = fieldErrors(e);
        error.value = errorMessage(e);
    } finally {
        saving.value = false;
    }
}

// ---------- Paso 2: elegir ----------
function toggle(type: ContributionType) {
    if (selected.value.includes(type)) {
        selected.value = selected.value.filter((t) => t !== type);
    } else {
        selected.value = ORDER.filter((t) => t === type || selected.value.includes(t));
        if (!items[type]) items[type] = blank(type);
    }
}
function startForms() {
    if (!selected.value.length) {
        error.value = 'Elige al menos una forma de participar.';
        return;
    }
    formIndex.value = 0;
    go('form');
}

// ---------- Paso 3: formularios ----------
function validateItem(it: DraftItem): Record<string, string> {
    const e: Record<string, string> = {};
    if (['letter', 'message', 'secret'].includes(it.type) && !it.body.trim()) e.body = 'Escribe algo antes de continuar.';
    if (it.type === 'photo' && !it.media.length) e.media_ids = 'Sube al menos una fotografía.';
    if (it.type === 'video' && !it.media.length) e.media_ids = 'Sube al menos un video.';
    if (it.type === 'song' && !it.link.trim()) e.link = 'Pega el enlace de la canción.';
    if (it.type === 'song' && it.link.trim() && !safeUrl(it.link)) e.link = 'Ese enlace no parece válido.';
    if (it.type === 'song' && !it.title.trim()) e.title = 'Escribe el nombre de la canción.';
    if (it.type === 'questions' && !it.chosenQuestions.some((id) => (it.answers[id] ?? '').trim())) e.answers = 'Responde al menos una pregunta.';
    return e;
}
function nextForm() {
    const t = currentType.value;
    formErrors[t] = validateItem(items[t]);
    if (Object.keys(formErrors[t]).length) return;
    if (formIndex.value < selected.value.length - 1) {
        formIndex.value++;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } else go('review');
}
function prevForm() {
    if (formIndex.value > 0) {
        formIndex.value--;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } else go('choose');
}
function editItem(type: ContributionType) {
    formIndex.value = selected.value.indexOf(type);
    go('form');
}

// ---------- Paso 4: enviar ----------
function payload(it: DraftItem) {
    return {
        type: it.type,
        title: it.title,
        artist: it.artist,
        link: it.link,
        body: it.body,
        signature: it.signature,
        prompt: it.prompt,
        media_ids: it.media.map((m) => m.id),
        answers: it.chosenQuestions.map((id) => ({ question_id: id, answer: it.answers[id] ?? '' })).filter((a) => a.answer.trim()),
    };
}
async function submit() {
    saving.value = true;
    error.value = null;
    try {
        const { data } = await http.post('/my/contributions', { items: selected.value.map((t) => payload(items[t])) });
        personalLink.value = data.personal_link;
        clearDraft();
        selected.value = [];
        for (const k of Object.keys(items)) delete items[k];
        await loadState(true);
        go('done');
    } catch (e) {
        const fe = fieldErrors(e);
        const firstKey = Object.keys(fe)[0];
        const m = firstKey?.match(/^items\.(\d+)\.(.+)$/);
        if (m) {
            const t = selected.value[Number(m[1])];
            formErrors[t] = { [m[2]]: fe[firstKey] };
            formIndex.value = Number(m[1]);
            go('form');
        }
        error.value = errorMessage(e);
    } finally {
        saving.value = false;
    }
}

async function copyLink() {
    if (!personalLink.value) return;
    try {
        await navigator.clipboard.writeText(personalLink.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2500);
    } catch {
        window.prompt('Copia tu enlace:', personalLink.value);
    }
}

function again() {
    selected.value = [];
    go('choose');
}

const closeText = computed(() =>
    event.value.submissions_close_at ? formatDate(event.value.submissions_close_at, { day: 'numeric', month: 'long' }) : null,
);
</script>

<template>
    <main class="mx-auto min-h-dvh max-w-2xl px-5 pb-28 pt-8 sm:pt-14" style="padding-top: max(2rem, env(safe-area-inset-top))">
        <!-- Barra de progreso -->
        <div v-if="stepNumber > 0 && step !== 'done'" class="mb-10 flex items-center gap-3">
            <div class="flex flex-1 gap-1.5">
                <div v-for="n in 4" :key="n" class="h-1 flex-1 rounded-full transition-colors duration-500" :class="n <= stepNumber ? 'bg-rose' : 'bg-sand'" />
            </div>
            <span class="text-xs text-muted">Paso {{ stepNumber }} de 4</span>
        </div>

        <!-- Recepción cerrada -->
        <section v-if="!event.accepting && step !== 'done'" class="pt-16 text-center animate-rise">
            <Heart class="mx-auto mb-6 size-8 text-rose" :stroke-width="1.3" />
            <h1 class="mb-5 text-4xl">La cápsula ya está cerrada</h1>
            <Ornament class="mx-auto mb-6" />
            <p class="text-ink-soft leading-relaxed">Ya no se reciben nuevos recuerdos. Gracias de corazón por ser parte de la vida de {{ her }}.</p>
            <RouterLink v-if="state.participant" to="/participar/mis-recuerdos" class="btn btn-ghost mt-8">Ver lo que dejé</RouterLink>
        </section>

        <Transition v-else name="slide" mode="out-in">
            <!-- Bienvenida -->
            <section v-if="step === 'welcome'" key="welcome" class="pt-4 text-center sm:pt-10">
                <p class="eyebrow mb-6 animate-rise">Para {{ her }} · {{ formatDate(event.birthday_date) }}</p>
                <div v-if="event.cover" class="mx-auto mb-10 w-44 rotate-[-2deg] bg-paper p-2.5 pb-8 shadow-[var(--shadow-lift)] animate-rise sm:w-52">
                    <img :src="event.cover.thumb" alt="" class="aspect-[4/5] w-full object-cover" />
                </div>
                <h1 class="mb-6 text-[2.6rem] leading-[1.08] sm:text-6xl animate-rise" style="animation-delay: 0.1s">
                    {{ event.invite_title }}
                </h1>
                <Ornament class="mx-auto mb-6 animate-rise" style="animation-delay: 0.2s" />
                <p class="mx-auto max-w-lg text-[1.05rem] leading-relaxed text-ink-soft animate-rise" style="animation-delay: 0.3s">
                    {{ event.invite_message }}
                </p>
                <p v-if="closeText" class="mt-5 text-sm text-muted animate-rise" style="animation-delay: 0.35s">Puedes participar hasta antes del {{ closeText }}.</p>

                <div class="mt-10 flex flex-col items-center gap-3 animate-rise" style="animation-delay: 0.45s">
                    <template v-if="state.participant">
                        <p class="mb-2 font-serif text-xl italic">Hola de nuevo, {{ state.participant.real_name }}.</p>
                        <button class="btn btn-rose w-full max-w-xs" @click="go('choose')">Dejarle algo más <ArrowRight class="size-4" /></button>
                        <RouterLink v-if="state.participant.contributions_count" to="/participar/mis-recuerdos" class="btn btn-ghost w-full max-w-xs">
                            Ver lo que ya dejé ({{ state.participant.contributions_count }})
                        </RouterLink>
                    </template>
                    <button v-else class="btn btn-rose w-full max-w-xs text-base" @click="go('identity')">Quiero dejarle algo especial <ArrowRight class="size-4" /></button>
                    <p class="mt-4 max-w-xs text-xs leading-relaxed text-muted">Lo que compartas será solo para ella. Nadie más que participe podrá verlo.</p>
                </div>
            </section>

            <!-- Paso 1: Identificación -->
            <section v-else-if="step === 'identity'" key="identity">
                <h2 class="mb-2 text-4xl">Primero, ¿quién eres?</h2>
                <p class="mb-8 text-ink-soft">Así {{ her }} sabrá de quién viene cada recuerdo.</p>
                <form class="space-y-7" @submit.prevent="saveIdentity">
                    <div>
                        <label class="field-label" for="i-name">Tu nombre</label>
                        <input id="i-name" v-model="identity.name" class="field" autocomplete="name" maxlength="120" placeholder="Nombre y apellido" />
                        <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
                    </div>
                    <div>
                        <p class="field-label">¿Qué relación tienes con ella?</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="(label, key) in state.relationships"
                                :key="key"
                                type="button"
                                class="chip"
                                :aria-pressed="identity.relationship === key"
                                @click="identity.relationship = String(key)"
                            >
                                {{ label }}
                            </button>
                        </div>
                        <p v-if="errors.relationship" class="field-error">{{ errors.relationship }}</p>
                        <div v-if="identity.relationship === 'other'" class="mt-3">
                            <input v-model="identity.relationship_other" class="field" maxlength="60" placeholder="Por ejemplo: vecina, maestra, mentora…" />
                            <p v-if="errors.relationship_other" class="field-error">{{ errors.relationship_other }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="field-label" for="i-display">¿Cómo te gustaría que ella te identificara? <span class="font-normal text-muted">(opcional)</span></label>
                        <input id="i-display" v-model="identity.display_name" class="field" maxlength="120" placeholder="Tu prima Ana, tu compañero de la prepa…" />
                    </div>
                    <p v-if="error" class="field-error">{{ error }}</p>
                    <div class="flex gap-3 pt-2">
                        <button type="button" class="btn btn-ghost" @click="go('welcome')"><ArrowLeft class="size-4" /></button>
                        <button type="submit" class="btn btn-primary flex-1" :disabled="saving">{{ saving ? 'Guardando…' : 'Continuar' }} <ArrowRight class="size-4" /></button>
                    </div>
                </form>
            </section>

            <!-- Paso 2: Elegir -->
            <section v-else-if="step === 'choose'" key="choose">
                <h2 class="mb-2 text-4xl">¿Cómo quieres expresarte?</h2>
                <p class="mb-8 text-ink-soft">Puedes elegir una o varias. No hay forma correcta: lo importante es que venga de ti.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="c in cards"
                        :key="c.type"
                        type="button"
                        class="group relative flex items-start gap-4 rounded-3xl border p-5 text-left transition duration-300"
                        :class="selected.includes(c.type) ? 'border-rose bg-paper shadow-[var(--shadow-lift)]' : 'border-sand/80 bg-paper/60 hover:bg-paper'"
                        :aria-pressed="selected.includes(c.type)"
                        @click="toggle(c.type)"
                    >
                        <span class="grid size-12 shrink-0 place-items-center rounded-2xl transition" :class="selected.includes(c.type) ? 'bg-rose text-white' : 'bg-rose-soft/60 text-rose-deep'">
                            <component :is="c.icon" class="size-5" :stroke-width="1.5" />
                        </span>
                        <span class="flex-1 pr-6">
                            <span class="block font-serif text-[1.4rem] leading-tight">{{ typeLabels[c.type].title }}</span>
                            <span class="mt-1 block text-sm leading-snug text-ink-soft">{{ typeLabels[c.type].hint }}</span>
                        </span>
                        <span class="absolute right-4 top-4 grid size-6 place-items-center rounded-full border transition" :class="selected.includes(c.type) ? 'border-rose bg-rose text-white' : 'border-sand'">
                            <Check v-if="selected.includes(c.type)" class="size-3.5" />
                        </span>
                    </button>
                </div>
                <p v-if="error" class="field-error mt-4">{{ error }}</p>
                <div class="sticky bottom-0 -mx-5 mt-8 flex gap-3 bg-gradient-to-t from-cream via-cream to-transparent px-5 pb-5 pt-6" style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
                    <button v-if="!state.participant" type="button" class="btn btn-ghost" @click="go('identity')"><ArrowLeft class="size-4" /></button>
                    <button v-else type="button" class="btn btn-ghost" @click="go('identity')"><Pencil class="size-4" /></button>
                    <button class="btn btn-primary flex-1" :disabled="!selected.length" @click="startForms">
                        Continuar<span v-if="selected.length"> · {{ selected.length }}</span> <ArrowRight class="size-4" />
                    </button>
                </div>
            </section>

            <!-- Paso 3: Formularios -->
            <section v-else-if="step === 'form' && currentType" :key="'form-' + currentType">
                <p v-if="selected.length > 1" class="eyebrow mb-3">{{ formIndex + 1 }} de {{ selected.length }}</p>
                <h2 class="mb-2 text-4xl">{{ typeLabels[currentType].title }}</h2>
                <p class="mb-8 text-ink-soft">{{ typeLabels[currentType].hint }}</p>
                <ContributionForm
                    v-model="items[currentType]"
                    :questions="questions"
                    :errors="formErrors[currentType]"
                    :celebrant="her"
                    @busy="uploading = $event"
                />
                <p v-if="error" class="field-error mt-4">{{ error }}</p>
                <div class="sticky bottom-0 -mx-5 mt-8 flex gap-3 bg-gradient-to-t from-cream via-cream to-transparent px-5 pb-5 pt-6" style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
                    <button type="button" class="btn btn-ghost" @click="prevForm"><ArrowLeft class="size-4" /></button>
                    <button class="btn btn-primary flex-1" :disabled="uploading" @click="nextForm">
                        <template v-if="uploading">Esperando a que termine la subida…</template>
                        <template v-else>{{ formIndex < selected.length - 1 ? 'Siguiente' : 'Ver cómo quedó' }} <ArrowRight class="size-4" /></template>
                    </button>
                </div>
            </section>

            <!-- Paso 4: Vista previa -->
            <section v-else-if="step === 'review'" key="review">
                <h2 class="mb-2 text-4xl">Así lo verá {{ her }}</h2>
                <p class="mb-8 text-ink-soft">Revisa con calma. Puedes editar cualquier parte antes de enviarla.</p>
                <div class="space-y-10">
                    <div v-for="t in selected" :key="t" class="relative">
                        <ContributionCard :item="items[t]" :from="identity.display_name || identity.name" :questions="questions" />
                        <button class="btn btn-sm btn-ghost mt-3" @click="editItem(t)"><Pencil class="size-3.5" /> Editar</button>
                    </div>
                </div>
                <p class="mt-10 text-center font-hand text-2xl text-ink-soft">— {{ identity.display_name || identity.name }}</p>
                <p v-if="error" class="field-error mt-4 text-center">{{ error }}</p>
                <div class="sticky bottom-0 -mx-5 mt-8 flex gap-3 bg-gradient-to-t from-cream via-cream to-transparent px-5 pb-5 pt-6" style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
                    <button type="button" class="btn btn-ghost" @click="formIndex = selected.length - 1; go('form')"><ArrowLeft class="size-4" /></button>
                    <button class="btn btn-rose flex-1" :disabled="saving" @click="submit">
                        {{ saving ? 'Enviando con cariño…' : 'Enviar mi recuerdo' }} <Heart v-if="!saving" class="size-4" />
                    </button>
                </div>
            </section>

            <!-- Confirmación -->
            <section v-else-if="step === 'done'" key="done" class="pt-10 text-center">
                <div class="mx-auto mb-8 grid size-16 place-items-center rounded-full bg-rose text-white shadow-[var(--shadow-lift)] animate-rise">
                    <Heart class="size-7" :stroke-width="1.5" />
                </div>
                <h1 class="mb-6 text-[2.5rem] leading-tight sm:text-5xl animate-rise">¡Tu recuerdo ya forma parte de esta sorpresa!</h1>
                <Ornament class="mx-auto mb-6" />
                <p class="mx-auto max-w-md text-lg leading-relaxed text-ink-soft animate-rise" style="animation-delay: 0.15s">
                    Gracias por regalarle un pedacito de ti. Estamos seguros de que significará muchísimo para ella.
                </p>

                <div v-if="personalLink" class="paper mx-auto mt-10 max-w-md rounded-3xl p-6 text-left animate-rise" style="animation-delay: 0.3s">
                    <p class="font-serif text-xl">Tu enlace personal</p>
                    <p class="mt-1 text-sm leading-relaxed text-ink-soft">
                        Guárdalo para volver a ver, editar o agregar algo más desde cualquier dispositivo<span v-if="closeText"> hasta antes del {{ closeText }}</span>. No lo compartas: es solo tuyo.
                    </p>
                    <button class="btn btn-sm btn-primary mt-4 w-full" @click="copyLink">
                        <Copy class="size-4" /> {{ copied ? '¡Copiado!' : 'Copiar mi enlace' }}
                    </button>
                </div>

                <div class="mt-8 flex flex-col items-center gap-3">
                    <button class="btn btn-ghost w-full max-w-xs" @click="again">Dejarle algo más</button>
                    <RouterLink to="/participar/mis-recuerdos" class="text-sm text-rose-deep underline-offset-4 hover:underline">Ver lo que dejé</RouterLink>
                </div>
            </section>
        </Transition>
    </main>
</template>
