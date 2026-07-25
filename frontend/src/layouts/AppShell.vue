<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const links = [
  { to: '/', label: 'Overview', exact: true },
  { to: '/activity', label: 'Activity', exact: false },
  { to: '/review', label: 'Review', exact: false },
  { to: '/rules', label: 'Rules', exact: false },
] as const

const pageTitle = computed(() =>
  typeof route.meta.title === 'string' ? route.meta.title : 'ClearSpend',
)

function isActive(path: string, exact: boolean): boolean {
  if (exact) {
    return route.path === path
  }

  return route.path === path || route.path.startsWith(`${path}/`)
}
</script>

<template>
  <div class="shell">
    <aside class="sidebar" aria-label="Primary">
      <div class="brand">
        <p class="brand-mark">ClearSpend</p>
        <p class="brand-sub">Dollarwise-inspired POC</p>
      </div>

      <nav class="nav">
        <RouterLink
          v-for="link in links"
          :key="link.to"
          :to="link.to"
          class="nav-link"
          :class="{ active: isActive(link.to, link.exact) }"
        >
          {{ link.label }}
        </RouterLink>
      </nav>

      <p class="persona">Demo persona · Jordan Lee</p>
    </aside>

    <div class="main-column">
      <header class="topbar">
        <div>
          <p class="eyebrow">ClearSpend</p>
          <h1>{{ pageTitle }}</h1>
        </div>
      </header>

      <nav class="mobile-nav" aria-label="Mobile">
        <RouterLink
          v-for="link in links"
          :key="`m-${link.to}`"
          :to="link.to"
          class="mobile-link"
          :class="{ active: isActive(link.to, link.exact) }"
        >
          {{ link.label }}
        </RouterLink>
      </nav>

      <main class="content">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
.shell {
  display: grid;
  grid-template-columns: 16.5rem minmax(0, 1fr);
  min-height: 100vh;
}

.sidebar {
  position: sticky;
  top: 0;
  display: flex;
  flex-direction: column;
  gap: 2rem;
  height: 100vh;
  padding: 1.75rem 1.25rem;
  border-right: 1px solid var(--border);
  background: linear-gradient(180deg, rgba(22, 32, 51, 0.96), rgba(11, 18, 32, 0.98));
}

.brand-mark {
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.65rem;
  font-weight: 700;
  letter-spacing: -0.03em;
}

.brand-sub {
  margin: 0.35rem 0 0;
  color: var(--text-muted);
  font-size: 0.85rem;
}

.nav {
  display: grid;
  gap: 0.35rem;
}

.nav-link {
  padding: 0.7rem 0.85rem;
  border-radius: var(--radius-sm);
  color: var(--text-muted);
  transition:
    background 160ms ease,
    color 160ms ease,
    transform 160ms ease;
}

.nav-link:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.04);
}

.nav-link.active {
  color: var(--text);
  background: linear-gradient(90deg, var(--need-soft), transparent);
  box-shadow: inset 3px 0 0 var(--need);
}

.persona {
  margin-top: auto;
  color: var(--text-dim);
  font-size: 0.8rem;
}

.main-column {
  min-width: 0;
}

.topbar {
  display: none;
  padding: 1.25rem 1.25rem 0.5rem;
}

.topbar h1 {
  margin: 0.15rem 0 0;
  font-family: var(--font-display);
  font-size: 1.75rem;
  font-weight: 600;
}

.eyebrow {
  margin: 0;
  color: var(--text-dim);
  font-size: 0.75rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.mobile-nav {
  display: none;
  gap: 0.4rem;
  padding: 0.75rem 1rem;
  overflow-x: auto;
  border-bottom: 1px solid var(--border);
  background: rgba(16, 24, 38, 0.9);
  backdrop-filter: blur(8px);
  position: sticky;
  top: 0;
  z-index: 5;
}

.mobile-link {
  flex: 0 0 auto;
  padding: 0.45rem 0.8rem;
  border-radius: 999px;
  border: 1px solid transparent;
  color: var(--text-muted);
  font-size: 0.9rem;
}

.mobile-link.active {
  color: var(--text);
  border-color: var(--border-strong);
  background: var(--bg-soft);
}

.content {
  padding: 1.5rem 1.75rem 3rem;
}

@media (max-width: 900px) {
  .shell {
    grid-template-columns: 1fr;
  }

  .sidebar {
    display: none;
  }

  .topbar,
  .mobile-nav {
    display: flex;
  }
}
</style>
