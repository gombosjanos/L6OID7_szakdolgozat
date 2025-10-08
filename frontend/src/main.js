import { createApp } from 'vue'
import App from './App.vue'
import router from './router'

// Vuetify importok
import 'vuetify/styles'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

// Vuetify példány
const vuetify = createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        colors: {
          primary: '#1E88E5',
          secondary: '#1565C0',
          error: '#D32F2F'
        }
      }
    }
  }
})



createApp(App).use(router).use(vuetify).mount('#app')
