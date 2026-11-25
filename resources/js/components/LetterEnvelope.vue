<script setup lang="ts">
defineProps<{ from: string; relation?: string; title?: string | null; tilt?: number }>();
defineEmits<{ open: [] }>();
</script>

<template>
    <button
        type="button"
        class="group relative block w-full text-left [perspective:900px]"
        :style="{ transform: `rotate(${tilt ?? 0}deg)` }"
        :aria-label="`Abrir la carta de ${from}`"
        @click="$emit('open')"
    >
        <div
            class="relative aspect-[1.55] overflow-hidden rounded-[6px] bg-[#f3e9da] shadow-[var(--shadow-paper)] transition duration-500 group-hover:-translate-y-1 group-hover:shadow-[var(--shadow-lift)]"
        >
            <!-- Textura y pliegues del sobre -->
            <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(255,255,255,0.35),transparent_45%)]" />
            <svg class="absolute inset-0 size-full" viewBox="0 0 155 100" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0 100 L77.5 52 L155 100" fill="none" stroke="#d9c8b1" stroke-width="0.6" />
                <path d="M0 0 L0 100 L60 58Z" fill="#efe3d1" />
                <path d="M155 0 L155 100 L95 58Z" fill="#efe3d1" />
            </svg>
            <!-- Solapa -->
            <div class="absolute inset-x-0 top-0 h-[58%] origin-top transition-transform duration-700 [transform-style:preserve-3d] group-hover:[transform:rotateX(18deg)]">
                <svg class="size-full drop-shadow-[0_2px_2px_rgba(58,47,41,0.08)]" viewBox="0 0 155 58" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M0 0 H155 L77.5 58Z" fill="#ead9c4" />
                </svg>
            </div>
            <!-- Sello de lacre -->
            <div class="absolute left-1/2 top-[58%] grid size-11 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-rose shadow-[inset_0_-2px_4px_rgba(0,0,0,0.18),0_2px_6px_rgba(154,100,95,0.4)]">
                <span class="font-serif text-lg italic text-white/95">{{ from.trim().charAt(0).toUpperCase() }}</span>
            </div>
            <div class="absolute inset-x-0 bottom-0 px-4 pb-3 text-center">
                <p class="font-hand text-[1.45rem] leading-none text-ink">{{ from }}</p>
                <p v-if="relation" class="mt-1 text-[0.65rem] uppercase tracking-[0.2em] text-muted">{{ relation }}</p>
            </div>
        </div>
        <p v-if="title" class="mt-3 truncate px-1 text-center font-serif text-base italic text-ink-soft">{{ title }}</p>
    </button>
</template>
