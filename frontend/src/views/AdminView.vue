<template>
  <v-container class="pa-0">
    <v-row>
      <v-col cols="12">
        <v-card elevation="2" class="pa-6">
          <v-card-title class="text-h5 font-weight-bold">Admin irányítópult</v-card-title>
          <v-card-subtitle>Üdvözlünk! Innen kezelheted a felhasználókat, gépeket és jogosultságokat.</v-card-subtitle>
          <v-card-text class="mt-4">
            <v-row>
              <v-col cols="12" md="4">
                <v-card variant="tonal" color="primary" class="pa-4">
                  <div class="text-subtitle-1 font-weight-medium">Felhasználók</div>
                  <div class="text-caption text-medium-emphasis">Kezelés, jogosultságok</div>
                </v-card>
              </v-col>
              <v-col cols="12" md="4">
                <v-card variant="tonal" color="primary" class="pa-4">
                  <div class="text-subtitle-1 font-weight-medium">Gépek</div>
                  <div class="text-caption text-medium-emphasis">Nyilvántartás, állapot</div>
                </v-card>
              </v-col>
              <v-col cols="12" md="4">
                <v-card variant="tonal" color="primary" class="pa-4">
                  <div class="text-subtitle-1 font-weight-medium">Riportok</div>
                  <div class="text-caption text-medium-emphasis">Statisztikák és kimutatások</div>
                </v-card>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>
