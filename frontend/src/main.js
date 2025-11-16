import { createApp } from 'vue'
import App from './App.vue'
import router from './router'

// Vuetify importok
import 'vuetify/styles'
import { aliases, mdi } from 'vuetify/iconsets/mdi'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

// Vuetify példány
const vuetify = createVuetify({
  components,
  directives,
  icons: {
    defaultSet: 'mdi',
    aliases,
    sets: { mdi }
  },
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        colors: {
          primary: '#2E7D32',
          secondary: '#1B5E20',
          success: '#43A047',
          warning: '#FBC02D',
          info: '#4CAF50',
          error: '#D32F2F',
          surface: '#ffffff',
          background: '#f4f7f5'
        }
      }
    }
  },
  defaults: {
    VTextField: { variant: 'outlined', density: 'comfortable' },
    VBtn: { rounded: 'lg' },
    VCard: { rounded: 'xl' }
  }
})



createApp(App).use(router).use(vuetify).mount('#app')
