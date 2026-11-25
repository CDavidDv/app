<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { AlertCircle, ImagePlus, RotateCcw, Video, X } from 'lucide-vue-next';
import { errorMessage, formatBytes, http, uploadFile } from '@/api';
import type { Media } from '@/types';

const props = defineProps<{
    kind: 'image' | 'video';
    modelValue: Media[];
    max: number;
    maxMb: number;
}>();
const emit = defineEmits<{ 'update:modelValue': [Media[]]; busy: [boolean] }>();

interface Pending {
    key: string;
    file: File;
    preview: string;
    progress: number;
    error: string | null;
    controller: AbortController;
}

const pending = reactive<Pending[]>([]);
// Copia local para no perder archivos si dos subidas terminan casi al mismo tiempo.
let latest: Media[] = props.modelValue;
watch(
    () => props.modelValue,
    (v) => (latest = v),
);
function commit(list: Media[]) {
    latest = list;
    emit('update:modelValue', list);
}
const input = ref<HTMLInputElement | null>(null);
const notice = ref<string | null>(null);

const accept = computed(() =>
    props.kind === 'image'
        ? 'image/jpeg,image/png,image/webp'
        : 'video/mp4,video/quicktime,video/webm,video/x-m4v,video/3gpp,.mp4,.mov,.m4v,.webm',
);
const slots = computed(() => props.max - props.modelValue.length - pending.length);

function updateBusy() {
    emit('busy', pending.some((p) => !p.error));
}

function pick() {
    notice.value = null;
    input.value?.click();
}

async function onFiles(e: Event) {
    const files = Array.from((e.target as HTMLInputElement).files ?? []);
    (e.target as HTMLInputElement).value = '';
    if (files.length > slots.value) {
        notice.value = `Puedes agregar hasta ${props.max} ${props.kind === 'image' ? 'fotografías' : 'videos'}.`;
    }
    for (const file of files.slice(0, Math.max(0, slots.value))) {
        if (file.size > props.maxMb * 1024 * 1024) {
            notice.value = `“${file.name}” pesa ${formatBytes(file.size)}. El máximo es de ${props.maxMb} MB.`;
            continue;
        }
        const item = reactive<Pending>({
            key: Math.random().toString(36).slice(2),
            file,
            preview: URL.createObjectURL(file),
            progress: 0,
            error: null,
            controller: new AbortController(),
        });
        pending.push(item);
        start(item);
    }
}

async function start(item: Pending) {
    item.error = null;
    item.progress = 0;
    item.controller = new AbortController();
    updateBusy();
    try {
        const media = await uploadFile(item.file, props.kind, (p) => (item.progress = p), item.controller.signal);
        commit([...latest, media]);
        remove(item, false);
    } catch (e) {
        item.error = errorMessage(e, 'No pudimos subir este archivo. Revisa tu conexión e inténtalo otra vez.');
    }
    updateBusy();
}

function remove(item: Pending, abort = true) {
    if (abort) item.controller.abort();
    URL.revokeObjectURL(item.preview);
    const i = pending.indexOf(item);
    if (i >= 0) pending.splice(i, 1);
    updateBusy();
}

async function removeUploaded(m: Media) {
    commit(latest.filter((x) => x.id !== m.id));
    // Si todavía no forma parte de una aportación, se borra del servidor.
    http.delete(`/my/media/${m.id}`).catch(() => {});
}

onBeforeUnmount(() => pending.forEach((p) => URL.revokeObjectURL(p.preview)));
</script>

<template>
    <div>
        <div class="grid gap-3" :class="kind === 'image' ? 'grid-cols-2 sm:grid-cols-3' : 'grid-cols-1'">
            <!-- Archivos ya subidos -->
            <div v-for="m in modelValue" :key="m.id" class="relative overflow-hidden rounded-2xl bg-linen" :class="kind === 'image' ? 'aspect-square' : ''">
                <img v-if="kind === 'image'" :src="m.thumb" alt="" class="size-full object-cover" />
                <video v-else :src="m.url + '#t=0.1'" controls playsinline preload="metadata" class="w-full max-h-[60vh] bg-black rounded-2xl" />
                <button
                    type="button"
                    class="absolute right-2 top-2 grid size-9 place-items-center rounded-full bg-ink/70 text-paper backdrop-blur"
                    aria-label="Quitar"
                    @click="removeUploaded(m)"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Subidas en curso -->
            <div v-for="p in pending" :key="p.key" class="relative overflow-hidden rounded-2xl bg-linen" :class="kind === 'image' ? 'aspect-square' : 'aspect-video'">
                <img v-if="kind === 'image'" :src="p.preview" alt="" class="size-full object-cover opacity-70" />
                <video v-else :src="p.preview" muted playsinline preload="metadata" class="size-full object-cover bg-black opacity-70" />
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-paper/40 p-4 text-center">
                    <template v-if="!p.error">
                        <div class="h-1.5 w-4/5 overflow-hidden rounded-full bg-paper/80">
                            <div class="h-full rounded-full bg-rose transition-[width] duration-300" :style="{ width: Math.round(p.progress * 100) + '%' }" />
                        </div>
                        <span class="text-sm font-medium text-ink">
                            {{ p.progress >= 0.99 ? 'Guardando…' : `Subiendo ${Math.round(p.progress * 100)}%` }}
                        </span>
                        <span class="text-xs text-ink-soft">{{ formatBytes(p.file.size) }}</span>
                    </template>
                    <template v-else>
                        <AlertCircle class="size-6 text-rose-deep" />
                        <span class="text-xs leading-snug text-ink">{{ p.error }}</span>
                        <button type="button" class="btn btn-sm btn-primary" @click="start(p)"><RotateCcw class="size-4" /> Reintentar</button>
                    </template>
                </div>
                <button
                    type="button"
                    class="absolute right-2 top-2 grid size-9 place-items-center rounded-full bg-ink/70 text-paper"
                    aria-label="Cancelar"
                    @click="remove(p)"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Botón para elegir -->
            <button
                v-if="slots > 0"
                type="button"
                class="group flex flex-col items-center justify-center gap-2 rounded-2xl border-[1.5px] border-dashed border-sand bg-paper/60 p-6 text-center text-ink-soft transition hover:border-rose hover:text-ink"
                :class="kind === 'image' ? 'aspect-square' : 'min-h-40'"
                @click="pick"
            >
                <ImagePlus v-if="kind === 'image'" class="size-7 text-rose" :stroke-width="1.4" />
                <Video v-else class="size-7 text-rose" :stroke-width="1.4" />
                <span class="text-sm font-medium">
                    {{ kind === 'image' ? (modelValue.length ? 'Agregar otra' : 'Elegir fotografía') : modelValue.length ? 'Agregar otro video' : 'Elegir o grabar video' }}
                </span>
                <span class="text-xs text-muted">
                    {{ kind === 'image' ? 'JPG, PNG o WEBP' : 'MP4 o MOV' }} · hasta {{ maxMb }} MB
                </span>
            </button>
        </div>
        <p v-if="notice" class="field-error">{{ notice }}</p>
        <p v-if="kind === 'video' && pending.length" class="mt-3 text-xs text-muted">
            Puedes seguir escribiendo mientras se sube. No cierres esta página hasta que termine.
        </p>
        <input ref="input" type="file" class="hidden" :accept="accept" :multiple="kind === 'image'" @change="onFiles" />
    </div>
</template>
