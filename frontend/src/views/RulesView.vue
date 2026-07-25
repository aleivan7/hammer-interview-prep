<script setup lang="ts">
import { onMounted, shallowRef } from 'vue'
import { fetchAccounts } from '../api/accountApi'
import { createRule, deleteRule, fetchRules, updateRule } from '../api/rulesApi'
import RuleForm from '../components/rules/RuleForm.vue'
import RuleList from '../components/rules/RuleList.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
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
    <PageHeader
      title="Rules"
      subtitle="Merchant rules run before heuristics during Smart Review. Lower priority numbers win."
    >
      <template #actions>
        <button type="button" class="btn btn-primary" @click="openCreate">
          <AppIcon name="plus" :size="16" />
          New rule
        </button>
      </template>
    </PageHeader>

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
      v-if="!loading && !showForm && rules.length"
      :rules="rules"
      @edit="openEdit"
      @remove="handleRemove"
    />

    <EmptyState
      v-if="!loading && !showForm && !rules.length"
      icon="tag"
      title="No rules yet"
      body="Create a merchant rule to auto-categorize matching transactions."
    >
      <button type="button" class="btn btn-primary" @click="openCreate">New rule</button>
    </EmptyState>
  </div>
</template>

<style scoped>
.rules {
  display: grid;
  gap: var(--space-5);
}

.status,
.error {
  margin: 0;
}

.error {
  color: var(--danger);
}
</style>
