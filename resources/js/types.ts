export type ContributionType = 'letter' | 'video' | 'photo' | 'message' | 'secret' | 'questions' | 'song';

export interface Media {
    id: string;
    kind: 'image' | 'video' | 'audio';
    mime: string;
    size: number;
    width: number | null;
    height: number | null;
    name: string | null;
    url: string;
    thumb: string;
}

export interface Answer {
    question_id: number | null;
    question: string;
    answer: string;
}

export interface Contribution {
    id: string;
    type: ContributionType;
    title: string | null;
    artist: string | null;
    link: string | null;
    platform: string | null;
    body: string | null;
    signature: string | null;
    prompt: string | null;
    media: Media[];
    answers: Answer[];
    created_at: string;
    updated_at: string;
    is_favorite: boolean;
    is_hidden?: boolean;
    participant_id?: string;
    participant?: Participant | null;
}

export interface Participant {
    id: string;
    name: string;
    relationship: string;
    relationship_label: string;
    real_name?: string;
    display_name?: string | null;
    relationship_other?: string | null;
    is_hidden?: boolean;
    guest_id?: number | null;
    contributions_count?: number;
    personal_link?: string | null;
    created_at?: string;
}

export interface Person extends Participant {
    photo: Media | null;
    count: number;
    contributions: Contribution[];
}

export interface EventInfo {
    celebrant_name: string;
    celebrant_short_name: string;
    age: number | null;
    birthday_date: string | null;
    cover_title: string | null;
    cover_message: string | null;
    invite_title: string | null;
    invite_message: string | null;
    cover: Media | null;
    music: Media | null;
    submissions_close_at: string | null;
    accepting: boolean;
    // sólo admin
    memories_enabled?: boolean;
    submissions_open?: boolean;
    has_birthday_password?: boolean;
    music_enabled?: boolean;
    music_file?: Media | null;
}

export interface Limits {
    image_mb: number;
    video_mb: number;
    chunk_bytes: number;
    photos_per_memory: number;
    videos_per_contribution: number;
    message_chars: number;
    letter_chars: number;
    text_chars: number;
}

export interface AppState {
    can_participate: boolean;
    participant: (Participant & { contributions_count: number }) | null;
    prefill: { name: string; relationship: string | null } | null;
    birthday: { linked: boolean; unlocked: boolean; needs_password: boolean };
    admin: { name: string; email: string } | null;
    event: EventInfo | null;
    relationships: Record<string, string>;
    limits: Limits;
}

export interface Question {
    id: number;
    text: string;
}

/** Borrador editable de una aportación (lo que el formulario manipula). */
export interface DraftItem {
    type: ContributionType;
    title: string;
    artist: string;
    link: string;
    body: string;
    signature: string;
    prompt: string;
    media: Media[];
    answers: Record<number, string>;
    chosenQuestions: number[];
}
