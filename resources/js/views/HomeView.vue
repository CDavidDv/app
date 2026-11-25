<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Lock } from 'lucide-vue-next';
import Ornament from '@/components/Ornament.vue';
import { loadState } from '@/store';

const router = useRouter();

onMounted(async () => {
    const s = await loadState().catch(() => null);
    if (!s) return;
    if (s.birthday.linked) router.replace('/para-ti');
    else if (s.can_participate) router.replace('/participar');
    else if (s.admin) router.replace('/admin');
});
</script>

<template>
    <main class="min-h-dvh flex items-center justify-center px-6 py-16">
        <div class="max-w-md text-center animate-rise">
            <div class="mx-auto mb-8 grid size-14 place-items-center rounded-full bg-paper shadow-[var(--shadow-paper)]">
                <Lock class="size-5 text-rose" :stroke-width="1.5" />
            </div>
            <p class="eyebrow mb-4">Un pedacito de todos</p>
            <h1 class="text-4xl sm:text-5xl leading-tight mb-6">Este es un lugar privado</h1>
            <Ornament class="mx-auto mb-6" />
            <p class="text-ink-soft leading-relaxed">
                Aquí se guardan recuerdos muy especiales. Para entrar necesitas el enlace que te compartieron.
                Si te invitaron y no puedes acceder, pídele de nuevo el enlace a quien te lo envió.
            </p>
        </div>
    </main>
</template>
