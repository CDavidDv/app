<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { ChevronLeft, ChevronRight, X } from 'lucide-vue-next';
import type { Media } from '@/types';

export interface LightboxItem {
    media: Media;
    title?: string | null;
    caption?: string | null;
    author?: string | null;
}

const props = defineProps<{ items: LightboxItem[]; index: number | null }>();
const emit = defineEmits<{ close: []; 'update:index': [number] }>();

const current = computed(() => (props.index === null ? null : props.items[props.index]));
const loaded = ref(false);

function go(step: number) {
    if (props.index === null || !props.items.length) return;
    loaded.value = false;
    emit('update:index', (props.index + step + props.items.length) % props.items.length);
}

function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('close');
    if (e.key === 'ArrowRight') go(1);
    if (e.key === 'ArrowLeft') go(-1);
}

let startX = 0;
function touchStart(e: TouchEvent) {
    startX = e.touches[0].clientX;
}
function touchEnd(e: TouchEvent) {
    const dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1);
}

watch(
    () => props.index,
    (i) => {
        document.documentElement.style.overflow = i !== null ? 'hidden' : '';
        if (i !== null) window.addEventListener('keydown', onKey);
        else window.removeEventListener('keydown', onKey);
    },
    { immediate: true },
);
onBeforeUnmount(() => {
    document.documentElement.style.overflow = '';
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="current"
                class="fixed inset-0 z-[60] flex flex-col bg-[#1d1714]/95 text-paper"
                role="dialog"
                aria-modal="true"
                @touchstart.passive="touchStart"
                @touchend.passive="touchEnd"
            >
                <div class="flex items-center justify-between px-4 py-3" style="padding-top: max(0.75rem, env(safe-area-inset-top))">
                    <span class="text-xs tracking-[0.2em] text-paper/60">{{ (index ?? 0) + 1 }} / {{ items.length }}</span>
                    <button class="grid size-11 place-items-center rounded-full hover:bg-white/10" aria-label="Cerrar" @click="emit('close')">
                        <X class="size-6" :stroke-width="1.4" />
                    </button>
                </div>
                <div class="relative flex flex-1 items-center justify-center overflow-hidden px-2" @click.self="emit('close')">
                    <img
                        :key="current.media.id"
                        :src="current.media.url"
                        :alt="current.title || 'Fotografía'"
                        class="max-h-full max-w-full object-contain transition-opacity duration-500 select-none"
                        :class="loaded ? 'opacity-100' : 'opacity-0'"
                        @load="loaded = true"
                    />
                    <div v-if="!loaded" class="absolute inset-0 grid place-items-center">
                        <img :src="current.media.thumb" alt="" class="max-h-full max-w-full object-contain opacity-60 blur-sm" />
                    </div>
                    <template v-if="items.length > 1">
                        <button class="absolute left-2 top-1/2 hidden -translate-y-1/2 sm:grid size-12 place-items-center rounded-full bg-black/30 hover:bg-black/50" aria-label="Anterior" @click="go(-1)">
                            <ChevronLeft class="size-6" />
                        </button>
                        <button class="absolute right-2 top-1/2 hidden -translate-y-1/2 sm:grid size-12 place-items-center rounded-full bg-black/30 hover:bg-black/50" aria-label="Siguiente" @click="go(1)">
                            <ChevronRight class="size-6" />
                        </button>
                    </template>
                </div>
                <div
                    v-if="current.title || current.caption || current.author"
                    class="max-h-[34dvh] overflow-y-auto px-6 pt-4 pb-6 text-center"
                    style="padding-bottom: max(1.5rem, env(safe-area-inset-bottom))"
                >
                    <p v-if="current.title" class="font-serif text-2xl">{{ current.title }}</p>
                    <p v-if="current.caption" class="mx-auto mt-2 max-w-2xl whitespace-pre-line text-[0.95rem] leading-relaxed text-paper/80">{{ current.caption }}</p>
                    <p v-if="current.author" class="mt-3 font-hand text-2xl text-rose-soft">{{ current.author }}</p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
