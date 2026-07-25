<script setup lang="ts">
import { onMounted, shallowRef } from 'vue'
import { useRouter } from 'vue-router'
import ProfileSummary from '../components/demo-user/ProfileSummary.vue'
import ResetDemoDataDialog from '../components/demo-user/ResetDemoDataDialog.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import SkeletonBlock from '../components/ui/SkeletonBlock.vue'
import { useDemoUser } from '../composables/useDemoUser'

const router = useRouter()
const {
  profile,
  loading,
  error,
  ensureProfile,
  switchDemoUser,
  resetCurrentDemoData,
} = useDemoUser()

const showResetDialog = shallowRef(false)
const resetting = shallowRef(false)
const resetError = shallowRef<string | null>(null)
const resetSuccess = shallowRef<string | null>(null)

async function load(): Promise<void> {
  resetSuccess.value = null
  await ensureProfile({ force: true })
}

function onSwitch(): void {
  switchDemoUser()
  void router.push({ name: 'login' })
}

async function onConfirmReset(): Promise<void> {
  resetting.value = true
  resetError.value = null

  try {
    await resetCurrentDemoData()
    showResetDialog.value = false
    resetSuccess.value = 'Demo data restored for this fictional profile.'
  } catch (err) {
    resetError.value = err instanceof Error ? err.message : 'Failed to reset demo data.'
  } finally {
    resetting.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="profile-page">
    <PageHeader
      title="Profile"
      subtitle="This is a fictional demo persona with isolated synthetic financial data."
    >
      <template #actions>
        <button type="button" class="btn btn-ghost" @click="onSwitch">Switch demo user</button>
        <button type="button" class="btn btn-primary" @click="showResetDialog = true">
          Reset demo data
        </button>
      </template>
    </PageHeader>

    <p v-if="resetSuccess" class="success" role="status">{{ resetSuccess }}</p>

    <p v-if="loading && !profile" class="sr-only" role="status">Loading profile</p>

    <div v-if="loading && !profile" class="loading" aria-hidden="true">
      <SkeletonBlock height="8rem" />
      <SkeletonBlock height="12rem" />
    </div>

    <EmptyState
      v-else-if="error && !profile"
      icon="alert"
      title="Couldn’t load profile"
      :body="error"
    >
      <button type="button" class="btn btn-ghost" @click="load">Try again</button>
    </EmptyState>

    <ProfileSummary v-else-if="profile" :profile="profile" />

    <ResetDemoDataDialog
      v-if="showResetDialog"
      :busy="resetting"
      :error="resetError"
      @cancel="showResetDialog = false"
      @confirm="onConfirmReset"
    />
  </div>
</template>

<style scoped>
.profile-page {
  display: grid;
  gap: var(--space-5);
}

.success {
  margin: 0;
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-sm);
  background: var(--accent-soft);
  color: var(--accent-text);
}

.loading {
  display: grid;
  gap: var(--space-4);
}
</style>
