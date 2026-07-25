<script setup lang="ts">
import { onMounted, shallowRef } from 'vue'
import { useRouter } from 'vue-router'
import { fetchDemoUsers } from '../api/demoUserApi'
import PersonaCard from '../components/demo-user/PersonaCard.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import SkeletonBlock from '../components/ui/SkeletonBlock.vue'
import { useDemoUser } from '../composables/useDemoUser'
import type { DemoPersona } from '../types/demoUser'

const router = useRouter()
const { selectDemoUser, ensureProfile } = useDemoUser()

const personas = shallowRef<DemoPersona[]>([])
const loading = shallowRef(true)
const selectingId = shallowRef<number | null>(null)
const error = shallowRef<string | null>(null)

async function load(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    personas.value = await fetchDemoUsers()
  } catch (err) {
    personas.value = []
    error.value = err instanceof Error ? err.message : 'Failed to load demo profiles.'
  } finally {
    loading.value = false
  }
}

async function onSelect(id: number): Promise<void> {
  selectingId.value = id
  error.value = null

  try {
    selectDemoUser(id)
    await ensureProfile({ force: true })
    await router.replace({ name: 'overview' })
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to select demo profile.'
  } finally {
    selectingId.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="login-page">
    <div class="hero">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true">C</span>
        <span class="brand-name">ClearSpend</span>
      </div>
      <h1>Choose a demo profile</h1>
      <p>
        Explore how ClearSpend adapts to different financial situations. All information shown is
        fictional demo data.
      </p>
      <p class="note">No password required — this is a seeded interview/demo environment.</p>
    </div>

    <p v-if="loading" class="sr-only" role="status">Loading demo profiles</p>

    <div v-if="loading" class="grid" aria-hidden="true">
      <SkeletonBlock v-for="index in 3" :key="index" height="22rem" />
    </div>

    <EmptyState
      v-else-if="error"
      icon="alert"
      title="Couldn’t load demo profiles"
      :body="error"
    >
      <button type="button" class="btn btn-ghost" @click="load">Try again</button>
    </EmptyState>

    <EmptyState
      v-else-if="personas.length === 0"
      icon="bank"
      title="No demo profiles available"
      body="Seed the database to create the three ClearSpend personas."
    />

    <div v-else class="grid">
      <PersonaCard
        v-for="persona in personas"
        :key="persona.id"
        :persona="persona"
        :busy="selectingId === persona.id"
        :selected="selectingId === persona.id"
        @select="onSelect"
      />
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  padding: var(--space-8) var(--space-6);
  max-width: 72rem;
  margin: 0 auto;
}

.hero {
  display: grid;
  gap: var(--space-3);
  margin-bottom: var(--space-8);
  max-width: 40rem;
}

.brand {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.brand-mark {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.55rem;
  background: linear-gradient(145deg, var(--accent), #0f766e);
  color: var(--accent-ink);
  font-weight: 700;
}

.brand-name {
  font-size: 1.05rem;
  font-weight: 600;
}

.hero h1 {
  margin: 0;
  font-size: clamp(1.8rem, 3vw, 2.4rem);
  letter-spacing: -0.03em;
}

.hero p {
  margin: 0;
  color: var(--text-muted);
  line-height: 1.5;
}

.note {
  color: var(--text-dim) !important;
  font-size: 0.9rem;
}

.grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-4);
}

@media (max-width: 980px) {
  .grid {
    grid-template-columns: 1fr;
  }

  .login-page {
    padding: var(--space-5) var(--space-4);
  }
}
</style>
