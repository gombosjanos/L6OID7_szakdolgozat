import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '../views/LoginView.vue'
import MainLayout from '../layouts/MainLayout.vue'
import AdminView from '../views/AdminView.vue'
import AdminCustomersView from '../views/admin/CustomersView.vue'
import AdminMachinesView from '../views/admin/MachinesView.vue'
import AdminWorkordersView from '../views/admin/WorkordersView.vue'
import AdminWorkorderDetail from '../views/admin/WorkorderDetail.vue'
import AdminWorkorderPrint from '../views/admin/WorkorderPrint.vue'
import AdminOfferPrint from '../views/admin/OfferPrint.vue'
import AdminPartsView from '../views/admin/PartsView.vue'

import RegisterView from '../views/RegisterView.vue'
import ResetPasswordView from '../views/ResetPasswordView.vue'
import UgyfelView from '../views/UgyfelView.vue'
import UgyfelWorkorderDetail from '../views/ugyfel/WorkorderDetail.vue'
import SzereloView from '../views/SzereloView.vue'
import AdminWorkorderCreate from '../views/admin/WorkorderCreate.vue'
import AdminWorkorderEdit from '../views/admin/WorkorderEdit.vue'
import ProfileView from '../views/ProfileView.vue'

const routes = [
  { path: '/login', component: LoginView },
  { path: '/register', component: RegisterView },
  { path: '/reset-password', component: ResetPasswordView },
  {
    path: '/',
    component: MainLayout,
    children: [
      { path: 'admin', component: AdminView },
      { path: 'admin/Ugyfelek', component: AdminCustomersView },
      { path: 'admin/gepek', component: AdminMachinesView },
      { path: 'admin/munkalapok', component: AdminWorkordersView },
      { path: 'admin/munkalapok/uj', component: AdminWorkorderCreate },
      { path: 'admin/munkalapok/:id', name: 'AdminWorkorderDetail', component: AdminWorkorderDetail, props: true },
      { path: 'admin/munkalapok/:id/szerkesztes', name: 'AdminWorkorderEdit', component: AdminWorkorderEdit, props: true },
      { path: 'admin/munkalapok/:id/nyomtat', name: 'AdminWorkorderPrint', component: AdminWorkorderPrint, props: true },
      { path: 'admin/munkalapok/:id/ajanlat-nyomtat', name: 'AdminOfferPrint', component: AdminOfferPrint, props: true },
      { path: 'admin/alkatreszek', component: AdminPartsView },
      { path: 'Ugyfel', component: UgyfelView },
      { path: 'Ugyfel/munkalapok/:id', name: 'UgyfelWorkorderDetail', component: UgyfelWorkorderDetail, props: true },
      { path: 'szerelo', component: SzereloView },
      { path: 'profile', component: ProfileView }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')
  const user = JSON.parse(localStorage.getItem('user') || 'null')
  const publicPages = ['/login', '/register', '/reset-password']
  const authRequired = !publicPages.includes(to.path)

  if (authRequired && !token) {
    next('/login')
    return
  }
  if (to.path.startsWith('/admin')) {
    const role = user?.jogosultsag
    if (role === 'admin') return next()
    if (role === 'szerelo') {
      const allowed = (
        to.path.startsWith('/admin/munkalapok') ||
        to.path.startsWith('/admin/gepek') ||
        to.path.startsWith('/admin/alkatreszek')
      )
      return allowed ? next() : next('/szerelo')
    }
    return next('/Ugyfel')
  }

  next()
})

export default router
