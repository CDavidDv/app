<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ChevronDown, Lock, Music2, Pause, Share2, Star, Eye } from 'lucide-vue-next';
import Ornament from '@/components/Ornament.vue';
import Sheet from '@/components/Sheet.vue';
import Lightbox, { type LightboxItem } from '@/components/Lightbox.vue';
import LetterEnvelope from '@/components/LetterEnvelope.vue';
import LetterPaper from '@/components/LetterPaper.vue';
import ContributionCard from '@/components/ContributionCard.vue';
import { errorMessage, http } from '@/api';
import { loadState, store, formatDate, typeLabels } from '@/store';
import type { Contribution, EventInfo, Person } from '@/types';

interface Memories {
    ready: boolean;
    preview?: boolean;
    share_url?: string | null;
    event: EventInfo;
    stats?: { people: number; memories: number };
    people?: Person[];
}

const router = useRouter();
const data = ref<Memories | null>(null);
const loading = ref(true);
const needsPassword = ref(false);
const password = ref('');
const pwError = ref<string | null>(null);
const unlocking = ref(false);
const loadError = ref<string | null>(null);

const opened = ref(false);
const tab = ref<'personas' | 'cartas' | 'fotos' | 'videos' | 'palabras' | 'canciones' | 'especiales'>('personas');
const person = ref<Person | null>(null);
const letter = ref<{ c: Contribution; p: Person } | null>(null);
const lightboxItems = ref<LightboxItem[]>([]);
const lightboxIndex = ref<number | null>(null);
const shared = ref(false);

const audio = ref<HTMLAudioElement | null>(null);
const playing = ref(false);

const event = computed(() => data.value?.event);
const people = computed(() => data.value?.people ?? []);
const name = computed(() => event.value?.celebrant_short_name ?? '');

type Entry = { c: Contribution; p: Person };
const all = computed<Entry[]>(() => people.value.flatMap((p) => p.contributions.map((c) => ({ c, p }))));
const byType = (...types: string[]) => all.value.filter((e) => types.includes(e.c.type));
const letters = computed(() => byType('letter'));
const videos = computed(() => byType('video'));
const messages = computed(() => byType('message'));
const songs = computed(() => byType('song'));
const specials = computed(() => byType('secret', 'questions'));
const favorites = computed(() => all.value.filter((e) => e.c.is_favorite));
const photoItems = computed<LightboxItem[]>(() =>
    byType('photo').flatMap(({ c, p }) => c.media.map((m) => ({ media: m, title: c.title, caption: c.body, author: p.name }))),
);

const tabs = computed(() =>
    [
        { id: 'personas', label: 'Personas', n: people.value.length },
        { id: 'cartas', label: 'Cartas', n: letters.value.length },
        { id: 'fotos', label: 'Fotografías', n: photoItems.value.length },
        { id: 'videos', label: 'Videos', n: videos.value.length },
        { id: 'palabras', label: 'Palabras', n: messages.value.length },
        { id: 'canciones', label: 'Canciones', n: songs.value.length },
        { id: 'especiales', label: 'Recuerdos especiales', n: specials.value.length },
    ].filter((t) => t.n > 0) as { id: typeof tab.value; label: string; n: number }[],
);

async function load() {
    loading.value = true;
    loadError.value = null;
    try {
        const s = await loadState(true);
        if (!s.birthday.linked && !s.admin) {
            router.replace('/');
            return;
        }
        if (s.birthday.needs_password && !s.admin) {
            needsPassword.value = true;
            return;
        }
        needsPassword.value = false;
        const r = await http.get<Memories>('/birthday/memories');
        data.value = r.data;
        try {
            opened.value = sessionStorage.getItem('pedacito:opened') === '1' || !!r.data.preview;
        } catch {
            opened.value = false;
        }
    } catch (e) {
        loadError.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function unlock() {
    unlocking.value = true;
    pwError.value = null;
    try {
        await http.post('/birthday/unlock', { password: password.value });
        await load();
    } catch (e) {
        pwError.value = errorMessage(e);
    } finally {
        unlocking.value = false;
    }
}

function open() {
    opened.value = true;
    try {
        sessionStorage.setItem('pedacito:opened', '1');
    } catch {
        /* */
    }
    window.scrollTo({ top: 0 });
}

function showPhotos(items: LightboxItem[], i: number) {
    lightboxItems.value = items;
    lightboxIndex.value = i;
}
function showPhotoOf(c: Contribution, p: Person, i: number) {
    showPhotos(c.media.map((m) => ({ media: m, title: c.title, caption: c.body, author: p.name })), i);
}

function toggleMusic() {
    const a = audio.value;
    if (!a) return;
    if (a.paused) {
        a.volume = 0.45;
        a.play().then(() => (playing.value = true)).catch(() => (playing.value = false));
    } else {
        a.pause();
        playing.value = false;
    }
}

async function share() {
    const url = data.value?.share_url || window.location.href;
    const payload = { title: 'Un pedacito de todos', text: `Recuerdos para ${name.value}`, url };
    try {
        if (navigator.share) await navigator.share(payload);
        else {
            await navigator.clipboard.writeText(url);
            shared.value = true;
            setTimeout(() => (shared.value = false), 2500);
        }
    } catch {
        /* cancelado */
    }
}

const tilt = (i: number) => [-1.6, 1.2, -0.6, 1.8, -1.2, 0.8][i % 6];
const initials = (n: string) =>
    n
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w.charAt(0).toUpperCase())
        .join('');

// Cuenta regresiva mientras el regalo se prepara.
const now = ref(Date.now());
let timer: number | undefined;
const countdown = computed(() => {
    const d = event.value?.birthday_date;
    if (!d) return null;
    const diff = new Date(d + 'T00:00:00').getTime() - now.value;
    if (diff <= 0) return null;
    return { days: Math.floor(diff / 864e5), hours: Math.floor((diff / 36e5) % 24), minutes: Math.floor((diff / 6e4) % 60) };
});

onMounted(() => {
    load();
    timer = window.setInterval(() => (now.value = Date.now()), 30000);
});
onBeforeUnmount(() => window.clearInterval(timer));

const coverStyle = computed(() => ({ animationDelay: '0.2s' }));
</script>

<template>
    <div class="min-h-dvh">
        <!-- Cargando -->
        <div v-if="loading" class="grid min-h-dvh place-items-center">
            <Ornament class="animate-pulse" />
        </div>

        <!-- Contraseña -->
        <main v-else-if="needsPassword" class="grid min-h-dvh place-items-center px-6">
            <form class="w-full max-w-sm text-center animate-rise" @submit.prevent="unlock">
                <div class="mx-auto mb-8 grid size-14 place-items-center rounded-full bg-paper shadow-[var(--shadow-paper)]">
                    <Lock class="size-5 text-rose" :stroke-width="1.5" />
                </div>
                <h1 class="mb-3 text-4xl">Algo te espera aquí</h1>
                <p class="mb-8 text-ink-soft">Escribe la palabra secreta para entrar.</p>
                <input v-model="password" type="password" class="field text-center" autocomplete="current-password" placeholder="Palabra secreta" />
                <p v-if="pwError" class="field-error">{{ pwError }}</p>
                <button class="btn btn-primary mt-5 w-full" :disabled="unlocking || !password">{{ unlocking ? 'Abriendo…' : 'Entrar' }}</button>
            </form>
        </main>

        <main v-else-if="loadError" class="grid min-h-dvh place-items-center px-6 text-center">
            <div>
                <p class="mb-4 text-ink-soft">{{ loadError }}</p>
                <button class="btn btn-ghost" @click="load">Intentar de nuevo</button>
            </div>
        </main>

        <!-- Aún no está listo -->
        <main v-else-if="data && !data.ready" class="grid min-h-dvh place-items-center px-6 py-16 text-center">
            <div class="max-w-lg animate-rise">
                <p class="eyebrow mb-6">Para {{ name }}</p>
                <h1 class="mb-6 text-5xl leading-tight">Tu regalo se está preparando</h1>
                <Ornament class="mx-auto mb-8" />
                <p class="text-lg leading-relaxed text-ink-soft">Muchas personas que te quieren están dejando algo aquí para ti. Vuelve muy pronto.</p>
                <div v-if="countdown" class="mt-10 flex justify-center gap-6">
                    <div v-for="(v, k) in { días: countdown.days, horas: countdown.hours, minutos: countdown.minutes }" :key="k" class="text-center">
                        <p class="font-serif text-5xl tabular-nums">{{ v }}</p>
                        <p class="eyebrow mt-1">{{ k }}</p>
                    </div>
                </div>
            </div>
        </main>

        <template v-else-if="data && event">
            <!-- Apertura -->
            <Transition name="fade">
                <div v-if="!opened" class="fixed inset-0 z-50 grid place-items-center bg-cream px-6 text-center">
                    <div class="animate-rise">
                        <p class="eyebrow mb-6">{{ formatDate(event.birthday_date, { day: 'numeric', month: 'long', year: 'numeric' }) }}</p>
                        <h1 class="mb-3 text-6xl leading-none sm:text-7xl">Para {{ name }}</h1>
                        <p class="mb-10 font-serif text-2xl italic text-ink-soft">de parte de quienes te quieren</p>
                        <button class="btn btn-rose px-10 text-base" @click="open">Abrir mi regalo</button>
                    </div>
                </div>
            </Transition>

            <div v-if="data.preview" class="sticky top-0 z-30 flex items-center justify-center gap-2 bg-ink px-4 py-2 text-center text-xs text-paper">
                <Eye class="size-3.5" /> Vista previa del administrador: la página aún no está activada para ella.
            </div>

            <!-- Portada -->
            <header class="relative mx-auto flex min-h-[92dvh] max-w-5xl flex-col items-center justify-center px-6 pb-16 pt-14 text-center">
                <p class="eyebrow mb-8 animate-rise">Un pedacito de todos</p>
                <div
                    v-if="event.cover"
                    class="mb-10 w-56 rotate-[-2.5deg] bg-paper p-3 pb-12 shadow-[var(--shadow-lift)] animate-rise sm:w-72"
                    :style="coverStyle"
                >
                    <img :src="event.cover.url" :alt="name" class="aspect-[4/5] w-full object-cover" />
                    <p class="mt-3 font-hand text-2xl text-ink-soft">{{ name }}</p>
                </div>
                <h1 class="text-[3.4rem] leading-[0.95] sm:text-8xl animate-rise" style="animation-delay: 0.3s">
                    Feliz cumpleaños,<br /><span class="italic text-rose-deep">{{ name }}</span>
                </h1>
                <p v-if="event.age" class="mt-5 font-serif text-2xl italic text-ink-soft animate-rise" style="animation-delay: 0.4s">{{ event.age }} años</p>
                <Ornament class="my-8 animate-rise" style="animation-delay: 0.5s" />
                <p class="max-w-xl font-serif text-[1.45rem] leading-relaxed text-ink-soft animate-rise sm:text-2xl" style="animation-delay: 0.6s">
                    {{ event.cover_message }}
                </p>
                <div class="mt-12 flex items-center gap-10 animate-rise" style="animation-delay: 0.75s">
                    <div>
                        <p class="font-serif text-5xl tabular-nums">{{ data.stats?.people ?? 0 }}</p>
                        <p class="eyebrow mt-1">personas</p>
                    </div>
                    <div class="h-12 w-px bg-sand" />
                    <div>
                        <p class="font-serif text-5xl tabular-nums">{{ data.stats?.memories ?? 0 }}</p>
                        <p class="eyebrow mt-1">recuerdos</p>
                    </div>
                </div>
                <a href="#recuerdos" class="mt-14 grid size-11 place-items-center rounded-full border border-sand text-ink-soft animate-bounce" aria-label="Ver recuerdos">
                    <ChevronDown class="size-5" :stroke-width="1.5" />
                </a>
            </header>

            <!-- Navegación por tipo -->
            <nav id="recuerdos" class="sticky top-0 z-20 border-y border-sand/60 bg-cream/85 backdrop-blur-md">
                <div class="scrollbar-none mx-auto flex max-w-5xl gap-1 overflow-x-auto px-4 py-2.5">
                    <button
                        v-for="t in tabs"
                        :key="t.id"
                        class="shrink-0 rounded-full px-4 py-2 text-sm transition"
                        :class="tab === t.id ? 'bg-ink text-paper' : 'text-ink-soft hover:bg-paper'"
                        @click="tab = t.id"
                    >
                        {{ t.label }} <span class="ml-1 text-xs opacity-60">{{ t.n }}</span>
                    </button>
                </div>
            </nav>

            <section class="mx-auto max-w-5xl px-5 py-14 sm:px-8">
                <p v-if="!people.length" class="py-20 text-center font-serif text-2xl italic text-ink-soft">Pronto habrá aquí muchos recuerdos para ti.</p>

                <Transition name="slide" mode="out-in">
                    <!-- Personas -->
                    <div v-if="tab === 'personas'" key="personas">
                        <div v-if="favorites.length" class="mb-16">
                            <h2 class="mb-2 text-center text-4xl">Para empezar</h2>
                            <p class="mb-8 text-center text-ink-soft">Algunos recuerdos que queríamos que vieras primero.</p>
                            <div class="grid gap-8 sm:grid-cols-2">
                                <div v-for="f in favorites.slice(0, 4)" :key="f.c.id">
                                    <ContributionCard :item="f.c" :from="f.p.name" compact @letter="letter = f" @photo="(i) => showPhotoOf(f.c, f.p, i)" />
                                    <p class="mt-2 text-right font-hand text-xl text-ink-soft">— {{ f.p.name }}</p>
                                </div>
                            </div>
                        </div>
                        <h2 class="mb-2 text-center text-4xl">Quienes te dejaron algo</h2>
                        <p class="mb-10 text-center text-ink-soft">Toca a cada persona para ver todo lo que te dejó.</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                            <button
                                v-for="(p, i) in people"
                                :key="p.id"
                                class="group text-center"
                                :style="{ transform: `rotate(${tilt(i) * 0.6}deg)` }"
                                @click="person = p"
                            >
                                <div class="bg-paper p-2 pb-4 shadow-[var(--shadow-paper)] transition duration-500 group-hover:-translate-y-1 group-hover:shadow-[var(--shadow-lift)]">
                                    <div class="aspect-square overflow-hidden bg-linen">
                                        <img v-if="p.photo" :src="p.photo.thumb" alt="" loading="lazy" class="size-full object-cover" />
                                        <div v-else class="grid size-full place-items-center bg-gradient-to-br from-rose-soft/70 to-linen">
                                            <span class="font-serif text-5xl italic text-rose-deep/80">{{ initials(p.name) }}</span>
                                        </div>
                                    </div>
                                    <p class="mt-3 truncate px-1 font-hand text-[1.55rem] leading-none">{{ p.name }}</p>
                                    <p class="mt-1.5 text-[0.65rem] uppercase tracking-[0.18em] text-muted">{{ p.relationship_label }}</p>
                                    <p class="mt-1 text-xs text-rose-deep">{{ p.count }} {{ p.count === 1 ? 'recuerdo' : 'recuerdos' }}</p>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Cartas -->
                    <div v-else-if="tab === 'cartas'" key="cartas">
                        <h2 class="mb-2 text-center text-4xl">Cartas para ti</h2>
                        <p class="mb-10 text-center text-ink-soft">Ábrelas cuando quieras, en el orden que quieras.</p>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-10 min-[420px]:grid-cols-2 lg:grid-cols-3">
                            <LetterEnvelope
                                v-for="(e, i) in letters"
                                :key="e.c.id"
                                :from="e.p.name"
                                :relation="e.p.relationship_label"
                                :title="e.c.title"
                                :tilt="tilt(i)"
                                @open="letter = e"
                            />
                        </div>
                    </div>

                    <!-- Fotografías -->
                    <div v-else-if="tab === 'fotos'" key="fotos">
                        <h2 class="mb-2 text-center text-4xl">Momentos juntos</h2>
                        <p class="mb-10 text-center text-ink-soft">Cada foto guarda una historia.</p>
                        <div class="columns-2 gap-3 sm:columns-3 sm:gap-4">
                            <button
                                v-for="(it, i) in photoItems"
                                :key="it.media.id"
                                class="group mb-3 block w-full break-inside-avoid bg-paper p-1.5 pb-3 text-left shadow-[var(--shadow-paper)] transition hover:shadow-[var(--shadow-lift)] sm:mb-4"
                                @click="showPhotos(photoItems, i)"
                            >
                                <img :src="it.media.thumb" :alt="it.title || 'Fotografía'" loading="lazy" class="w-full bg-linen" :style="it.media.width && it.media.height ? { aspectRatio: `${it.media.width}/${it.media.height}` } : {}" />
                                <p v-if="it.title" class="mt-2 truncate px-1 font-serif text-base italic">{{ it.title }}</p>
                                <p class="px-1 font-hand text-lg leading-tight text-ink-soft">{{ it.author }}</p>
                            </button>
                        </div>
                    </div>

                    <!-- Videos -->
                    <div v-else-if="tab === 'videos'" key="videos">
                        <h2 class="mb-2 text-center text-4xl">Videos para ti</h2>
                        <p class="mb-10 text-center text-ink-soft">Sube el volumen.</p>
                        <div class="mx-auto max-w-3xl space-y-14">
                            <div v-for="e in videos" :key="e.c.id">
                                <p class="mb-3 font-hand text-3xl">{{ e.p.name }} <span class="ml-1 align-middle font-sans text-[0.65rem] uppercase tracking-[0.18em] text-muted">{{ e.p.relationship_label }}</span></p>
                                <video
                                    v-for="m in e.c.media"
                                    :key="m.id"
                                    :src="m.url + '#t=0.1'"
                                    controls
                                    playsinline
                                    preload="metadata"
                                    class="mb-3 w-full max-h-[80vh] rounded-2xl bg-black shadow-[var(--shadow-lift)]"
                                />
                                <p v-if="e.c.body" class="whitespace-pre-line font-serif text-xl italic leading-relaxed text-ink-soft">{{ e.c.body }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Palabras -->
                    <div v-else-if="tab === 'palabras'" key="palabras">
                        <h2 class="mb-2 text-center text-4xl">Unas palabras para ti</h2>
                        <p class="mb-10 text-center text-ink-soft">Pequeñas, pero dichas con todo el corazón.</p>
                        <div class="columns-1 gap-5 sm:columns-2 lg:columns-3">
                            <figure
                                v-for="(e, i) in messages"
                                :key="e.c.id"
                                class="paper relative mb-5 break-inside-avoid rounded-2xl px-6 pb-6 pt-8"
                                :style="{ transform: `rotate(${tilt(i) * 0.5}deg)` }"
                            >
                                <span class="absolute left-4 top-0 font-serif text-6xl leading-none text-rose/30" aria-hidden="true">“</span>
                                <blockquote class="relative whitespace-pre-line font-serif text-[1.4rem] leading-snug">{{ e.c.body }}</blockquote>
                                <figcaption class="mt-4 text-right">
                                    <span class="font-hand text-2xl text-rose-deep">{{ e.c.signature || e.p.name }}</span>
                                </figcaption>
                            </figure>
                        </div>
                    </div>

                    <!-- Canciones -->
                    <div v-else-if="tab === 'canciones'" key="canciones">
                        <h2 class="mb-2 text-center text-4xl">Canciones que nos recuerdan a ti</h2>
                        <p class="mb-10 text-center text-ink-soft">Cada una se abre en su aplicación. Ponte audífonos.</p>
                        <div class="mx-auto grid max-w-3xl gap-8 sm:grid-cols-2">
                            <div v-for="e in songs" :key="e.c.id">
                                <ContributionCard :item="e.c" :from="e.p.name" />
                                <p class="mt-2 text-right font-hand text-2xl text-ink-soft">— {{ e.p.name }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Recuerdos especiales -->
                    <div v-else-if="tab === 'especiales'" key="especiales">
                        <h2 class="mb-2 text-center text-4xl">Recuerdos especiales</h2>
                        <p class="mb-10 text-center text-ink-soft">Cosas que nunca te habían dicho, y lo que significas para ellos.</p>
                        <div class="mx-auto max-w-2xl space-y-14">
                            <div v-for="e in specials" :key="e.c.id">
                                <ContributionCard :item="e.c" :from="e.p.name" />
                                <p class="mt-3 text-right font-hand text-2xl text-ink-soft">— {{ e.p.name }}</p>
                            </div>
                        </div>
                    </div>
                </Transition>
            </section>

            <footer class="mx-auto max-w-xl px-6 pb-20 pt-10 text-center">
                <Ornament class="mx-auto mb-6" />
                <p class="font-serif text-2xl italic leading-relaxed text-ink-soft">
                    Hay personas que hacen nuestra vida más bonita. Tú eres una de ellas.
                </p>
                <p class="mt-4 text-sm text-muted">Esta página es tuya. Vuelve cuando quieras.</p>
                <button class="btn btn-ghost btn-sm mt-8" @click="share"><Share2 class="size-4" /> {{ shared ? 'Enlace copiado' : 'Compartir esta página' }}</button>
            </footer>

            <!-- Música ambiental: desactivada por defecto, nunca se reproduce sola -->
            <template v-if="event.music">
                <audio ref="audio" :src="event.music.url" loop preload="none" @pause="playing = false" @play="playing = true" />
                <button
                    class="fixed bottom-5 right-5 z-30 flex items-center gap-2 rounded-full bg-paper/90 px-4 py-3 text-sm text-ink-soft shadow-[var(--shadow-lift)] backdrop-blur"
                    style="bottom: max(1.25rem, env(safe-area-inset-bottom))"
                    :aria-label="playing ? 'Pausar música' : 'Reproducir música'"
                    @click="toggleMusic"
                >
                    <Pause v-if="playing" class="size-4" />
                    <Music2 v-else class="size-4" />
                    <span class="hidden sm:inline">{{ playing ? 'Pausar' : 'Música' }}</span>
                </button>
            </template>

            <!-- Persona -->
            <Sheet :open="!!person" wide :label="person?.name" @close="person = null">
                <template v-if="person">
                    <div class="mb-10 text-center">
                        <div class="mx-auto mb-5 size-24 overflow-hidden rounded-full bg-linen shadow-[var(--shadow-paper)]">
                            <img v-if="person.photo" :src="person.photo.thumb" alt="" class="size-full object-cover" />
                            <div v-else class="grid size-full place-items-center bg-gradient-to-br from-rose-soft/70 to-linen">
                                <span class="font-serif text-3xl italic text-rose-deep/80">{{ initials(person.name) }}</span>
                            </div>
                        </div>
                        <h2 class="font-hand text-5xl">{{ person.name }}</h2>
                        <p class="eyebrow mt-2">{{ person.relationship_label }}</p>
                        <p class="mt-2 text-sm text-rose-deep">{{ person.count }} {{ person.count === 1 ? 'recuerdo' : 'recuerdos' }} para ti</p>
                    </div>
                    <div class="space-y-12">
                        <div v-for="c in person.contributions" :key="c.id" class="relative">
                            <Star v-if="c.is_favorite" class="absolute -left-1 -top-1 size-4 fill-gold text-gold" />
                            <ContributionCard :item="c" :from="person.name" compact @letter="letter = { c, p: person! }" @photo="(i) => showPhotoOf(c, person!, i)" />
                        </div>
                    </div>
                </template>
            </Sheet>

            <!-- Carta abierta -->
            <Sheet :open="!!letter" wide :label="letter ? 'Carta de ' + letter.p.name : ''" @close="letter = null">
                <LetterPaper
                    v-if="letter"
                    :key="letter.c.id"
                    animate
                    :title="letter.c.title"
                    :body="letter.c.body"
                    :signature="letter.c.signature"
                    :from="letter.p.name"
                    :date="letter.c.created_at"
                />
            </Sheet>

            <Lightbox :items="lightboxItems" :index="lightboxIndex" @close="lightboxIndex = null" @update:index="lightboxIndex = $event" />
        </template>
    </div>
</template>
