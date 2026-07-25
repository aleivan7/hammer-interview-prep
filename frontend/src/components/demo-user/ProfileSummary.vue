<script setup lang="ts">
import type { DemoProfile } from '../../types/demoUser'

defineProps<{
  profile: DemoProfile
}>()
</script>

<template>
  <section class="summary" aria-label="Profile summary">
    <div class="identity panel">
      <span class="avatar" aria-hidden="true">{{ profile.avatar_initials }}</span>
      <div>
        <h2>{{ profile.name }}</h2>
        <p class="email">{{ profile.email }}</p>
        <p class="pill">{{ profile.persona_label }}</p>
      </div>
    </div>

    <p class="description panel">{{ profile.description }}</p>

    <div class="stats">
      <article class="panel stat">
        <p class="label">Member since</p>
        <p class="value">{{ profile.member_since }}</p>
      </article>
      <article class="panel stat">
        <p class="label">Monthly income</p>
        <p class="value money">${{ profile.monthly_income }}</p>
      </article>
      <article class="panel stat">
        <p class="label">Total balance</p>
        <p class="value money">${{ profile.total_balance }}</p>
      </article>
      <article class="panel stat">
        <p class="label">Connected accounts</p>
        <p class="value">{{ profile.account_count }}</p>
      </article>
    </div>

    <section v-if="profile.plan" class="panel plan" aria-label="50/30/20 plan">
      <h3>50/30/20 plan</h3>
      <dl>
        <div>
          <dt>Needs</dt>
          <dd>{{ profile.plan.needs_percent }}%</dd>
        </div>
        <div>
          <dt>Wants</dt>
          <dd>{{ profile.plan.wants_percent }}%</dd>
        </div>
        <div>
          <dt>Savings</dt>
          <dd>{{ profile.plan.savings_percent }}%</dd>
        </div>
        <div>
          <dt>Safety buffer</dt>
          <dd>${{ profile.plan.safety_buffer }}</dd>
        </div>
      </dl>
    </section>

    <section class="panel accounts" aria-label="Connected accounts">
      <h3>Connected accounts</h3>
      <ul>
        <li v-for="account in profile.accounts" :key="account.id">
          <div>
            <p class="account-name">{{ account.name }}</p>
            <p class="account-meta">{{ account.institution_name }} · {{ account.type }}</p>
          </div>
          <p class="money">${{ account.balance }}</p>
        </li>
      </ul>
    </section>
  </section>
</template>

<style scoped>
.summary {
  display: grid;
  gap: var(--space-4);
}

.identity {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-5);
}

.avatar {
  display: grid;
  place-items: center;
  width: 3.5rem;
  height: 3.5rem;
  border-radius: var(--radius-pill);
  background: var(--accent-soft);
  color: var(--accent-text);
  font-weight: 700;
}

.identity h2 {
  margin: 0;
  font-size: 1.4rem;
}

.email {
  margin: 0.2rem 0 0.55rem;
  color: var(--text-muted);
}

.description {
  margin: 0;
  padding: var(--space-5);
  color: var(--text-muted);
  line-height: 1.5;
}

.stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-3);
}

.stat {
  padding: var(--space-4);
  gap: var(--space-2);
}

.label {
  margin: 0;
  color: var(--text-dim);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.value {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
}

.plan,
.accounts {
  padding: var(--space-5);
  gap: var(--space-4);
}

.plan h3,
.accounts h3 {
  margin: 0;
  font-size: 1rem;
}

.plan dl {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-3);
  margin: 0;
}

.plan dt {
  color: var(--text-dim);
  font-size: 0.75rem;
}

.plan dd {
  margin: 0.2rem 0 0;
  font-weight: 600;
}

.accounts ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: var(--space-3);
}

.accounts li {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
  padding-top: var(--space-3);
  border-top: 1px solid var(--border);
}

.accounts li:first-child {
  padding-top: 0;
  border-top: 0;
}

.account-name {
  margin: 0;
  font-weight: 600;
}

.account-meta {
  margin: 0.15rem 0 0;
  color: var(--text-dim);
  font-size: 0.8rem;
  text-transform: capitalize;
}

@media (max-width: 900px) {
  .stats,
  .plan dl {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .stats,
  .plan dl {
    grid-template-columns: 1fr;
  }
}
</style>
