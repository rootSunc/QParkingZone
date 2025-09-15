import {createRouter, createWebHashHistory} from 'vue-router'
import ZonesListView from '@/views/ZonesListView.vue'
import ZoneDetailView from '@/views/ZoneDetailView.vue'

export default createRouter({
  history: createWebHashHistory(),
  routes: [
    {path: '/', name: 'zones', component: ZonesListView},
    {
      path: '/zones/:id',
      name: 'zone-detail',
      component: ZoneDetailView,
      props: true,
    },
  ],
})
