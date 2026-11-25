import axios, { AxiosError } from 'axios';
import type { Media } from './types';

export const http = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
});

/** Convierte cualquier error de la API en un mensaje comprensible en español. */
export function errorMessage(e: unknown, fallback = 'Algo no salió bien. Inténtalo de nuevo en un momento.'): string {
    if (axios.isCancel(e)) return 'Se canceló la subida.';
    const err = e as AxiosError<{ message?: string; errors?: Record<string, string[]> }>;
    if (!err?.response) {
        return 'No pudimos conectarnos. Revisa tu conexión a internet e inténtalo otra vez.';
    }
    const { status, data } = err.response;
    if (data?.errors) {
        const first = Object.values(data.errors)[0];
        if (first?.[0]) return first[0];
    }
    if (status === 413) return 'El archivo es demasiado grande para subirlo.';
    if (status === 419) return 'Tu sesión expiró. Recarga la página para continuar.';
    if (status === 429) return 'Vas muy rápido. Espera unos segundos e inténtalo de nuevo.';
    if (status === 401 || status === 403) return data?.message || 'No tienes acceso a esta sección.';
    return data?.message && status < 500 ? data.message : fallback;
}

export function fieldErrors(e: unknown): Record<string, string> {
    const err = e as AxiosError<{ errors?: Record<string, string[]> }>;
    const out: Record<string, string> = {};
    for (const [k, v] of Object.entries(err?.response?.data?.errors ?? {})) out[k] = v[0];
    return out;
}

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

/**
 * Sube un archivo por partes, con reintentos y progreso (0-1).
 * Funciona bien en redes móviles inestables y con los límites de PHP del hosting.
 */
export async function uploadFile(
    file: File,
    kind: 'image' | 'video',
    onProgress: (p: number) => void,
    signal?: AbortSignal,
): Promise<Media> {
    const { data: s } = await http.post('/uploads', { kind, name: file.name, size: file.size }, { signal });
    const size: number = s.chunk_size;
    let done = 0;

    for (let i = 0; i < s.total_chunks; i++) {
        const blob = file.slice(i * size, Math.min(file.size, (i + 1) * size));
        let attempt = 0;
        for (;;) {
            try {
                const form = new FormData();
                form.append('index', String(i));
                form.append('chunk', blob, `part-${i}`);
                await http.post(`/uploads/${s.id}/chunk`, form, {
                    signal,
                    onUploadProgress: (ev) => onProgress(Math.min(0.99, (done + (ev.loaded ?? 0)) / file.size)),
                });
                break;
            } catch (e) {
                const status = (e as AxiosError).response?.status;
                if (axios.isCancel(e) || (status && status < 500 && status !== 429) || ++attempt > 4) throw e;
                await sleep(800 * attempt * attempt);
            }
        }
        done += blob.size;
        onProgress(Math.min(0.99, done / file.size));
    }

    const { data } = await http.post<Media>(`/uploads/${s.id}/complete`, {}, { signal });
    onProgress(1);
    return data;
}

export function formatBytes(n: number): string {
    if (n < 1024 * 1024) return `${Math.max(1, Math.round(n / 1024))} KB`;
    if (n < 1024 * 1024 * 1024) return `${(n / 1024 / 1024).toFixed(1)} MB`;
    return `${(n / 1024 / 1024 / 1024).toFixed(2)} GB`;
}
