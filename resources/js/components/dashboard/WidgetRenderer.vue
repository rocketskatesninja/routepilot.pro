<script setup lang="ts">
import { computed, type Component } from 'vue';
import BillingSummaryWidget from './widgets/BillingSummaryWidget.vue';
import MyRouteWidget from './widgets/MyRouteWidget.vue';
import NotificationsWidget from './widgets/NotificationsWidget.vue';
import RecentVisitsWidget from './widgets/RecentVisitsWidget.vue';
import RequestsWidget from './widgets/RequestsWidget.vue';
import RouteMapWidget from './widgets/RouteMapWidget.vue';
import StatsWidget from './widgets/StatsWidget.vue';
import TodayStopsWidget from './widgets/TodayStopsWidget.vue';
import WeatherWidget from './widgets/WeatherWidget.vue';
import WeekStripWidget from './widgets/WeekStripWidget.vue';

const props = defineProps<{ widgetKey: string; data: unknown }>();

const registry: Record<string, Component> = {
    stats: StatsWidget,
    route_map: RouteMapWidget,
    my_route: MyRouteWidget,
    requests: RequestsWidget,
    recent_visits: RecentVisitsWidget,
    week_strip: WeekStripWidget,
    today_stops: TodayStopsWidget,
    weather: WeatherWidget,
    billing_summary: BillingSummaryWidget,
    notifications: NotificationsWidget,
};

const component = computed(() => registry[props.widgetKey] ?? null);
</script>

<template>
    <component :is="component" v-if="component" :data="data" />
    <div v-else class="flex h-full items-center justify-center text-sm text-muted-foreground">Unknown widget</div>
</template>
