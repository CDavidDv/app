<script setup lang="ts">
import { computed } from 'vue';
import { Mail, MessageCircle, Camera, Video, Feather, HelpCircle, Music, Disc3, Play, ExternalLink } from 'lucide-vue-next';
import { platformOf, safeUrl, typeLabels } from '@/store';
import type { Contribution, DraftItem, Question } from '@/types';
import LetterPaper from './LetterPaper.vue';

/**
 * Muestra una aportación de forma cuidada. Acepta una aportación guardada o un borrador.
 */
const props = defineProps<{
    item: Contribution | DraftItem;
    from?: string;
    questions?: Question[];
    compact?: boolean;
}>();
const emit = defineEmits<{ photo: [number]; letter: [] }>();

const icons = { letter: Mail, message: MessageCircle, photo: Camera, video: Video, secret: Feather, questions: HelpCircle, song: Music };

const safeLink = computed(() => safeUrl(props.item.link));
const platform = computed(() => ('platform' in props.item && props.item.platform) || platformOf(props.item.link));

const answers = computed(() => {
    const it = props.item as Contribution & DraftItem;
    if (Array.isArray(it.answers)) return it.answers;
    return (it.chosenQuestions ?? [])
        .map((id) => ({ question: props.questions?.find((q) => q.id === id)?.text ?? '', answer: (it.answers ?? {})[id] ?? '' }))
        .filter((a) => a.answer.trim());
});
const date = computed(() => ('created_at' in props.item ? props.item.created_at : undefined));
</script>

<template>
    <div>
        <div class="mb-3 flex items-center gap-2 text-muted">
            <component :is="icons[item.type]" class="size-4" :stroke-width="1.5" />
            <span class="eyebrow">{{ typeLabels[item.type].title }}</span>
        </div>

        <!-- Carta -->
        <template v-if="item.type === 'letter'">
            <button v-if="compact" type="button" class="paper w-full rounded-2xl p-5 text-left transition hover:shadow-[var(--shadow-lift)]" @click="emit('letter')">
                <p v-if="item.title" class="font-serif text-2xl">{{ item.title }}</p>
                <p class="mt-2 line-clamp-4 whitespace-pre-line font-serif text-lg leading-relaxed text-ink-soft">{{ item.body }}</p>
                <p class="mt-3 text-sm text-rose-deep">Leer la carta completa</p>
            </button>
            <LetterPaper v-else :title="item.title" :body="item.body" :signature="item.signature" :from="from" :date="date" />
        </template>

        <!-- Fotografías -->
        <template v-else-if="item.type === 'photo'">
            <div class="paper rounded-2xl p-3 sm:p-4">
                <div class="grid gap-2" :class="item.media.length === 1 ? 'grid-cols-1' : 'grid-cols-2'">
                    <button
                        v-for="(m, i) in item.media"
                        :key="m.id"
                        type="button"
                        class="overflow-hidden rounded-xl bg-linen"
                        :class="item.media.length === 1 ? '' : 'aspect-square'"
                        @click="emit('photo', i)"
                    >
                        <img :src="m.thumb" :alt="item.title || 'Fotografía'" loading="lazy" class="size-full object-cover transition duration-700 hover:scale-[1.03]" :class="item.media.length === 1 ? 'max-h-[70vh] w-full object-contain' : ''" />
                    </button>
                </div>
                <div class="px-2 pt-4 pb-2">
                    <p v-if="item.title" class="font-serif text-2xl leading-snug">{{ item.title }}</p>
                    <p v-if="item.body" class="mt-2 whitespace-pre-line leading-relaxed text-ink-soft">{{ item.body }}</p>
                </div>
            </div>
        </template>

        <!-- Videos -->
        <template v-else-if="item.type === 'video'">
            <div class="space-y-3">
                <video
                    v-for="m in item.media"
                    :key="m.id"
                    :src="m.url + '#t=0.1'"
                    controls
                    playsinline
                    preload="metadata"
                    class="w-full max-h-[75vh] rounded-2xl bg-black shadow-[var(--shadow-paper)]"
                />
                <p v-if="item.body" class="whitespace-pre-line px-1 font-serif text-lg italic leading-relaxed text-ink-soft">{{ item.body }}</p>
            </div>
        </template>

        <!-- Mensaje corto -->
        <template v-else-if="item.type === 'message'">
            <div class="paper relative rounded-2xl px-6 py-7">
                <span class="absolute left-4 top-1 font-serif text-6xl leading-none text-rose/30" aria-hidden="true">“</span>
                <p class="relative whitespace-pre-line font-serif text-2xl leading-snug">{{ item.body }}</p>
                <p v-if="item.signature" class="mt-4 text-right font-hand text-2xl text-rose-deep">{{ item.signature }}</p>
            </div>
        </template>

        <!-- Algo que nunca te he dicho -->
        <template v-else-if="item.type === 'secret'">
            <div class="relative rounded-2xl bg-[#3a2f29] px-6 py-8 text-paper shadow-[var(--shadow-lift)]">
                <p v-if="item.prompt" class="mb-3 font-serif text-lg italic text-rose-soft">{{ item.prompt }}</p>
                <p class="whitespace-pre-line font-serif text-[1.35rem] leading-relaxed">{{ item.body }}</p>
            </div>
        </template>

        <!-- Canción -->
        <template v-else-if="item.type === 'song'">
            <div class="paper flex flex-col gap-4 rounded-2xl px-6 py-6">
                <div class="flex items-start gap-4">
                    <span class="grid size-14 shrink-0 place-items-center rounded-full bg-ink text-paper shadow-[var(--shadow-paper)]" aria-hidden="true">
                        <Disc3 class="size-7 motion-safe:animate-[spin_8s_linear_infinite]" :stroke-width="1.25" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-serif text-2xl leading-snug break-words">{{ item.title }}</p>
                        <p v-if="item.artist" class="text-ink-soft break-words">{{ item.artist }}</p>
                    </div>
                </div>
                <p v-if="item.body" class="whitespace-pre-line font-serif text-lg italic leading-relaxed text-ink-soft">{{ item.body }}</p>
                <a
                    v-if="safeLink"
                    :href="safeLink"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-ghost btn-sm self-start"
                >
                    <Play class="size-4" /> Escuchar{{ platform ? ' en ' + platform : '' }}
                    <ExternalLink class="size-3.5 opacity-60" />
                </a>
            </div>
        </template>

        <!-- Preguntas -->
        <template v-else-if="item.type === 'questions'">
            <div class="space-y-4">
                <div v-for="(a, i) in answers" :key="i" class="paper rounded-2xl px-5 py-5">
                    <p class="font-serif text-lg italic text-rose-deep">{{ a.question }}</p>
                    <p class="mt-2 whitespace-pre-line leading-relaxed text-ink">{{ a.answer }}</p>
                </div>
            </div>
        </template>
    </div>
</template>
