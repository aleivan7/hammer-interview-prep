<script setup lang="ts">
import { computed } from 'vue'
import { BUCKET_LABELS, type Bucket } from '../../types/bucket'
import type { Category } from '../../types/category'

const props = withDefaults(
  defineProps<{
    categories: Category[]
    modelValue: number | null
    disabled?: boolean
    allowEmpty?: boolean
  }>(),
  {
    disabled: false,
    allowEmpty: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
  'create-intent': [bucket: Bucket]
}>()

const buckets: Bucket[] = ['need', 'want', 'savings']

const grouped = computed(() =>
  buckets.map((bucket) => ({
    bucket,
    label: BUCKET_LABELS[bucket],
    categories: props.categories
      .filter((category) => category.bucket === bucket && !category.archived_at)
      .sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name)),
  })),
)

function onChange(event: Event): void {
  const value = (event.target as HTMLSelectElement).value
  emit('update:modelValue', value ? Number(value) : null)
}

function onCreateIntent(bucket: Bucket): void {
  emit('create-intent', bucket)
}
</script>

<template>
  <div class="category-selector">
    <label class="label">
      Category
      <select
        class="field"
        :value="modelValue ?? ''"
        :disabled="disabled"
        @change="onChange"
      >
        <option v-if="allowEmpty" value="">No detailed category</option>
        <option v-else value="" disabled>Select a category</option>
        <optgroup
          v-for="group in grouped"
          :key="group.bucket"
          :label="group.label"
        >
          <option
            v-for="category in group.categories"
            :key="category.id"
            :value="category.id"
          >
            {{ category.name }}{{ category.is_system ? '' : ' (custom)' }}
          </option>
        </optgroup>
      </select>
    </label>

    <div class="create-row">
      <span class="hint">Need something else?</span>
      <button
        v-for="bucket in buckets"
        :key="bucket"
        type="button"
        class="btn btn-ghost create-btn"
        :disabled="disabled"
        @click="onCreateIntent(bucket)"
      >
        New in {{ BUCKET_LABELS[bucket] }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.category-selector {
  display: grid;
  gap: var(--space-2);
}

.label {
  display: grid;
  gap: 0.3rem;
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.create-row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  align-items: center;
}

.hint {
  color: var(--text-dim);
  font-size: 0.72rem;
}

.create-btn {
  min-height: 1.75rem;
  padding: 0.2rem 0.55rem;
  font-size: 0.72rem;
}
</style>
