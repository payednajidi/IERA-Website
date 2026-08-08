<script setup>
import { useRoute } from 'vue-router'
import Sidebar from '../components/Sidebar.vue'
import HeaderBar from '../components/HeaderBar.vue'

const route = useRoute()
</script>

<template>

<div class="layout">

  <Sidebar />

  <div class="main">

    <HeaderBar />

    <div class="content" :class="{ 'content-fullscreen': route.meta?.fullscreen }">
      <router-view />
    </div>

  </div>

</div>

</template>

<style scoped>

.layout{
  display:flex;
}

.main{
  flex:1;
  display:flex;
  flex-direction:column;
  height:100vh;
  min-width: 0;
}

.content{
  flex:1;
  padding:30px;
  background:#f5f6f8;
  overflow:auto;
  min-width: 0;
}

.content-fullscreen{
  padding:0;
  overflow:hidden;
}

</style>
