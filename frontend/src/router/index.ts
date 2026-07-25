import { createRouter, createWebHistory } from 'vue-router'
import ActivityView from '../views/ActivityView.vue'
import OverviewView from '../views/OverviewView.vue'
import RulesView from '../views/RulesView.vue'
import TransactionReviewView from '../views/TransactionReviewView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'overview', component: OverviewView, meta: { title: 'Overview' } },
    { path: '/activity', name: 'activity', component: ActivityView, meta: { title: 'Activity' } },
    {
      path: '/review',
      name: 'review',
      component: TransactionReviewView,
      meta: { title: 'Review' },
    },
    { path: '/rules', name: 'rules', component: RulesView, meta: { title: 'Rules' } },
  ],
  scrollBehavior() {
    return { top: 0 }
  },
})

router.afterEach((to) => {
  const title = typeof to.meta.title === 'string' ? to.meta.title : 'ClearSpend'
  document.title = `${title} · ClearSpend`
})

export default router
