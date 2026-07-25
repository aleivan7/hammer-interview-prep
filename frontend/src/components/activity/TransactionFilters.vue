<script setup lang="ts">
import { BUCKET_LABELS, type Bucket } from '../../types/bucket'

defineProps<{
  search: string
  bucket: '' | Bucket
  reviewed: '' | 'true' | 'false'
}>()

const emit = defineEmits<{
  'update:search': [value: string]
  'update:bucket': [value: '' | Bucket]
  'update:reviewed': [value: '' | 'true' | 'false']
}>()

const buckets: Array<'' | Bucket> = ['', 'need', 'want', 'savings']
</script>

<template>
  <div class="filters">
    <label>
      <span class="sr-only">Search merchants</span>
      <input
        type="search"
        :value="search"
        placeholder="Search merchants"
        @input="emit('update:search', ($event.target as HTMLInputElement).value)"
      />
    </label>

    <label>
      <span>Bucket</span>
      <select
        :value="bucket"
        @change="emit('update:bucket', ($event.target as HTMLSelectElement).value as '' | Bucket)"
      >
        <option v-for="option in buckets" :key="option || 'all'" :value="option">
          {{ option ? BUCKET_LABELS[option] : 'All buckets' }}
        </option>
      </select>
    </label>

    <label>
      <span>Reviewed</span>
      <select
        :value="reviewed"
        @change="
          emit('update:reviewed', ($event.target as HTMLSelectElement).value as '' | 'true' | 'false')
        "
      >
        <option value="">All</option>
        <option value="true">Reviewed</option>
        <option value="false">Unreviewed</option>
      </select>
    </label>
  </div>
</template>

<style scoped>
.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

label {
  display: grid;
  gap: 0.3rem;
  color: var(--text-muted);
  font-size: 0.85rem;
}

input,
select {
  min-width: 10rem;
  padding: 0.55rem 0.7rem;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border);
  background: var(--bg-soft);
  color: var(--text);
}
</style>
