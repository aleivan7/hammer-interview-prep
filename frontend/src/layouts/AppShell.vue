<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import AppIcon, { type IconName } from '../components/ui/AppIcon.vue'

const route = useRoute()

const links: Array<{ to: string; label: string; exact: boolean; icon: IconName }> = [
  { to: '/', label: 'Overview', exact: true, icon: 'dashboard' },
  { to: '/activity', label: 'Activity', exact: false, icon: 'activity' },
  { to: '/review', label: 'Review', exact: false, icon: 'review' },
  { to: '/rules', label: 'Rules', exact: false, icon: 'rules' },
]

function isActive(path: string, exact: boolean): boolean {
  if (exact) {
    return route.path === path
  }

  return route.path === path || route.path.startsWith(`${path}/`)
}

const routeKey = computed(() => String(route.name ?? route.path))
</script>

<template>
  <div class="shell">
    <aside class="sidebar" aria-label="Primary">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true">C</span>
        <span class="brand-name">ClearSpend</span>
      </div>

      <nav class="nav">
        <RouterLink
          v-for="link in links"
          :key="link.to"
          :to="link.to"
          class="nav-link"
          :class="{ active: isActive(link.to, link.exact) }"
          :aria-current="isActive(link.to, link.exact) ? 'page' : undefined"
        >
          <AppIcon :name="link.icon" :size="18" />
          <span>{{ link.label }}</span>
        </RouterLink>
      </nav>

      <div class="sidebar-footer">
        <div class="premium panel">
          <div class="premium-top">
            <span class="gem">
              <AppIcon name="gem" :size="16" />
            </span>
            <span class="pill pill-accent">Active</span>
          </div>
          <p class="premium-title">Premium</p>
          <p class="premium-sub">All features unlocked</p>
        </div>

        <div class="persona">
          <span class="avatar" aria-hidden="true">JL</span>
          <div>
            <p class="persona-name">Jordan Lee</p>
            <p class="persona-role">Demo persona</p>
          </div>
        </div>
      </div>
    </aside>

    <div class="main-column">
      <header class="topbar">
        <div class="brand mobile-brand">
          <span class="brand-mark" aria-hidden="true">C</span>
          <span class="brand-name">ClearSpend</span>
        </div>
        <span class="avatar" aria-hidden="true">JL</span>
      </header>

      <nav class="mobile-nav" aria-label="Mobile">
        <RouterLink
          v-for="link in links"
          :key="`m-${link.to}`"
          :to="link.to"
          class="mobile-link"
          :class="{ active: isActive(link.to, link.exact) }"
          :aria-current="isActive(link.to, link.exact) ? 'page' : undefined"
        >
          <AppIcon :name="link.icon" :size="16" />
          <span>{{ link.label }}</span>
        </RouterLink>
      </nav>

      <main class="content">
        <RouterView v-slot="{ Component }">
          <Transition name="page-fade" mode="out-in">
            <component :is="Component" :key="routeKey" />
          </Transition>
        </RouterView>
      </main>
    </div>
  </div>
</template>

<style scoped>
.shell {
  display: grid;
  grid-template-columns: 15rem minmax(0, 1fr);
  min-height: 100vh;
}

.sidebar {
  position: sticky;
  top: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-6);
  height: 100vh;
  padding: var(--space-5) var(--space-3);
  border-right: 1px solid var(--border);
  background: var(--bg-deep);
}

.brand {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: 0 var(--space-2);
}

.brand-mark {
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 0.55rem;
  background: linear-gradient(145deg, var(--accent), #0f766e);
  color: var(--accent-ink);
  font-size: 0.85rem;
  font-weight: 700;
}

.brand-name {
  font-size: 1rem;
  font-weight: 600;
  letter-spacing: -0.02em;
}

.nav {
  display: grid;
  gap: var(--space-1);
}

.nav-link {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  min-height: 2.375rem;
  padding: 0.55rem 0.75rem;
  border-radius: var(--radius-sm);
  color: var(--text-muted);
  font-size: 0.84rem;
  font-weight: 500;
  transition:
    background 160ms ease,
    color 160ms ease;
}

.nav-link:hover {
  color: var(--text);
  background: var(--bg-hover);
}

.nav-link.active {
  color: var(--accent-text);
  background: var(--accent-soft);
}

.sidebar-footer {
  margin-top: auto;
  display: grid;
  gap: var(--space-4);
}

.premium {
  gap: var(--space-2);
  padding: var(--space-4);
}

.premium-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.gem {
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
  background: var(--accent-soft);
  color: var(--accent-text);
}

.premium-title {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
}

.premium-sub {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.72rem;
}

.persona {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: 0 var(--space-2);
}

.avatar {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-pill);
  background: var(--bg-soft);
  color: var(--text);
  font-size: 0.72rem;
  font-weight: 600;
}

.persona-name {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
}

.persona-role {
  margin: 0;
  color: var(--text-dim);
  font-size: 0.72rem;
}

.main-column {
  min-width: 0;
}

.topbar,
.mobile-nav {
  display: none;
}

.content {
  padding: var(--space-8) var(--space-8) var(--space-10);
}

.content > * {
  max-width: 80rem;
  margin-inline: auto;
}

@media (max-width: 900px) {
  .shell {
    grid-template-columns: 1fr;
  }

  .sidebar {
    display: none;
  }

  .topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-4) var(--space-4) var(--space-2);
  }

  .mobile-nav {
    display: flex;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-4) var(--space-3);
    overflow-x: auto;
    border-bottom: 1px solid var(--border);
    background: rgba(13, 13, 13, 0.92);
    backdrop-filter: blur(8px);
    position: sticky;
    top: 0;
    z-index: 5;
  }

  .mobile-link {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    flex: 0 0 auto;
    padding: 0.45rem 0.8rem;
    border-radius: var(--radius-pill);
    border: 1px solid transparent;
    color: var(--text-muted);
    font-size: 0.84rem;
  }

  .mobile-link.active {
    color: var(--accent-text);
    border-color: rgba(34, 197, 94, 0.35);
    background: var(--accent-soft);
  }

  .content {
    padding: var(--space-4) var(--space-4) var(--space-8);
  }
}
</style>
