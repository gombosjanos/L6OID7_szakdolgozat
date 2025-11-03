<template>
  <aside class="w-64 bg-white shadow-lg hidden md:flex flex-col">
    <div class="p-6 border-b">
      <h1 class="text-2xl font-bold text-blue-600">Szakdolgozat</h1>
    </div>

    <nav class="flex-1 p-4 space-y-1">
      <RouterLink
        to="/"
        class="block p-2 rounded hover:bg-blue-50 text-gray-700 font-medium"
        active-class="bg-blue-100 text-blue-700"
      >
        🏠 Kezdőlap
      </RouterLink>
      <RouterLink
        to="/dashboard"
        class="block p-2 rounded hover:bg-blue-50 text-gray-700 font-medium"
        active-class="bg-blue-100 text-blue-700"
      >
        📊 Statisztika
      </RouterLink>
      <RouterLink
        to="/users"
        class="block p-2 rounded hover:bg-blue-50 text-gray-700 font-medium"
        active-class="bg-blue-100 text-blue-700"
      >
        👤 Felhasználók
      </RouterLink>
      <RouterLink
        to="/settings"
        class="block p-2 rounded hover:bg-blue-50 text-gray-700 font-medium"
        active-class="bg-blue-100 text-blue-700"
      >
        ⚙️ Beállítások
      </RouterLink>
    </nav>
  </aside>
</template>
<style scoped>
/* Remove global overrides that forced all Vuetify drawers open. */
:root { --sidebar-width: 260px; --app-bar-height: 64px; }

/* Optional: only adjust main content margin when a LEFT drawer is open */
:deep(.v-navigation-drawer:not(.v-navigation-drawer--right):not(.v-navigation-drawer--close)) ~ main.v-main {
  margin-left: var(--sidebar-width, 260px) !important;
}
</style>
