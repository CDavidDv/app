<script setup lang="ts">
import { LinkIcon } from 'lucide-vue-next';
import Ornament from '@/components/Ornament.vue';
</script>

<template>
    <main class="min-h-dvh flex items-center justify-center px-6 py-16">
        <div class="max-w-md text-center animate-rise">
            <div class="mx-auto mb-8 grid size-14 place-items-center rounded-full bg-paper shadow-[var(--shadow-paper)]">
                <LinkIcon class="size-5 text-rose" :stroke-width="1.5" />
            </div>
            <h1 class="text-4xl leading-tight mb-6">Este enlace ya no funciona</h1>
            <Ornament class="mx-auto mb-6" />
            <p class="text-ink-soft leading-relaxed">
                Puede que haya cambiado o que esté incompleto. Pídele a quien organiza la sorpresa que te comparta el enlace de nuevo.
                Asegúrate de abrirlo completo, tal como te llegó.
            </p>
        </div>
    </main>
</template>
