<script setup lang="ts">
import type { DemoPersona } from '../../types/demoUser'

defineProps<{
  persona: DemoPersona
  selected?: boolean
  busy?: boolean
}>()

const emit = defineEmits<{
  select: [id: number]
}>()
</script>

<template>
  <article class="persona-card panel" :class="{ selected }">
    <div class="card-top">
      <span class="avatar" aria-hidden="true">{{ persona.avatar_initials }}</span>
      <span class="pill">{{ persona.persona_label }}</span>
    </div>

    <h2 class="name">{{ persona.name }}</h2>
    <p class="description">{{ persona.description }}</p>

    <dl class="stats">
      <div>
        <dt>Monthly income</dt>
        <dd>${{ persona.monthly_income }}</dd>
      </div>
      <div>
        <dt>Accounts</dt>
        <dd>{{ persona.account_count }}</dd>
      </div>
      <div class="status">
        <dt>Status</dt>
        <dd>{{ persona.financial_status_label }}</dd>
      </div>
    </dl>

    <button
      type="button"
      class="btn btn-primary continue"
      :disabled="busy"
      @click="emit('select', persona.id)"
    >
      Continue as {{ persona.name.split(/\s+/)[0] }}
    </button>
  </article>
</template>

<style scoped>
.persona-card {
  display: grid;
  gap: var(--space-4);
  padding: var(--space-5);
  border: 1px solid var(--border);
  transition:
    border-color 160ms ease,
    transform 160ms ease,
    box-shadow 160ms ease;
}

.persona-card:hover,
.persona-card.selected {
  border-color: rgba(34, 197, 94, 0.45);
  box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.15);
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-3);
}

.avatar {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-pill);
  background: var(--accent-soft);
  color: var(--accent-text);
  font-size: 0.9rem;
  font-weight: 700;
}

.name {
  margin: 0;
  font-size: 1.2rem;
  font-weight: 600;
  letter-spacing: -0.02em;
}

.description {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.9rem;
  line-height: 1.45;
  min-height: 4.2em;
}

.stats {
  display: grid;
  gap: var(--space-3);
  margin: 0;
}

.stats div {
  display: grid;
  gap: 0.15rem;
}

.stats dt {
  color: var(--text-dim);
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.stats dd {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
}

.status dd {
  color: var(--accent-text);
  font-weight: 500;
  font-size: 0.88rem;
}

.continue {
  width: 100%;
  min-height: 2.5rem;
}

.continue:disabled {
  opacity: 0.7;
  cursor: wait;
}
</style>
