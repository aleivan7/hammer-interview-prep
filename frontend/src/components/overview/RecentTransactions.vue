<script setup lang="ts">
import type { Transaction } from '../../types/transaction'
import { formatRelativeDay } from '../../utils/dates'
import { formatSigned, isCredit, signedAmountCents } from '../../utils/transactions'
import MerchantAvatar from '../ui/MerchantAvatar.vue'

defineProps<{
  transactions: Transaction[]
}>()
</script>

<template>
  <section class="panel">
    <header class="panel-header">
      <h2>Recent transactions</h2>
      <RouterLink class="link" to="/activity">View all</RouterLink>
    </header>

    <ul v-if="transactions.length" class="panel-rows">
      <li v-for="tx in transactions" :key="tx.id" class="row">
        <div class="left">
          <MerchantAvatar :name="tx.merchant" :size="36" />
          <div>
            <strong>{{ tx.merchant }}</strong>
            <span
              >{{ formatRelativeDay(tx.transaction_date)
              }}{{ tx.account ? ` · ${tx.account.name}` : '' }}</span
            >
          </div>
        </div>
        <span class="amount money" :class="isCredit(tx) ? 'credit' : 'debit'">
          {{ formatSigned(signedAmountCents(tx)) }}
        </span>
      </li>
    </ul>
    <p v-else class="empty">No recent transactions.</p>

    <RouterLink class="footer-link link" to="/activity">View all activity →</RouterLink>
  </section>
</template>

<style scoped>
.row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-4);
  align-items: center;
  min-height: 3.5rem;
  padding: var(--space-3) 0;
}

.left {
  display: flex;
  gap: var(--space-3);
  align-items: center;
  min-width: 0;
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
  font-size: 0.72rem;
}

.amount {
  font-size: 0.875rem;
  font-weight: 600;
}

.empty {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.875rem;
}

.footer-link {
  justify-self: start;
}
</style>
