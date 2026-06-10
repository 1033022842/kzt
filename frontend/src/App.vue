<script setup>
</script>

<template>
  <el-config-provider>
    <router-view :key="$route.fullPath" />
  </el-config-provider>
</template>

<style>
#app {
  min-height: 100vh;
}
</style>
