<script setup lang="ts">
import { computed, type Component } from 'vue';
import MyRouteWidget from './widgets/MyRouteWidget.vue';
import RecentVisitsWidget from './widgets/RecentVisitsWidget.vue';
import RequestsWidget from './widgets/RequestsWidget.vue';
import StatsWidget from './widgets/StatsWidget.vue';

const props = defineProps<{ widgetKey: string; data: unknown }>();

const registry: Record<string, Component> = {
    stats: StatsWidget,
    my_route: MyRouteWidget,
    requests: RequestsWidget,
    recent_visits: RecentVisitsWidget,
};

const component = computed(() => registry[props.widgetKey] ?? null);
</script>

<template>
    <component :is="component" v-if="component" :data="data" />
    <div v-else class="flex h-full items-center justify-center text-sm text-muted-foreground">Unknown widget</div>
</template>
