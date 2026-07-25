<script setup lang="ts">
import { onMounted, shallowRef } from 'vue'
import { fetchAccounts } from '../api/accountApi'
import { createRule, deleteRule, fetchRules, updateRule } from '../api/rulesApi'
import RuleForm from '../components/rules/RuleForm.vue'
import RuleList from '../components/rules/RuleList.vue'
import type { Account } from '../types/account'
import type { CategorizationRule } from '../types/rule'

const rules = shallowRef<CategorizationRule[]>([])
const accounts = shallowRef<Account[]>([])
const loading = shallowRef(true)
const saving = shallowRef(false)
const error = shallowRef<string | null>(null)
const showForm = shallowRef(false)
const editing = shallowRef<CategorizationRule | null>(null)

async function load(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    rules.value = await fetchRules()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to load rules.'
  } finally {
    loading.value = false
  }
}

function openCreate(): void {
  editing.value = null
  showForm.value = true
}

function openEdit(rule: CategorizationRule): void {
  editing.value = rule
  showForm.value = true
}

async function handleSubmit(payload: {
  name: string
  merchant_contains: string
  account_id: number | null
  amount_cents_min: number | null
  amount_cents_max: number | null
  target_bucket: CategorizationRule['target_bucket']
  target_subcategory: string | null
  priority: number
  enabled: boolean
  auto_review: boolean
}): Promise<void> {
  saving.value = true
  error.value = null

  try {
    if (editing.value) {
      await updateRule(editing.value.id, payload)
    } else {
      await createRule(payload)
    }

    showForm.value = false
    editing.value = null
    await load()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to save rule.'
  } finally {
    saving.value = false
  }
}

async function handleRemove(rule: CategorizationRule): Promise<void> {
  if (!window.confirm(`Delete rule “${rule.name}”?`)) {
    return
  }

  error.value = null

  try {
    await deleteRule(rule.id)
    await load()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to delete rule.'
  }
}

onMounted(async () => {
  try {
    accounts.value = await fetchAccounts()
  } catch {
    accounts.value = []
  }

  await load()
})
</script>

<template>
  <div class="rules">
    <div class="toolbar">
      <p>
        Practical merchant rules run before heuristics during Smart Review. Lower priority numbers
        win.
      </p>
      <button type="button" class="primary" @click="openCreate">New rule</button>
    </div>

    <p v-if="loading" class="status" role="status">Loading rules…</p>
    <p v-else-if="error" class="error" role="alert">{{ error }}</p>

    <RuleForm
      v-if="showForm"
      :accounts="accounts"
      :rule="editing"
      :saving="saving"
      @cancel="showForm = false"
      @submit="handleSubmit"
    />

    <RuleList
      v-if="!loading && !showForm"
      :rules="rules"
      @edit="openEdit"
      @remove="handleRemove"
    />

    <p v-if="!loading && !showForm && !rules.length" class="empty">No rules yet.</p>
  </div>
</template>

<style scoped>
.rules {
  display: grid;
  gap: 1rem;
  max-width: 56rem;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: start;
}

.toolbar p {
  margin: 0;
  color: var(--text-muted);
  max-width: 36rem;
}

.primary {
  padding: 0.6rem 1rem;
  border: 0;
  border-radius: var(--radius-sm);
  background: var(--need);
  color: #071018;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.status,
.error,
.empty {
  margin: 0;
}

.error {
  color: var(--danger);
}

.empty {
  color: var(--text-muted);
}
</style>
