<script setup lang="ts">
import { BILLING_BADGES, HERO_BADGES, MAX_BADGES } from '@/components/landing/badges';
import { choice, flip, TEXTAREA } from '@/components/landing/editor/choice';
import EditorField from '@/components/landing/editor/EditorField.vue';
import Segmented from '@/components/landing/editor/Segmented.vue';
import LandingImagePicker from '@/components/landing/LandingImagePicker.vue';
import { ALIGN_OPTIONS, HERO_BACKGROUNDS, HERO_EFFECTS, HERO_PRESETS } from '@/components/landing/meta';
import { TITLE_SIZES } from '@/components/landing/titleStyle';
import { Input } from '@/components/ui/input';
import { useCustomerBilling } from '@/composables/useCustomerBilling';
import { computed } from 'vue';

interface HeroSection {
    headline: string;
    subhead: string;
    align: string;
    cta_label: string;
    cta_anchor: string;
    badges: string[];
    headline_size: string;
    headline_max_width: number;
    bg_type: string;
    preset: string;
    image_url: string | null;
    gradient_start: string;
    gradient_end: string;
    effects: Record<string, boolean> & { overlay_opacity: number };
}

/** The landing editor's hero panel. It edits the section in place, as every panel does: the preview reads the same object. */
const props = defineProps<{ section: HeroSection }>();
const s = props.section;

// Badges about paying online only make sense for a company that bills here.
const billing = useCustomerBilling();
const pickableBadges = computed(() => HERO_BADGES.filter((b) => billing.value || !BILLING_BADGES.includes(b.key)));
</script>

<template>
    <EditorField label="Headline" hint="Press Enter to start a new line.">
        <textarea v-model="s.headline" rows="2" :class="TEXTAREA" />
    </EditorField>
    <EditorField label="Subheadline"><textarea v-model="s.subhead" rows="2" :class="TEXTAREA" /></EditorField>
    <EditorField label="Text alignment"><Segmented v-model="s.align" :options="ALIGN_OPTIONS" /></EditorField>
    <div class="grid grid-cols-2 gap-3">
        <EditorField label="Button text"><Input v-model="s.cta_label" /></EditorField>
        <EditorField label="Button target"><Input v-model="s.cta_anchor" placeholder="contact" /></EditorField>
    </div>
    <EditorField hint="Shown under the headline. Only pick what’s true of your business.">
        <template #label>Badges · {{ s.badges.length }} of {{ MAX_BADGES }}</template>
        <div class="flex flex-wrap gap-1.5">
            <button
                v-for="b in pickableBadges"
                :key="b.key"
                type="button"
                :aria-pressed="s.badges.includes(b.key)"
                :disabled="!s.badges.includes(b.key) && s.badges.length >= MAX_BADGES"
                class="rounded-full border px-2.5 py-1 text-xs font-medium disabled:opacity-40"
                :class="choice(s.badges.includes(b.key))"
                @click="flip(s.badges, b.key)"
            >
                {{ b.label }}
            </button>
        </div>
        <button
            v-if="s.badges.length"
            type="button"
            class="text-muted-foreground hover:text-foreground justify-self-start text-xs underline-offset-4 hover:underline"
            @click="s.badges = []"
        >
            Turn all off
        </button>
    </EditorField>
    <EditorField label="Headline size"><Segmented v-model="s.headline_size" :options="TITLE_SIZES" /></EditorField>
    <EditorField :label="`Headline width · ${s.headline_max_width}rem`">
        <input v-model.number="s.headline_max_width" type="range" min="32" max="80" class="w-full" />
    </EditorField>
    <EditorField label="Background">
        <Segmented v-model="s.bg_type" :options="HERO_BACKGROUNDS" />
        <div v-if="s.bg_type === 'preset'" class="grid grid-cols-4 gap-2">
            <button
                v-for="p in HERO_PRESETS"
                :key="p"
                type="button"
                :aria-pressed="s.preset === p"
                class="overflow-hidden rounded-md border-2"
                :class="s.preset === p ? 'border-primary' : 'border-transparent'"
                @click="s.preset = p"
            >
                <img :src="`/assets/images/hero-presets/${p}.jpg`" :alt="p" loading="lazy" class="h-11 w-full object-cover" />
            </button>
        </div>
        <LandingImagePicker
            v-else-if="s.bg_type === 'image'"
            :url="s.image_url"
            label="hero image"
            @uploaded="(e) => Object.assign(s, { image_path: e.path, image_url: e.url })"
        />
        <div v-else class="grid grid-cols-2 gap-3">
            <label class="grid gap-1 text-xs"
                >Top color <input v-model="s.gradient_start" type="color" class="border-input h-9 w-full rounded border"
            /></label>
            <label class="grid gap-1 text-xs"
                >Bottom color <input v-model="s.gradient_end" type="color" class="border-input h-9 w-full rounded border"
            /></label>
        </div>
    </EditorField>
    <EditorField label="Effects">
        <div class="grid grid-cols-2 gap-x-4 gap-y-2">
            <label v-for="fx in HERO_EFFECTS" :key="fx.value" class="flex items-center gap-2">
                <input v-model="s.effects[fx.value]" type="checkbox" /> {{ fx.label }}
            </label>
        </div>
        <label v-if="s.effects.dark_overlay" class="grid gap-1 text-xs">
            Overlay · {{ s.effects.overlay_opacity }}%
            <input v-model.number="s.effects.overlay_opacity" type="range" min="0" max="90" class="w-full" />
        </label>
    </EditorField>
</template>
