<script setup lang="ts">
import { computed } from 'vue'
import type { Account } from '../../types/account'
import type { Transaction } from '../../types/transaction'
import { BUCKET_LABELS } from '../../types/bucket'
import { formatCents } from '../../utils/money'
import {
  aggregateBucketSpend,
  formatSigned,
  summarizeCashFlow,
} from '../../utils/transactions'
import AppIcon from '../ui/AppIcon.vue'
import SkeletonBlock from '../ui/SkeletonBlock.vue'

const props = defineProps<{
  transactions: Transaction[]
  accounts: Account[]
  loading: boolean
}>()

const summary = computed(() => summarizeCashFlow(props.transactions))
const bucketSpend = computed(() => aggregateBucketSpend(props.transactions))
</script>

<template>
  <aside class="rail">
    <template v-if="loading">
      <SkeletonBlock height="8rem" radius="var(--radius)" />
      <SkeletonBlock height="10rem" radius="var(--radius)" />
      <SkeletonBlock height="10rem" radius="var(--radius)" />
    </template>

    <template v-else>
      <section class="panel">
        <header class="panel-header">
          <div>
            <h2>Summary</h2>
            <p>Across current filters</p>
          </div>
        </header>
        <p class="net money" :class="summary.netCents >= 0 ? 'credit' : 'debit'">
          {{ formatSigned(summary.netCents) }}
        </p>
        <dl class="split">
          <div>
            <dt>Income</dt>
            <dd class="money credit">{{ formatSigned(summary.incomeCents) }}</dd>
          </div>
          <div>
            <dt>Expenses</dt>
            <dd class="money debit">−{{ formatCents(summary.expenseCents) }}</dd>
          </div>
        </dl>
      </section>

      <section class="panel">
        <header class="panel-header">
          <h2>Spending by category</h2>
        </header>
        <ul class="spend">
          <li v-for="row in bucketSpend" :key="row.bucket">
            <div class="spend-head">
              <span class="tile" :data-bucket="row.bucket">
                <AppIcon
                  :name="row.bucket === 'need' ? 'bank' : row.bucket === 'want' ? 'star' : 'trending'"
                  :size="14"
                />
              </span>
              <span>{{ BUCKET_LABELS[row.bucket] }}</span>
              <span class="money">{{ formatCents(row.cents) }}</span>
            </div>
            <div class="meter" aria-hidden="true">
              <div
                class="meter-fill"
                :data-bucket="row.bucket"
                :style="{ width: `${row.percent}%` }"
              />
            </div>
            <p class="pct">{{ row.percent }}%</p>
          </li>
        </ul>
      </section>

      <section class="panel">
        <header class="panel-header">
          <h2>Accounts</h2>
        </header>
        <ul class="panel-rows accounts">
          <li v-for="account in accounts" :key="account.id" class="account">
            <span class="tile" aria-hidden="true">{{
              account.logo_key.slice(0, 1).toUpperCase()
            }}</span>
            <div>
              <strong>{{ account.name }}</strong>
              <span>···{{ account.mask }}</span>
            </div>
            <div class="right">
              <span class="money">{{ formatCents(account.balance_cents) }}</span>
              <span class="dot" :data-status="account.sync_status" aria-hidden="true" />
            </div>
          </li>
        </ul>
      </section>
    </template>
  </aside>
</template>

<style scoped>
.rail {
  display: grid;
  gap: var(--space-4);
  align-content: start;
}

.net {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.split {
  display: grid;
  gap: var(--space-2);
  margin: 0;
}

.split div {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
}

.split dt {
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.split dd {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
}

.spend {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: var(--space-4);
}

.spend-head {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: var(--space-2);
  align-items: center;
  margin-bottom: var(--space-2);
  font-size: 0.8125rem;
}

.tile {
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
  background: var(--bg-soft);
  color: var(--text-muted);
  font-size: 0.72rem;
  font-weight: 700;
}

.tile[data-bucket='need'] {
  background: var(--need-soft);
  color: #93c5fd;
}

.tile[data-bucket='want'] {
  background: var(--want-soft);
  color: #fcd34d;
}

.tile[data-bucket='savings'] {
  background: var(--savings-soft);
  color: var(--accent-text);
}

.meter-fill[data-bucket='need'] {
  background: var(--need);
}

.meter-fill[data-bucket='want'] {
  background: var(--want);
}

.meter-fill[data-bucket='savings'] {
  background: var(--savings);
}

.pct {
  margin: 0.25rem 0 0;
  color: var(--text-dim);
  font-size: 0.72rem;
}

.accounts {
  gap: 0;
}

.account {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: var(--space-3);
  align-items: center;
  padding: var(--space-3) 0;
}

.account div {
  display: grid;
  gap: 0.1rem;
  min-width: 0;
}

.account strong {
  font-size: 0.8125rem;
  font-weight: 500;
}

.account span {
  color: var(--text-muted);
  font-size: 0.72rem;
}

.right {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.right .money {
  color: var(--text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.dot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: var(--radius-pill);
  background: var(--text-dim);
}

.dot[data-status='healthy'] {
  background: var(--accent);
}

.dot[data-status='error'] {
  background: var(--danger);
}

.dot[data-status='pending'] {
  background: var(--want);
}
</style>
