<script setup lang="ts">
import { useSiteBrand } from '@/components/landing/brand';
import { choice } from '@/components/landing/editor/choice';
import EditorField from '@/components/landing/editor/EditorField.vue';
import Segmented from '@/components/landing/editor/Segmented.vue';
import FontPicker from '@/components/landing/FontPicker.vue';
import { LANDING_ICONS } from '@/components/landing/icons';
import SiteTitle from '@/components/landing/SiteTitle.vue';
import { TITLE_DISPLAYS, TITLE_SHADOWS, TITLE_SIZES, TITLE_TRACKINGS, TITLE_WEIGHTS } from '@/components/landing/titleStyle';
import type { TitleConfig } from '@/components/landing/types';
import { Input } from '@/components/ui/input';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/** The landing editor's "Company title" panel: how the name in the site header looks. */
const title = defineModel<TitleConfig>({ required: true });
const brand = useSiteBrand();

const select = 'border-input bg-background h-9 w-full rounded-md border px-2 text-sm';
// A solid title color is opt-in: null = the theme's text color.
const useCustomColor = computed({
    get: () => title.value.color !== null,
    set: (on: boolean) => (title.value.color = on ? (title.value.color ?? '#0f172a') : null),
});
</script>

<template>
    <!-- Exactly what the site header shows. -->
    <div class="border-border bg-background flex min-h-14 items-center justify-center overflow-hidden rounded-lg border px-4 py-3">
        <SiteTitle :title="title" :logo-url="brand.logoUrl" :name="brand.name" />
    </div>
    <EditorField label="Title text" hint="Blank uses your company name.">
        <Input v-model="title.text" placeholder="Your company name" />
    </EditorField>
    <EditorField label="Icon beside the name">
        <div class="flex flex-wrap gap-1.5" role="group" aria-label="Icon beside the name">
            <button
                type="button"
                :aria-pressed="title.icon === null"
                class="h-10 rounded-md border px-3 text-xs"
                :class="choice(title.icon === null)"
                @click="title.icon = null"
            >
                None
            </button>
            <button
                type="button"
                :aria-pressed="title.icon === 'logo'"
                class="flex h-10 items-center gap-1.5 rounded-md border px-2.5 text-xs"
                :class="choice(title.icon === 'logo')"
                @click="title.icon = 'logo'"
            >
                <img v-if="brand.logoUrl" :src="brand.logoUrl" alt="" class="size-6 rounded object-contain" /> Logo
            </button>
            <button
                v-for="ic in LANDING_ICONS"
                :key="ic.key"
                type="button"
                :title="ic.label"
                :aria-label="ic.label"
                :aria-pressed="title.icon === ic.key"
                class="flex size-10 items-center justify-center rounded-md border"
                :class="choice(title.icon === ic.key)"
                @click="title.icon = ic.key"
            >
                <component :is="ic.icon" class="size-5" />
            </button>
        </div>
        <!-- The logo itself lives in Company settings (emails and the portal use it too). -->
        <template v-if="title.icon === 'logo'" #hint>
            <template v-if="brand.logoUrl">
                This is your company logo.
                <Link href="/company" class="text-primary underline-offset-4 hover:underline">Change it in Company settings</Link>.
            </template>
            <template v-else>
                No logo uploaded yet, so nothing shows.
                <Link href="/company" class="text-primary underline-offset-4 hover:underline">Add one in Company settings</Link>, or pick an icon.
            </template>
        </template>
    </EditorField>
    <EditorField label="Show in header"><Segmented v-model="title.display" :options="TITLE_DISPLAYS" /></EditorField>
    <EditorField label="Size"><Segmented v-model="title.size" :options="TITLE_SIZES" /></EditorField>
    <div class="grid grid-cols-2 gap-3">
        <EditorField label="Font"><FontPicker v-model="title.font" /></EditorField>
        <EditorField label="Shadow">
            <select v-model="title.shadow" :class="select">
                <option v-for="o in TITLE_SHADOWS" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
        </EditorField>
        <EditorField label="Weight">
            <select v-model="title.weight" :class="select">
                <option v-for="o in TITLE_WEIGHTS" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
        </EditorField>
        <EditorField label="Spacing">
            <select v-model="title.tracking" :class="select">
                <option v-for="o in TITLE_TRACKINGS" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
        </EditorField>
    </div>
    <EditorField label="Color">
        <div class="flex items-center gap-4">
            <label class="flex items-center gap-1.5"><input v-model="title.color_type" type="radio" value="solid" /> Solid</label>
            <label class="flex items-center gap-1.5"><input v-model="title.color_type" type="radio" value="gradient" /> Gradient</label>
        </div>
        <div v-if="title.color_type === 'solid'" class="flex items-center gap-2">
            <label class="flex items-center gap-1.5"
                ><input v-model="useCustomColor" type="checkbox" class="border-input size-4 rounded" /> Custom color</label
            >
            <input v-if="title.color !== null" v-model="title.color" type="color" class="border-input bg-background h-8 w-12 rounded border" />
        </div>
        <div v-else class="flex items-center gap-2">
            <input v-model="title.gradient_start" type="color" class="border-input h-8 w-9 rounded border" title="Start color" />
            <input v-model="title.gradient_via" type="color" class="border-input h-8 w-9 rounded border" title="Middle color" />
            <input v-model="title.gradient_end" type="color" class="border-input h-8 w-9 rounded border" title="End color" />
            <label class="ml-auto flex items-center gap-1.5 text-xs">
                Angle <Input v-model.number="title.gradient_angle" type="number" min="0" max="360" class="w-16" />
            </label>
        </div>
    </EditorField>
    <div class="flex items-center gap-2">
        <label class="flex items-center gap-1.5"><input v-model="title.outline" type="checkbox" class="border-input size-4 rounded" /> Outline</label>
        <template v-if="title.outline">
            <input v-model="title.outline_color" type="color" class="border-input h-8 w-9 rounded border" title="Outline color" />
            <select v-model.number="title.outline_width" class="border-input bg-background h-8 rounded-md border px-2 text-sm">
                <option :value="1">Thin</option>
                <option :value="2">Medium</option>
                <option :value="3">Thick</option>
            </select>
        </template>
    </div>
</template>
