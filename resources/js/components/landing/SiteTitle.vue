<script setup lang="ts">
// The company's name in its site header: the logo or picked icon beside it (or
// alone), styled from the editor's Company title settings. The public page and
// the editor's preview both use this, so they always match.
import { computed } from 'vue';
import { titleMark, titleStyle } from './titleStyle';
import type { TitleConfig } from './types';

const props = defineProps<{ title: TitleConfig; logoUrl: string | null; name: string }>();

const mark = computed(() => titleMark(props.title, props.logoUrl));
const text = computed(() => props.title.text || props.name);
const box = computed(() => ({ width: `${mark.value.px}px`, height: `${mark.value.px}px` }));
</script>

<template>
    <span class="flex min-w-0 items-center gap-2.5">
        <img v-if="mark.logo" :src="mark.logo" alt="" class="shrink-0 rounded object-contain" :style="box" />
        <component :is="mark.icon" v-else-if="mark.icon" class="text-primary shrink-0" :style="box" aria-hidden="true" />
        <span v-if="mark.text" class="truncate" :style="titleStyle(title)">{{ text }}</span>
        <span v-else class="sr-only">{{ text }}</span>
    </span>
</template>
