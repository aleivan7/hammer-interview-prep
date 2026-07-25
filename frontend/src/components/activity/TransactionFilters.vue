<script setup lang="ts">
import { shallowRef } from 'vue'
import { BUCKET_LABELS, type Bucket } from '../../types/bucket'
import AppIcon from '../ui/AppIcon.vue'

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

const filtersOpen = shallowRef(false)

const chips: Array<{ value: '' | Bucket; label: string }> = [
  { value: '', label: 'All' },
  { value: 'need', label: BUCKET_LABELS.need },
  { value: 'want', label: BUCKET_LABELS.want },
  { value: 'savings', label: BUCKET_LABELS.savings },
]

function toggleBucket(value: '' | Bucket, current: '' | Bucket): void {
  if (value !== '' && value === current) {
    emit('update:bucket', '')
    return
  }
  emit('update:bucket', value)
}

function clearFilters(): void {
  emit('update:search', '')
  emit('update:bucket', '')
  emit('update:reviewed', '')
}
</script>

<template>
  <div class="filters">
    <div class="search-row">
      <label class="search">
        <AppIcon name="search" :size="16" />
        <span class="sr-only">Search transactions</span>
        <input
          class="field"
          type="search"
          :value="search"
          placeholder="Search transactions…"
          @input="emit('update:search', ($event.target as HTMLInputElement).value)"
        />
      </label>
      <button type="button" class="btn btn-ghost" @click="filtersOpen = !filtersOpen">
        <AppIcon name="filter" :size="16" />
        Filters
      </button>
    </div>

    <div v-if="filtersOpen" class="extra">
      <label>
        <span>Reviewed</span>
        <select
          class="field"
          :value="reviewed"
          @change="
            emit(
              'update:reviewed',
              ($event.target as HTMLSelectElement).value as '' | 'true' | 'false',
            )
          "
        >
          <option value="">All</option>
          <option value="true">Reviewed</option>
          <option value="false">Queued</option>
        </select>
      </label>
      <button type="button" class="btn btn-ghost" @click="clearFilters">Clear filters</button>
    </div>

    <div class="chips" role="group" aria-label="Bucket filters">
      <button
        v-for="chip in chips"
        :key="chip.value || 'all'"
        type="button"
        class="chip"
        :class="{ active: bucket === chip.value }"
        :data-bucket="chip.value || undefined"
        @click="toggleBucket(chip.value, bucket)"
      >
        <span v-if="chip.value" class="chip-dot" aria-hidden="true" />
        {{ chip.label }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.filters {
  display: grid;
  gap: var(--space-3);
}

.search-row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
}

.search {
  position: relative;
  flex: 1 1 16rem;
}

.search :deep(.icon) {
  position: absolute;
  left: 0.8rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-dim);
  pointer-events: none;
}

.search .field {
  padding-left: 2.35rem;
}

.extra {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  align-items: end;
}

.extra label {
  display: grid;
  gap: 0.3rem;
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.extra .field {
  min-width: 10rem;
}

.chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}
</style>
