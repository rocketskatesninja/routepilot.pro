<script setup lang="ts">
// Name (required), email and phone: the part of every site form that says who's asking.
import { field } from '../layout';

const name = defineModel<string>('name', { required: true });
const email = defineModel<string>('email', { required: true });
const phone = defineModel<string>('phone', { required: true });
</script>

<template>
    <label class="@3xl:col-span-2">
        <span class="sr-only">Your name</span>
        <input v-model="name" required autocomplete="name" placeholder="Your name" :class="field" />
    </label>
    <label>
        <span class="sr-only">Email</span>
        <input v-model="email" type="email" autocomplete="email" placeholder="Email" :class="field" />
    </label>
    <label>
        <span class="sr-only">Phone</span>
        <input v-model="phone" type="tel" autocomplete="tel" placeholder="Phone" :class="field" />
    </label>
</template>
