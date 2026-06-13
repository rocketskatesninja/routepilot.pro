<script setup lang="ts">
import { computed, type Component } from 'vue';
import AccountBalanceWidget from './widgets/AccountBalanceWidget.vue';
import BillingSummaryWidget from './widgets/BillingSummaryWidget.vue';
import MyPoolsWidget from './widgets/MyPoolsWidget.vue';
import MyRouteWidget from './widgets/MyRouteWidget.vue';
import NextVisitWidget from './widgets/NextVisitWidget.vue';
import NotificationsWidget from './widgets/NotificationsWidget.vue';
import RecentTenantsWidget from './widgets/RecentTenantsWidget.vue';
import RecentVisitsWidget from './widgets/RecentVisitsWidget.vue';
import RequestsWidget from './widgets/RequestsWidget.vue';
import RouteMapWidget from './widgets/RouteMapWidget.vue';
import StatsWidget from './widgets/StatsWidget.vue';
import TodayStopsWidget from './widgets/TodayStopsWidget.vue';
import WeatherWidget from './widgets/WeatherWidget.vue';
import WeekStripWidget from './widgets/WeekStripWidget.vue';

const props = defineProps<{ widgetKey: string; data: unknown }>();

const registry: Record<string, Component> = {
    // tenant_admin
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
    // agent
    agent_stats: StatsWidget,
    agent_route: MyRouteWidget,
    // customer
    my_pools: MyPoolsWidget,
    next_visit: NextVisitWidget,
    account_balance: AccountBalanceWidget,
    customer_visits: RecentVisitsWidget,
    // super_admin
    platform_stats: StatsWidget,
    recent_tenants: RecentTenantsWidget,
};

const component = computed(() => registry[props.widgetKey] ?? null);
</script>

<template>
    <component :is="component" v-if="component" :data="data" />
    <div v-else class="flex h-full items-center justify-center text-sm text-muted-foreground">Unknown widget</div>
</template>
