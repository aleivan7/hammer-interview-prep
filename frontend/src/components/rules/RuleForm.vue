<script setup lang="ts">
import { reactive, watch } from 'vue'
import type { Account } from '../../types/account'
import type { Bucket } from '../../types/bucket'
import type { CategorizationRule } from '../../types/rule'
import { dollarsInputToCents } from '../../utils/money'

const props = defineProps<{
  accounts: Account[]
  rule: CategorizationRule | null
  saving: boolean
}>()

const emit = defineEmits<{
  cancel: []
  submit: [
    payload: {
      name: string
      merchant_contains: string
      account_id: number | null
      amount_cents_min: number | null
      amount_cents_max: number | null
      target_bucket: Bucket
      target_subcategory: string | null
      priority: number
      enabled: boolean
      auto_review: boolean
    },
  ]
}>()

const form = reactive({
  name: '',
  merchant_contains: '',
  account_id: '',
  amount_min: '',
  amount_max: '',
  target_bucket: 'want' as Bucket,
  target_subcategory: '',
  priority: 10,
  enabled: true,
  auto_review: true,
})

watch(
  () => props.rule,
  (rule) => {
    if (rule) {
      form.name = rule.name
      form.merchant_contains = rule.merchant_contains
      form.account_id = rule.account_id ? String(rule.account_id) : ''
      form.amount_min =
        rule.amount_cents_min == null ? '' : (rule.amount_cents_min / 100).toFixed(2)
      form.amount_max =
        rule.amount_cents_max == null ? '' : (rule.amount_cents_max / 100).toFixed(2)
      form.target_bucket = rule.target_bucket
      form.target_subcategory = rule.target_subcategory ?? ''
      form.priority = rule.priority
      form.enabled = rule.enabled
      form.auto_review = rule.auto_review
    } else {
      form.name = ''
      form.merchant_contains = ''
      form.account_id = ''
      form.amount_min = ''
      form.amount_max = ''
      form.target_bucket = 'want'
      form.target_subcategory = ''
      form.priority = 10
      form.enabled = true
      form.auto_review = true
    }
  },
  { immediate: true },
)

function parseOptionalCents(value: string): number | null {
  if (!value.trim()) {
    return null
  }

  return dollarsInputToCents(value)
}

function onSubmit(): void {
  const min = parseOptionalCents(form.amount_min)
  const max = parseOptionalCents(form.amount_max)

  emit('submit', {
    name: form.name.trim(),
    merchant_contains: form.merchant_contains.trim(),
    account_id: form.account_id ? Number(form.account_id) : null,
    amount_cents_min: min,
    amount_cents_max: max,
    target_bucket: form.target_bucket,
    target_subcategory: form.target_subcategory.trim() || null,
    priority: Number(form.priority),
    enabled: form.enabled,
    auto_review: form.auto_review,
  })
}
</script>

<template>
  <form class="form panel" @submit.prevent="onSubmit">
    <header class="panel-header">
      <h2>{{ rule ? 'Edit rule' : 'New rule' }}</h2>
      <button type="button" class="btn btn-ghost" @click="emit('cancel')">Close</button>
    </header>

    <label>
      Name
      <input v-model="form.name" class="field" required maxlength="120" />
    </label>

    <label>
      Merchant contains
      <input v-model="form.merchant_contains" class="field" required maxlength="120" />
    </label>

    <div class="row">
      <label>
        Account (optional)
        <select v-model="form.account_id" class="field">
          <option value="">Any account</option>
          <option v-for="account in accounts" :key="account.id" :value="String(account.id)">
            {{ account.name }}
          </option>
        </select>
      </label>
      <label>
        Target bucket
        <select v-model="form.target_bucket" class="field">
          <option value="need">Needs</option>
          <option value="want">Wants</option>
          <option value="savings">Savings</option>
        </select>
      </label>
    </div>

    <div class="row">
      <label>
        Min amount
        <input v-model="form.amount_min" class="field" inputmode="decimal" placeholder="optional" />
      </label>
      <label>
        Max amount
        <input v-model="form.amount_max" class="field" inputmode="decimal" placeholder="optional" />
      </label>
    </div>

    <div class="row">
      <label>
        Subcategory
        <input v-model="form.target_subcategory" class="field" maxlength="100" />
      </label>
      <label>
        Priority
        <input
          v-model.number="form.priority"
          class="field"
          type="number"
          min="1"
          max="1000"
          required
        />
      </label>
    </div>

    <label class="check">
      <input v-model="form.enabled" type="checkbox" />
      Enabled
    </label>
    <label class="check">
      <input v-model="form.auto_review" type="checkbox" />
      Auto-review on Smart Review
    </label>

    <footer>
      <button type="button" class="btn btn-ghost" @click="emit('cancel')">Cancel</button>
      <button type="submit" class="btn btn-primary" :disabled="saving">
        {{ saving ? 'Saving…' : 'Save rule' }}
      </button>
    </footer>
  </form>
</template>

<style scoped>
.form {
  gap: var(--space-4);
}

header,
footer,
.row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
}

.row > label {
  flex: 1;
}

label {
  display: grid;
  gap: 0.3rem;
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.check {
  grid-template-columns: auto 1fr;
  align-items: center;
  color: var(--text);
}

footer {
  justify-content: end;
}
</style>
