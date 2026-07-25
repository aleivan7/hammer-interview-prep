<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import AppShell from './layouts/AppShell.vue'

const route = useRoute()
const isPublicRoute = computed(() => route.meta.public === true)
</script>

<template>
  <RouterView v-if="isPublicRoute" />
  <AppShell v-else />
</template>
