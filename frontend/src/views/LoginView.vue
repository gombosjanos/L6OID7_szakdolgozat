<template>
  <div class="login-container">
    <h2>Bejelentkezés</h2>
    <form @submit.prevent="login">
      <div>
        <label for="email">Email</label>
        <input type="email" id="email" v-model="email" required />
      </div>
      <div>
        <label for="password">Jelszó</label>
        <input type="password" id="password" v-model="password" required />
      </div>
      <button type="submit">Belépés</button>
    </form>
    <p v-if="error" style="color:red">{{ error }}</p>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'LoginView',
  data() {
    return {
      email: '',
      password: '',
      error: null
    }
  },
  methods: {
    async login() {
      try {
        const response = await axios.post('http://127.0.0.1:8000/api/login', {
          email: this.email,
          jelszo: this.password
        })
        console.log('Login success:', response.data)
        localStorage.setItem('token', response.data.token)
        alert('Sikeres bejelentkezés!')
      } catch (err) {
        console.error(err)
        this.error = 'Hibás email vagy jelszó'
      }
    }
  }
}
</script>

<style>
.login-container {
  max-width: 400px;
  margin: 100px auto;
  text-align: center;
}
.login-container input {
  display: block;
  width: 100%;
  padding: 8px;
  margin-bottom: 10px;
}
</style>
