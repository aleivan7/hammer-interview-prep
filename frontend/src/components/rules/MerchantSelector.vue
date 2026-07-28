<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue'
import type { Merchant } from '../../types/merchant'

const props = defineProps<{
  merchants: Merchant[]
  modelValue: number | null
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
}>()

const query = shallowRef('')
const open = shallowRef(false)

const selected = computed(
  () => props.merchants.find((merchant) => merchant.id === props.modelValue) ?? null,
)

const filtered = computed(() => {
  const needle = query.value.trim().toLowerCase()
  if (!needle) {
    return props.merchants.slice(0, 40)
  }

  return props.merchants
    .filter((merchant) => {
      if (merchant.name.toLowerCase().includes(needle)) {
        return true
      }
      if (merchant.normalized_name.toLowerCase().includes(needle)) {
        return true
      }
      return merchant.example_descriptors.some((example) =>
        example.pattern.toLowerCase().includes(needle),
      )
    })
    .slice(0, 40)
})

watch(
  () => props.modelValue,
  () => {
    if (selected.value && !open.value) {
      query.value = selected.value.name
    }
  },
  { immediate: true },
)

function selectMerchant(merchant: Merchant): void {
  emit('update:modelValue', merchant.id)
  query.value = merchant.name
  open.value = false
}

function onFocus(): void {
  open.value = true
  if (selected.value) {
    query.value = selected.value.name
  }
}

function onBlur(): void {
  window.setTimeout(() => {
    open.value = false
    query.value = selected.value?.name ?? ''
  }, 120)
}

function onInput(): void {
  open.value = true
  if (selected.value && query.value.trim() !== selected.value.name) {
    emit('update:modelValue', null)
  }
}
</script>

<template>
  <div class="merchant-selector">
    <label class="label">
      Merchant
      <input
        v-model="query"
        class="field"
        type="search"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="open"
        aria-controls="merchant-options"
        placeholder="Search canonical merchants…"
        :disabled="disabled"
        autocomplete="off"
        @focus="onFocus"
        @blur="onBlur"
        @input="onInput"
      />
    </label>

    <ul
      v-if="open"
      id="merchant-options"
      class="options"
      role="listbox"
    >
      <li v-if="!filtered.length" class="empty" role="option" aria-disabled="true">
        No merchants match “{{ query }}”.
      </li>
      <li
        v-for="merchant in filtered"
        :key="merchant.id"
        role="option"
        :aria-selected="merchant.id === modelValue"
        class="option"
        :class="{ selected: merchant.id === modelValue }"
        @mousedown.prevent="selectMerchant(merchant)"
      >
        <strong>{{ merchant.name }}</strong>
        <span v-if="merchant.example_descriptors[0]">
          e.g. {{ merchant.example_descriptors[0].pattern }}
        </span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.merchant-selector {
  position: relative;
  display: grid;
  gap: 0.3rem;
}

.label {
  display: grid;
  gap: 0.3rem;
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.options {
  position: absolute;
  top: calc(100% + 0.25rem);
  left: 0;
  right: 0;
  z-index: 5;
  margin: 0;
  padding: 0.35rem;
  list-style: none;
  max-height: 14rem;
  overflow: auto;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: var(--bg-elevated);
  box-shadow: var(--shadow-modal);
}

.option,
.empty {
  display: grid;
  gap: 0.15rem;
  padding: 0.55rem 0.65rem;
  border-radius: var(--radius-sm);
}

.option {
  cursor: pointer;
}

.option:hover,
.option.selected {
  background: var(--bg-hover);
}

.option strong {
  font-size: 0.8125rem;
  font-weight: 600;
}

.option span,
.empty {
  color: var(--text-muted);
  font-size: 0.72rem;
}
</style>
