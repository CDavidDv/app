<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ImagePlus, Music2, Trash2 } from 'lucide-vue-next';
import { errorMessage, fieldErrors, http } from '@/api';
import { loadState } from '@/store';
import type { EventInfo } from '@/types';

const s = ref<EventInfo | null>(null);
const form = reactive({
    celebrant_name: '',
    celebrant_short_name: '',
    age: null as number | null,
    birthday_date: '',
    cover_title: '',
    cover_message: '',
    invite_title: '',
    invite_message: '',
    memories_enabled: false,
    submissions_open: true,
    submissions_close_at: '',
    music_enabled: false,
    birthday_password: '',
});
const errors = ref<Record<string, string>>({});
const message = ref<string | null>(null);
const saving = ref(false);
const uploading = ref<string | null>(null);
const pw = reactive({ current_password: '', password: '', password_confirmation: '' });
const pwMsg = ref<string | null>(null);

function toLocalInput(iso: string | null) {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function fill(data: EventInfo) {
    s.value = data;
    Object.assign(form, {
        celebrant_name: data.celebrant_name,
        celebrant_short_name: data.celebrant_short_name,
        age: data.age,
        birthday_date: data.birthday_date ?? '',
        cover_title: data.cover_title ?? '',
        cover_message: data.cover_message ?? '',
        invite_title: data.invite_title ?? '',
        invite_message: data.invite_message ?? '',
        memories_enabled: !!data.memories_enabled,
        submissions_open: !!data.submissions_open,
        submissions_close_at: toLocalInput(data.submissions_close_at),
        music_enabled: !!data.music_enabled,
        birthday_password: '',
    });
}

onMounted(async () => fill((await http.get<EventInfo>('/admin/settings')).data));

async function save(extra: Record<string, unknown> = {}) {
    saving.value = true;
    errors.value = {};
    message.value = null;
    try {
        const body: Record<string, unknown> = { ...form, ...extra };
        if (!form.birthday_password) delete body.birthday_password;
        if (!form.submissions_close_at) body.submissions_close_at = null;
        if (!form.birthday_date) body.birthday_date = null;
        fill((await http.put<EventInfo>('/admin/settings', body)).data);
        await loadState(true);
        message.value = 'Cambios guardados.';
        setTimeout(() => (message.value = null), 3000);
    } catch (e) {
        errors.value = fieldErrors(e);
        message.value = errorMessage(e);
    } finally {
        saving.value = false;
    }
}

async function upload(what: 'cover' | 'music', e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    (e.target as HTMLInputElement).value = '';
    if (!file) return;
    uploading.value = what;
    try {
        const fd = new FormData();
        fd.append('file', file);
        fill((await http.post<EventInfo>(`/admin/settings/${what}`, fd)).data);
        loadState(true);
    } catch (err) {
        alert(errorMessage(err));
    } finally {
        uploading.value = null;
    }
}
async function removeMedia(what: 'cover' | 'music') {
    if (!confirm('¿Quitar este archivo?')) return;
    fill((await http.delete<EventInfo>(`/admin/settings/${what}`)).data);
}
async function changePassword() {
    pwMsg.value = null;
    try {
        await http.put('/admin/password', pw);
        pwMsg.value = 'Contraseña actualizada.';
        Object.assign(pw, { current_password: '', password: '', password_confirmation: '' });
    } catch (e) {
        pwMsg.value = errorMessage(e);
    }
}
</script>

<template>
    <div v-if="s" class="max-w-3xl">
        <h1 class="text-4xl">Configuración</h1>
        <p class="mb-8 text-ink-soft">Todo lo que verán los invitados y la cumpleañera.</p>

        <!-- Interruptores principales -->
        <section class="paper mb-6 space-y-4 rounded-3xl p-6">
            <h2 class="text-2xl">Estado</h2>
            <label class="flex items-start gap-3">
                <input v-model="form.memories_enabled" type="checkbox" class="mt-1 size-5 accent-rose" />
                <span><strong class="font-medium">Página de recuerdos visible para ella</strong><br /><span class="text-sm text-muted">Mientras esté apagada, verá “Tu regalo se está preparando”. Tú siempre puedes previsualizarla.</span></span>
            </label>
            <label class="flex items-start gap-3">
                <input v-model="form.submissions_open" type="checkbox" class="mt-1 size-5 accent-rose" />
                <span><strong class="font-medium">Recibir nuevos recuerdos</strong><br /><span class="text-sm text-muted">Apágalo para cerrar la cápsula manualmente.</span></span>
            </label>
            <div>
                <label class="field-label">Fecha y hora límite para participar</label>
                <input v-model="form.submissions_close_at" type="datetime-local" class="field" />
                <p class="mt-1 text-xs text-muted">Después de esta fecha nadie puede enviar ni editar. Déjalo vacío para no tener límite.</p>
            </div>
        </section>

        <!-- Cumpleañera -->
        <section class="paper mb-6 space-y-4 rounded-3xl p-6">
            <h2 class="text-2xl">La cumpleañera</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="field-label">Nombre completo</label>
                    <input v-model="form.celebrant_name" class="field" />
                    <p v-if="errors.celebrant_name" class="field-error">{{ errors.celebrant_name }}</p>
                </div>
                <div>
                    <label class="field-label">Cómo la llamamos</label>
                    <input v-model="form.celebrant_short_name" class="field" />
                </div>
                <div>
                    <label class="field-label">Años que cumple</label>
                    <input v-model.number="form.age" type="number" min="1" max="120" class="field" />
                </div>
                <div>
                    <label class="field-label">Fecha de cumpleaños</label>
                    <input v-model="form.birthday_date" type="date" class="field" />
                </div>
            </div>
            <div>
                <p class="field-label">Fotografía principal</p>
                <div class="flex items-center gap-4">
                    <div class="h-28 w-24 overflow-hidden rounded-lg bg-linen">
                        <img v-if="s.cover" :src="s.cover.thumb" alt="" class="size-full object-cover" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="btn btn-sm btn-ghost cursor-pointer">
                            <ImagePlus class="size-4" /> {{ uploading === 'cover' ? 'Subiendo…' : s.cover ? 'Cambiar foto' : 'Subir foto' }}
                            <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="upload('cover', $event)" />
                        </label>
                        <button v-if="s.cover" class="btn btn-sm btn-ghost" @click="removeMedia('cover')"><Trash2 class="size-4" /> Quitar</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Textos -->
        <section class="paper mb-6 space-y-4 rounded-3xl p-6">
            <h2 class="text-2xl">Textos</h2>
            <div>
                <label class="field-label">Mensaje de portada (lo verá ella)</label>
                <textarea v-model="form.cover_message" class="field min-h-28 font-serif text-lg" />
            </div>
            <div>
                <label class="field-label">Título de la invitación (lo ven los participantes)</label>
                <input v-model="form.invite_title" class="field" />
            </div>
            <div>
                <label class="field-label">Descripción de la invitación</label>
                <textarea v-model="form.invite_message" class="field min-h-28" />
            </div>
        </section>

        <!-- Privacidad -->
        <section class="paper mb-6 space-y-4 rounded-3xl p-6">
            <h2 class="text-2xl">Palabra secreta para ella</h2>
            <p class="text-sm text-muted">
                Opcional. Además del enlace, se le pedirá esta palabra para entrar.
                {{ s.has_birthday_password ? 'Actualmente hay una palabra secreta configurada.' : 'Ahora mismo no hay palabra secreta.' }}
            </p>
            <input v-model="form.birthday_password" type="text" class="field" autocomplete="off" placeholder="Nueva palabra secreta (mínimo 4 caracteres)" />
            <p v-if="errors.birthday_password" class="field-error">{{ errors.birthday_password }}</p>
            <button v-if="s.has_birthday_password" class="btn btn-sm btn-ghost" @click="save({ remove_birthday_password: true })">Quitar palabra secreta</button>
        </section>

        <!-- Música -->
        <section class="paper mb-6 space-y-4 rounded-3xl p-6">
            <h2 class="text-2xl">Música ambiental</h2>
            <p class="text-sm text-muted">Nunca se reproduce sola: ella verá un botón discreto para encenderla.</p>
            <div class="flex flex-wrap items-center gap-3">
                <label class="btn btn-sm btn-ghost cursor-pointer">
                    <Music2 class="size-4" /> {{ uploading === 'music' ? 'Subiendo…' : s.music_file ? 'Cambiar canción' : 'Subir canción (MP3/M4A)' }}
                    <input type="file" accept="audio/mpeg,audio/mp4,audio/x-m4a,audio/ogg,.mp3,.m4a,.ogg" class="hidden" @change="upload('music', $event)" />
                </label>
                <span v-if="s.music_file" class="text-sm text-ink-soft">{{ s.music_file.name }}</span>
                <button v-if="s.music_file" class="btn btn-sm btn-ghost" @click="removeMedia('music')"><Trash2 class="size-4" /></button>
            </div>
            <label v-if="s.music_file" class="flex items-center gap-2 text-sm"><input v-model="form.music_enabled" type="checkbox" class="size-4 accent-rose" /> Mostrar botón de música</label>
            <audio v-if="s.music_file" :src="s.music_file.url" controls preload="none" class="w-full" />
        </section>

        <div class="sticky bottom-4 z-10 flex items-center gap-4 rounded-full bg-paper/95 p-2 pl-5 shadow-[var(--shadow-lift)] backdrop-blur">
            <span class="flex-1 text-sm text-ink-soft">{{ message ?? 'Recuerda guardar tus cambios.' }}</span>
            <button class="btn btn-primary" :disabled="saving" @click="save()">{{ saving ? 'Guardando…' : 'Guardar' }}</button>
        </div>

        <!-- Cuenta -->
        <section class="paper mt-10 space-y-3 rounded-3xl p-6">
            <h2 class="text-2xl">Tu contraseña de administrador</h2>
            <input v-model="pw.current_password" type="password" class="field" placeholder="Contraseña actual" autocomplete="current-password" />
            <input v-model="pw.password" type="password" class="field" placeholder="Nueva contraseña (mínimo 10 caracteres)" autocomplete="new-password" />
            <input v-model="pw.password_confirmation" type="password" class="field" placeholder="Repite la nueva contraseña" autocomplete="new-password" />
            <p v-if="pwMsg" class="text-sm text-ink-soft">{{ pwMsg }}</p>
            <button class="btn btn-sm btn-ghost" @click="changePassword">Cambiar contraseña</button>
        </section>
    </div>
</template>
