import { reactive } from 'vue';
import { http } from './api';
import type { AppState } from './types';

export const store = reactive<{ state: AppState | null; loading: boolean }>({
    state: null,
    loading: false,
});

let inflight: Promise<AppState> | null = null;

export async function loadState(force = false): Promise<AppState> {
    if (store.state && !force) return store.state;
    if (inflight && !force) return inflight;
    store.loading = true;
    inflight = http
        .get<AppState>('/state')
        .then((r) => {
            store.state = r.data;
            return r.data;
        })
        .finally(() => {
            store.loading = false;
            inflight = null;
        });
    return inflight;
}

export const typeLabels: Record<string, { title: string; short: string; hint: string }> = {
    letter: { title: 'Una carta para ti', short: 'Carta', hint: 'Escribe con calma todo lo que quieras decirle.' },
    video: { title: 'Un video para ti', short: 'Video', hint: 'Graba o elige un video desde tu galería.' },
    photo: { title: 'Un recuerdo juntos', short: 'Recuerdo', hint: 'Una fotografía y la historia que hay detrás.' },
    message: { title: 'Unas palabras para ti', short: 'Palabras', hint: 'Un mensaje corto, directo al corazón.' },
    secret: { title: 'Algo que nunca te he dicho', short: 'Nunca te he dicho', hint: 'Un agradecimiento, una confesión bonita, un sentimiento.' },
    questions: { title: 'Responder unas preguntas', short: 'Preguntas', hint: 'Preguntas que ayudan a encontrar las palabras.' },
    song: { title: 'Una canción que me recuerda a ti', short: 'Canción', hint: 'Un enlace de YouTube, Spotify o donde la escuches.' },
};

const platforms: [string, string][] = [
    ['music.youtube.com', 'YouTube Music'],
    ['youtube.com', 'YouTube'],
    ['youtu.be', 'YouTube'],
    ['spotify.com', 'Spotify'],
    ['spotify.link', 'Spotify'],
    ['spoti.fi', 'Spotify'],
    ['music.apple.com', 'Apple Music'],
    ['deezer.com', 'Deezer'],
    ['deezer.page.link', 'Deezer'],
    ['soundcloud.com', 'SoundCloud'],
    ['tidal.com', 'Tidal'],
    ['music.amazon.com', 'Amazon Music'],
    ['music.amazon.com.mx', 'Amazon Music'],
    ['music.amazon.es', 'Amazon Music'],
];

/** Devuelve la URL solo si es http(s); evita enlaces javascript: u otros esquemas. */
export function safeUrl(raw: string | null | undefined): string | null {
    if (!raw) return null;
    let v = raw.trim();
    const found = v.match(/https?:\/\/\S+/i);
    if (found) v = found[0];
    else if (!/^[a-z][a-z0-9+.-]*:/i.test(v)) v = 'https://' + v;
    try {
        const u = new URL(v);
        return u.protocol === 'https:' || u.protocol === 'http:' ? u.href : null;
    } catch {
        return null;
    }
}

export function platformOf(raw: string | null | undefined): string | null {
    const url = safeUrl(raw);
    if (!url) return null;
    const host = new URL(url).hostname.toLowerCase().replace(/^(www|m|open|on|listen|play)\./, '');
    for (const [d, name] of platforms) if (host === d || host.endsWith('.' + d)) return name;
    return host.includes('.') ? host : null;
}

export const secretPrompts = [
    'Algo que nunca te he dicho es…',
    'Nunca te di las gracias por…',
    'Quiero que sepas que…',
    'Me haces sentir…',
];

export function formatDate(iso: string | null | undefined, opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'long' }): string {
    if (!iso) return '';
    const d = iso.length === 10 ? new Date(iso + 'T12:00:00') : new Date(iso);
    return new Intl.DateTimeFormat('es-MX', opts).format(d);
}
