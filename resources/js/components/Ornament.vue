<script setup lang="ts">
defineProps<{ size?: number }>();
</script>

<template>
    <!-- Pequeño adorno floral de línea fina -->
    <svg :width="size ?? 72" :height="(size ?? 72) / 4" viewBox="0 0 120 30" fill="none" aria-hidden="true" class="text-gold">
        <path d="M2 15h38M80 15h38" stroke="currentColor" stroke-width="0.8" stroke-linecap="round" opacity="0.6" />
        <path
            d="M60 6c-3 4-9 5-13 9 4 4 10 5 13 9 3-4 9-5 13-9-4-4-10-5-13-9z"
            stroke="currentColor"
            stroke-width="0.9"
            stroke-linejoin="round"
        />
        <circle cx="60" cy="15" r="1.6" fill="currentColor" />
        <circle cx="44" cy="15" r="1" fill="currentColor" opacity="0.7" />
        <circle cx="76" cy="15" r="1" fill="currentColor" opacity="0.7" />
    </svg>
</template>
