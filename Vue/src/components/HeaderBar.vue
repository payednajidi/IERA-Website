<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const pageTitle = computed(() => {
  if (route.path.startsWith('/era-summary')) return 'ERA Initial Summary'
  if (route.path.startsWith('/era-environmental-factors')) return 'ERA Environmental Factors'
  if (route.path.startsWith('/era-vibration')) return 'ERA Vibration'
  if (route.path.startsWith('/era-repetitive-motion')) return 'ERA Repetitive Motion'
  if (route.path.startsWith('/era-forceful-exertion')) return 'ERA Forceful Exertion'
  if (route.path.startsWith('/era-checklist')) return 'ERA Checklist'
  if (route.path.startsWith('/era-form')) return 'ERA Assessment Form'
  return 'Ergonomics Risk Assessment System'
})
</script>

<template>

<div class="header">

  <div class="left">
    <div class="system-title">Ergonomics Risk Assessment System</div>
    <div class="breadcrumb">
      <span class="breadcrumb-sep">›</span>
      <span class="page-name">{{ pageTitle }}</span>
    </div>
  </div>

  <div class="user">
    👤 Logged in as Admin
  </div>

</div>

</template>

<style scoped>

.header{
  height:60px;
  background:white;
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:0 24px;
  border-bottom:1px solid #ddd;
  flex-shrink:0;
}

.left{
  display:flex;
  align-items:center;
  gap:8px;
}

.system-title{
  font-weight:700;
  font-size:14px;
  color:#2f3e4d;
}

.breadcrumb{
  display:flex;
  align-items:center;
  gap:6px;
}

.breadcrumb-sep{
  color:#aaa;
  font-size:16px;
}

.page-name{
  font-size:13.5px;
  color:#555;
  font-weight:500;
}

.user{
  font-size:13px;
  color:#555;
}

</style>
