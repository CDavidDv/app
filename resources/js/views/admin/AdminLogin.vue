<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import Ornament from '@/components/Ornament.vue';
import { errorMessage, http } from '@/api';
import { loadState } from '@/store';

const router = useRouter();
const form = reactive({ email: '', password: '', remember: true });
const error = ref<string | null>(null);
const loading = ref(false);

async function submit() {
    loading.value = true;
    error.value = null;
    try {
        await http.post('/admin/login', form);
        await loadState(true);
        router.replace('/admin');
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <main class="grid min-h-dvh place-items-center px-6">
        <form class="paper w-full max-w-sm rounded-[28px] p-8 animate-rise" @submit.prevent="submit">
            <p class="eyebrow mb-3 text-center">Un pedacito de todos</p>
            <h1 class="mb-4 text-center text-3xl">Administración</h1>
            <Ornament class="mx-auto mb-8" />
            <label class="field-label" for="email">Correo</label>
            <input id="email" v-model="form.email" type="email" class="field mb-4" autocomplete="username" required />
            <label class="field-label" for="password">Contraseña</label>
            <input id="password" v-model="form.password" type="password" class="field" autocomplete="current-password" required />
            <label class="mt-4 flex items-center gap-2 text-sm text-ink-soft">
                <input v-model="form.remember" type="checkbox" class="size-4 accent-rose" /> Mantener sesión iniciada
            </label>
            <p v-if="error" class="field-error">{{ error }}</p>
            <button class="btn btn-primary mt-6 w-full" :disabled="loading">{{ loading ? 'Entrando…' : 'Entrar' }}</button>
        </form>
    </main>
</template>
