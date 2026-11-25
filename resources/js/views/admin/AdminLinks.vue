<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Copy, RefreshCw, Users, Gift } from 'lucide-vue-next';
import { http } from '@/api';
import { formatDate, store } from '@/store';

interface LinkInfo {
    url: string;
    uses: number;
    last_used_at: string | null;
    created_at: string;
}
const links = ref<Record<'invite' | 'birthday', LinkInfo> | null>(null);
const copied = ref<string | null>(null);

onMounted(async () => (links.value = (await http.get('/admin/links')).data));

async function copy(key: string, text: string) {
    await navigator.clipboard.writeText(text).catch(() => prompt('Copia:', text));
    copied.value = key;
    setTimeout(() => (copied.value = null), 2500);
}
async function regenerate(type: 'invite' | 'birthday') {
    const msg =
        type === 'invite'
            ? 'Se creará un nuevo enlace de invitación y el anterior dejará de funcionar para quien no haya entrado todavía. Quienes ya participaron conservan su enlace personal. ¿Continuar?'
            : 'Se creará un nuevo enlace para la cumpleañera y el anterior dejará de funcionar. ¿Continuar?';
    if (!confirm(msg)) return;
    links.value = (await http.post(`/admin/links/${type}/regenerate`)).data;
}
const inviteText = () => {
    const her = store.state?.event?.celebrant_short_name ?? '';
    return `Estamos preparando una sorpresa para el cumpleaños de ${her}: queremos reunir mensajes, cartas, fotos y videos de las personas que la quieren. ¿Le dejas algo? Es secreto, no le digas nada. ${links.value?.invite.url}`;
};
</script>

<template>
    <div v-if="links" class="max-w-3xl">
        <h1 class="text-4xl">Enlaces</h1>
        <p class="mb-8 text-ink-soft">Cada enlace es secreto y abre una parte distinta. Compártelos solo con quien corresponde.</p>

        <section class="paper mb-6 rounded-3xl p-6">
            <div class="mb-3 flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-2xl bg-rose-soft/60 text-rose-deep"><Users class="size-5" :stroke-width="1.5" /></span>
                <div>
                    <h2 class="text-2xl leading-none">Invitación para participantes</h2>
                    <p class="text-xs text-muted">Usado {{ links.invite.uses }} veces<span v-if="links.invite.last_used_at"> · último uso {{ formatDate(links.invite.last_used_at, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}</span></p>
                </div>
            </div>
            <p class="mb-4 text-sm text-ink-soft">Mándalo por WhatsApp a familia y amigos. Cada quien se identifica y recibe su propio enlace para volver a editar.</p>
            <code class="block break-all rounded-xl bg-linen px-4 py-3 text-xs">{{ links.invite.url }}</code>
            <div class="mt-4 flex flex-wrap gap-2">
                <button class="btn btn-sm btn-primary" @click="copy('invite', links.invite.url)"><Copy class="size-3.5" /> {{ copied === 'invite' ? 'Copiado' : 'Copiar enlace' }}</button>
                <button class="btn btn-sm btn-ghost" @click="copy('invite-text', inviteText())"><Copy class="size-3.5" /> {{ copied === 'invite-text' ? 'Copiado' : 'Copiar mensaje para WhatsApp' }}</button>
                <button class="btn btn-sm btn-ghost" @click="regenerate('invite')"><RefreshCw class="size-3.5" /> Regenerar</button>
            </div>
        </section>

        <section class="paper rounded-3xl p-6">
            <div class="mb-3 flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-2xl bg-gold/20 text-gold"><Gift class="size-5" :stroke-width="1.5" /></span>
                <div>
                    <h2 class="text-2xl leading-none">Enlace privado de la cumpleañera</h2>
                    <p class="text-xs text-muted">Usado {{ links.birthday.uses }} veces</p>
                </div>
            </div>
            <p class="mb-4 text-sm text-ink-soft">
                Este es el regalo. Entrégalo el día de su cumpleaños (puedes añadir una palabra secreta en Configuración).
                Recuerda activar “Página de recuerdos visible” antes de dárselo.
            </p>
            <code class="block break-all rounded-xl bg-linen px-4 py-3 text-xs">{{ links.birthday.url }}</code>
            <div class="mt-4 flex flex-wrap gap-2">
                <button class="btn btn-sm btn-primary" @click="copy('birthday', links.birthday.url)"><Copy class="size-3.5" /> {{ copied === 'birthday' ? 'Copiado' : 'Copiar enlace' }}</button>
                <button class="btn btn-sm btn-ghost" @click="regenerate('birthday')"><RefreshCw class="size-3.5" /> Regenerar</button>
            </div>
        </section>
    </div>
</template>
