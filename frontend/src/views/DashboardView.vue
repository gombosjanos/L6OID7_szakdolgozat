<template>
  <v-container class="pa-0">
    <v-row class="mb-4">
      <v-col cols="12">
        <div class="text-h5 font-weight-bold">Statisztika</div>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12" md="4">
        <StatCard title="Felhasználók száma" :value="124" />
      </v-col>
      <v-col cols="12" md="4">
        <StatCard title="Aktív munkalapok" :value="12" />
      </v-col>
      <v-col cols="12" md="4">
        <StatCard title="Mai bejelentkezések" :value="31" />
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import StatCard from '../components/StatCard.vue'
</script>

