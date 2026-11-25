<script setup lang="ts">
import { formatDate } from '@/store';
import Ornament from './Ornament.vue';

defineProps<{
    title?: string | null;
    body: string | null;
    signature?: string | null;
    from?: string;
    date?: string;
    prompt?: string | null;
    animate?: boolean;
}>();
</script>

<template>
    <article class="letter-unfold relative mx-auto max-w-2xl" :class="{ 'is-animated': animate }">
        <div class="paper-lined relative rounded-[4px] px-6 py-10 shadow-[var(--shadow-lift)] sm:px-14 sm:py-14">
            <!-- margen rosado de cuaderno -->
            <div class="pointer-events-none absolute inset-y-0 left-4 w-px bg-rose/25 sm:left-9" />
            <p v-if="date" class="mb-6 text-right text-xs tracking-[0.15em] text-muted uppercase">{{ formatDate(date, { day: 'numeric', month: 'long', year: 'numeric' }) }}</p>
            <h3 v-if="title" class="mb-6 text-3xl leading-tight sm:text-4xl">{{ title }}</h3>
            <p v-if="prompt" class="mb-4 font-serif text-xl italic text-rose-deep">{{ prompt }}</p>
            <div class="letter-text">{{ body }}</div>
            <div v-if="signature || from" class="mt-10 text-right">
                <Ornament :size="56" class="mb-2 ml-auto" />
                <p class="font-hand text-[2rem] leading-tight text-ink">{{ signature || from }}</p>
            </div>
        </div>
    </article>
</template>

<style scoped>
.letter-unfold.is-animated {
    animation: unfold 1.1s cubic-bezier(0.2, 0.75, 0.2, 1) both;
    transform-origin: top center;
}
.letter-unfold.is-animated .letter-text,
.letter-unfold.is-animated h3 {
    animation: ink 1.4s 0.5s ease both;
}
@keyframes unfold {
    0% { opacity: 0; transform: perspective(1200px) rotateX(-60deg) translateY(40px) scale(0.92); }
    60% { opacity: 1; }
    100% { opacity: 1; transform: none; }
}
@keyframes ink {
    from { opacity: 0; filter: blur(2px); }
    to { opacity: 1; filter: none; }
}
</style>
