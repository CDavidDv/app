<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps<{ open: boolean; wide?: boolean; label?: string }>();
const emit = defineEmits<{ close: [] }>();

function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('close');
}

watch(
    () => props.open,
    (o) => {
        document.documentElement.style.overflow = o ? 'hidden' : '';
        if (o) window.addEventListener('keydown', onKey);
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
            <div v-if="open" class="fixed inset-0 z-40 bg-ink/40 backdrop-blur-[2px]" @click="emit('close')" />
        </Transition>
        <Transition name="sheet">
            <div
                v-if="open"
                class="fixed inset-x-0 bottom-0 z-50 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-6 pointer-events-none"
                role="dialog"
                aria-modal="true"
                :aria-label="label"
            >
                <div
                    class="pointer-events-auto relative max-h-[92dvh] w-full overflow-y-auto overscroll-contain rounded-t-[28px] bg-cream shadow-[var(--shadow-lift)] sm:rounded-[28px]"
                    :class="wide ? 'sm:max-w-3xl' : 'sm:max-w-xl'"
                >
                    <div class="sticky top-0 z-10 flex justify-end bg-gradient-to-b from-cream via-cream/90 to-transparent px-4 pt-3 pb-2">
                        <div class="absolute left-1/2 top-2.5 h-1 w-10 -translate-x-1/2 rounded-full bg-sand sm:hidden" />
                        <button class="grid size-10 place-items-center rounded-full bg-paper/80 text-ink-soft hover:text-ink" aria-label="Cerrar" @click="emit('close')">
                            <X class="size-5" :stroke-width="1.5" />
                        </button>
                    </div>
                    <div class="px-5 pb-10 sm:px-10 pt-1" style="padding-bottom: max(2.5rem, env(safe-area-inset-bottom))">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
