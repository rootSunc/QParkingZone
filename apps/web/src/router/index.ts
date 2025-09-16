import {createRouter, createWebHashHistory} from 'vue-router'

export default createRouter({
  history: createWebHashHistory(),
  routes: [
    {path: '/', name: 'zones', component: () => import('@/views/ZonesListView.vue')},
    {
      path: '/zones/:id',
      name: 'zone-detail',
      component: () => import('@/views/ZoneDetailView.vue'),
      props: true,
    },
  ],
})
