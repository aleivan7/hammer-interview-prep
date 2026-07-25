<script setup lang="ts">
import type { Account } from '../../types/account'
import { formatDollars } from '../../utils/money'

defineProps<{
  accounts: Account[]
}>()
</script>

<template>
  <section class="panel">
    <header>
      <h2>Accounts</h2>
      <p>Sync health for the demo persona</p>
    </header>

    <ul>
      <li v-for="account in accounts" :key="account.id">
        <div>
          <strong>{{ account.name }}</strong>
          <span>{{ account.institution_name }} ···{{ account.mask }}</span>
        </div>
        <div class="right">
          <span class="balance">{{ formatDollars(account.balance) }}</span>
          <span class="status" :data-status="account.sync_status">{{ account.sync_status }}</span>
        </div>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.panel {
  display: grid;
  gap: 1rem;
  padding: 1.25rem;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--bg-elevated);
}

header h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.25rem;
}

header p {
  margin: 0.35rem 0 0;
  color: var(--text-muted);
  font-size: 0.92rem;
}

ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.75rem;
}

li {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border);
}

li:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

li div {
  display: grid;
  gap: 0.15rem;
}

li span {
  color: var(--text-muted);
  font-size: 0.85rem;
}

.right {
  text-align: right;
}

.balance {
  color: var(--text) !important;
  font-variant-numeric: tabular-nums;
}

.status {
  text-transform: capitalize;
}

.status[data-status='healthy'] {
  color: var(--savings);
}

.status[data-status='error'] {
  color: var(--danger);
}

.status[data-status='pending'] {
  color: var(--want);
}
</style>
