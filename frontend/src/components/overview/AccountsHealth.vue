<script setup lang="ts">
import type { Account } from '../../types/account'
import { formatDollars } from '../../utils/money'

defineProps<{
  accounts: Account[]
}>()
</script>

<template>
  <section class="panel">
    <header class="panel-header">
      <div>
        <h2>Accounts</h2>
        <p>Sync health for the demo persona</p>
      </div>
    </header>

    <ul class="panel-rows">
      <li v-for="account in accounts" :key="account.id" class="row">
        <div class="left">
          <span class="tile" aria-hidden="true">{{ account.logo_key.slice(0, 1).toUpperCase() }}</span>
          <div>
            <strong>{{ account.name }}</strong>
            <span>{{ account.institution_name }} ···{{ account.mask }}</span>
          </div>
        </div>
        <div class="right">
          <span class="balance money">{{ formatDollars(account.balance) }}</span>
          <span class="status" :data-status="account.sync_status">{{ account.sync_status }}</span>
        </div>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-4);
  padding: var(--space-3) 0;
}

.left {
  display: flex;
  gap: var(--space-3);
  align-items: center;
  min-width: 0;
}

.tile {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-sm);
  background: var(--bg-soft);
  color: var(--text-muted);
  font-size: 0.75rem;
  font-weight: 700;
}

.left div {
  display: grid;
  gap: 0.1rem;
  min-width: 0;
}

.left strong {
  font-size: 0.875rem;
  font-weight: 500;
}

.left span {
  color: var(--text-muted);
  font-size: 0.75rem;
}

.right {
  display: grid;
  gap: 0.15rem;
  text-align: right;
}

.balance {
  color: var(--text);
  font-size: 0.875rem;
  font-weight: 600;
}

.status {
  font-size: 0.72rem;
  text-transform: capitalize;
}

.status[data-status='healthy'] {
  color: var(--accent-text);
}

.status[data-status='error'] {
  color: var(--danger);
}

.status[data-status='pending'] {
  color: #fcd34d;
}
</style>
