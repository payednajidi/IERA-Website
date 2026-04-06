<script setup>
import Sidebar from '../components/Sidebar.vue'
import HeaderBar from '../components/HeaderBar.vue'
</script>

<template>

<div class="layout">

  <Sidebar />

  <div class="main">

    <HeaderBar />

    <div class="content">
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

</style>
