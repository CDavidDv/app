<script setup lang="ts">
import { computed } from 'vue';
import { Check } from 'lucide-vue-next';
import MediaUploader from './MediaUploader.vue';
import { platformOf, secretPrompts, store } from '@/store';
import type { DraftItem, Question } from '@/types';

const props = defineProps<{ modelValue: DraftItem; questions: Question[]; errors?: Record<string, string>; celebrant: string }>();
const emit = defineEmits<{ 'update:modelValue': [DraftItem]; busy: [boolean] }>();

const limits = computed(() => store.state!.limits);
const item = computed(() => props.modelValue);

function set<K extends keyof DraftItem>(key: K, value: DraftItem[K]) {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}

function toggleQuestion(id: number) {
    const chosen = item.value.chosenQuestions.includes(id)
        ? item.value.chosenQuestions.filter((q) => q !== id)
        : [...item.value.chosenQuestions, id];
    set('chosenQuestions', chosen);
}

function setAnswer(id: number, text: string) {
    set('answers', { ...item.value.answers, [id]: text });
}

const err = (k: string) => props.errors?.[k];
</script>

<template>
    <div class="space-y-6">
        <!-- Carta -->
        <template v-if="item.type === 'letter'">
            <div>
                <label class="field-label" for="f-title">Título <span class="text-muted font-normal">(opcional)</span></label>
                <input id="f-title" class="field font-serif text-lg" :value="item.title" maxlength="200" placeholder="Para ti, en tu día" @input="set('title', ($event.target as HTMLInputElement).value)" />
            </div>
            <div>
                <label class="field-label" for="f-body">Tu carta</label>
                <div class="paper rounded-3xl p-1.5">
                    <textarea
                        id="f-body"
                        class="paper-lined block min-h-[22rem] w-full rounded-[20px] border-0 px-5 py-4 font-serif text-[1.2rem] leading-8 text-ink focus:outline-none"
                        :value="item.body"
                        :maxlength="limits.letter_chars"
                        :placeholder="`Querida ${celebrant}…`"
                        @input="set('body', ($event.target as HTMLTextAreaElement).value)"
                    />
                </div>
                <div class="mt-1.5 flex justify-between text-xs text-muted">
                    <span>Tómate tu tiempo. Lo que escribes se guarda en este dispositivo mientras tanto.</span>
                </div>
                <p v-if="err('body')" class="field-error">{{ err('body') }}</p>
            </div>
            <div>
                <label class="field-label" for="f-sign">Firma</label>
                <input id="f-sign" class="field font-hand text-2xl" :value="item.signature" maxlength="120" placeholder="Con cariño, …" @input="set('signature', ($event.target as HTMLInputElement).value)" />
            </div>
        </template>

        <!-- Video -->
        <template v-else-if="item.type === 'video'">
            <MediaUploader
                kind="video"
                :model-value="item.media"
                :max="limits.videos_per_contribution"
                :max-mb="limits.video_mb"
                @update:model-value="set('media', $event)"
                @busy="emit('busy', $event)"
            />
            <p v-if="err('media_ids')" class="field-error">{{ err('media_ids') }}</p>
            <div>
                <label class="field-label" for="f-desc">Unas palabras sobre tu video <span class="text-muted font-normal">(opcional)</span></label>
                <textarea id="f-desc" class="field min-h-28" :value="item.body" :maxlength="limits.text_chars" placeholder="Lo grabé pensando en…" @input="set('body', ($event.target as HTMLTextAreaElement).value)" />
            </div>
        </template>

        <!-- Fotografía -->
        <template v-else-if="item.type === 'photo'">
            <MediaUploader
                kind="image"
                :model-value="item.media"
                :max="limits.photos_per_memory"
                :max-mb="limits.image_mb"
                @update:model-value="set('media', $event)"
                @busy="emit('busy', $event)"
            />
            <p v-if="err('media_ids')" class="field-error">{{ err('media_ids') }}</p>
            <div>
                <label class="field-label" for="f-ptitle">¿Cómo se llama este recuerdo?</label>
                <input id="f-ptitle" class="field font-serif text-lg" :value="item.title" maxlength="200" placeholder="Aquella tarde en…" @input="set('title', ($event.target as HTMLInputElement).value)" />
            </div>
            <div>
                <label class="field-label" for="f-story">La historia detrás de la foto</label>
                <textarea id="f-story" class="field min-h-36" :value="item.body" :maxlength="limits.text_chars" placeholder="Ese día…" @input="set('body', ($event.target as HTMLTextAreaElement).value)" />
            </div>
        </template>

        <!-- Mensaje corto -->
        <template v-else-if="item.type === 'message'">
            <div>
                <label class="field-label" for="f-msg">Tus palabras</label>
                <textarea
                    id="f-msg"
                    class="field min-h-36 font-serif text-xl leading-relaxed"
                    :value="item.body"
                    :maxlength="limits.message_chars"
                    placeholder="Feliz cumpleaños…"
                    @input="set('body', ($event.target as HTMLTextAreaElement).value)"
                />
                <div class="mt-1.5 text-right text-xs text-muted">{{ item.body.length }} / {{ limits.message_chars }}</div>
                <p v-if="err('body')" class="field-error">{{ err('body') }}</p>
            </div>
            <div>
                <label class="field-label" for="f-msign">Firma <span class="text-muted font-normal">(opcional)</span></label>
                <input id="f-msign" class="field font-hand text-2xl" :value="item.signature" maxlength="120" @input="set('signature', ($event.target as HTMLInputElement).value)" />
            </div>
        </template>

        <!-- Algo que nunca te he dicho -->
        <template v-else-if="item.type === 'secret'">
            <div>
                <p class="field-label">Elige cómo empezar <span class="text-muted font-normal">(opcional)</span></p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="p in secretPrompts"
                        :key="p"
                        type="button"
                        class="chip"
                        :aria-pressed="item.prompt === p"
                        @click="set('prompt', item.prompt === p ? '' : p)"
                    >
                        {{ p }}
                    </button>
                </div>
            </div>
            <div>
                <label class="field-label" for="f-secret">{{ item.prompt || '¿Qué es eso que nunca le has dicho?' }}</label>
                <textarea id="f-secret" class="field min-h-44 font-serif text-xl leading-relaxed" :value="item.body" :maxlength="limits.text_chars" @input="set('body', ($event.target as HTMLTextAreaElement).value)" />
                <p v-if="err('body')" class="field-error">{{ err('body') }}</p>
            </div>
        </template>

        <!-- Canción -->
        <template v-else-if="item.type === 'song'">
            <p class="text-sm text-ink-soft">
                Esa canción que escuchas y piensas en {{ celebrant }}. Busca la canción en YouTube, Spotify, Apple Music o la plataforma que uses, toca
                <strong class="font-medium">Compartir</strong> y luego <strong class="font-medium">Copiar enlace</strong>.
            </p>
            <div>
                <label class="field-label" for="f-slink">Enlace de la canción</label>
                <input
                    id="f-slink"
                    class="field"
                    type="url"
                    inputmode="url"
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    :value="item.link"
                    maxlength="500"
                    placeholder="https://open.spotify.com/track/…"
                    @input="set('link', ($event.target as HTMLInputElement).value)"
                />
                <p v-if="item.link.trim() && platformOf(item.link)" class="mt-1.5 text-xs text-muted">{{ platformOf(item.link) }}</p>
                <p v-if="err('link')" class="field-error">{{ err('link') }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label" for="f-stitle">Nombre de la canción</label>
                    <input id="f-stitle" class="field font-serif text-lg" :value="item.title" maxlength="200" @input="set('title', ($event.target as HTMLInputElement).value)" />
                    <p v-if="err('title')" class="field-error">{{ err('title') }}</p>
                </div>
                <div>
                    <label class="field-label" for="f-sartist">Artista <span class="text-muted font-normal">(opcional)</span></label>
                    <input id="f-sartist" class="field" :value="item.artist" maxlength="200" @input="set('artist', ($event.target as HTMLInputElement).value)" />
                </div>
            </div>
            <div>
                <label class="field-label" for="f-swhy">¿Por qué te recuerda a ella? <span class="text-muted font-normal">(opcional)</span></label>
                <textarea id="f-swhy" class="field min-h-28" :value="item.body" :maxlength="limits.text_chars" placeholder="Cada vez que la escucho…" @input="set('body', ($event.target as HTMLTextAreaElement).value)" />
            </div>
        </template>

        <!-- Preguntas -->
        <template v-else-if="item.type === 'questions'">
            <p class="text-sm text-ink-soft">Elige las que quieras responder. Todas son opcionales.</p>
            <div class="space-y-3">
                <div v-for="q in questions" :key="q.id" class="rounded-2xl border border-sand/80 bg-paper/70 transition" :class="item.chosenQuestions.includes(q.id) ? 'shadow-[var(--shadow-paper)]' : ''">
                    <button type="button" class="flex w-full items-start gap-3 p-4 text-left" :aria-pressed="item.chosenQuestions.includes(q.id)" @click="toggleQuestion(q.id)">
                        <span
                            class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full border transition"
                            :class="item.chosenQuestions.includes(q.id) ? 'border-rose bg-rose text-white' : 'border-sand'"
                        >
                            <Check v-if="item.chosenQuestions.includes(q.id)" class="size-3.5" />
                        </span>
                        <span class="font-serif text-lg leading-snug">{{ q.text }}</span>
                    </button>
                    <div v-if="item.chosenQuestions.includes(q.id)" class="px-4 pb-4">
                        <textarea class="field min-h-28" :value="item.answers[q.id] ?? ''" :maxlength="limits.text_chars" placeholder="Tu respuesta…" @input="setAnswer(q.id, ($event.target as HTMLTextAreaElement).value)" />
                    </div>
                </div>
            </div>
            <p v-if="err('answers')" class="field-error">{{ err('answers') }}</p>
        </template>
    </div>
</template>
