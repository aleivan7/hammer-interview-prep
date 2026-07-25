import { createRouter, createWebHistory, type RouterHistory, type RouteRecordRaw } from 'vue-router'
import { useDemoUser } from '../composables/useDemoUser'
import { getSelectedDemoUserId } from '../session/demoUserSession'
import ActivityView from '../views/ActivityView.vue'
import LoginView from '../views/LoginView.vue'
import OverviewView from '../views/OverviewView.vue'
import ProfileView from '../views/ProfileView.vue'
import RulesView from '../views/RulesView.vue'
import TransactionReviewView from '../views/TransactionReviewView.vue'

export const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { title: 'Choose a demo profile', public: true },
  },
  {
    path: '/',
    name: 'overview',
    component: OverviewView,
    meta: { title: 'Overview', requiresDemoUser: true },
  },
  {
    path: '/activity',
    name: 'activity',
    component: ActivityView,
    meta: { title: 'Activity', requiresDemoUser: true },
  },
  {
    path: '/review',
    name: 'review',
    component: TransactionReviewView,
    meta: { title: 'Review', requiresDemoUser: true },
  },
  {
    path: '/rules',
    name: 'rules',
    component: RulesView,
    meta: { title: 'Rules', requiresDemoUser: true },
  },
  {
    path: '/profile',
    name: 'profile',
    component: ProfileView,
    meta: { title: 'Profile', requiresDemoUser: true },
  },
]

export function createAppRouter(history: RouterHistory = createWebHistory()) {
  const router = createRouter({
    history,
    routes,
    scrollBehavior() {
      return { top: 0 }
    },
  })

  router.beforeEach(async (to) => {
    const isPublic = to.meta.public === true
    const requiresDemoUser = to.meta.requiresDemoUser === true
    const selectedId = getSelectedDemoUserId()

    if (isPublic) {
      return true
    }

    if (!requiresDemoUser) {
      return true
    }

    if (selectedId === null) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }

    const { ensureProfile, switchDemoUser } = useDemoUser()

    try {
      const profile = await ensureProfile()
      if (!profile) {
        switchDemoUser()
        return { name: 'login', query: { redirect: to.fullPath } }
      }
    } catch {
      return { name: 'login', query: { redirect: to.fullPath, error: 'profile' } }
    }

    return true
  })

  router.afterEach((to) => {
    const title = typeof to.meta.title === 'string' ? to.meta.title : 'ClearSpend'
    document.title = `${title} · ClearSpend`
  })

  return router
}

const router = createAppRouter()

export default router
