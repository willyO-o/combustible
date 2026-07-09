// stores/auth.ts
import { defineStore } from 'pinia';


const users: User[] = [
    {
      "id": 1,
      "username": "spruko@gmail.com",
      "password": "spruko1234",
      "token": ""
    },
    {
      "id": 2,
      "username": "test",
      "password": "test",
      "token": ""
    }
  ]; // Type assertion for the imported users

interface LoginPayload {
  username: string;
  password: string;
}

interface User {
  id: number;
  username: string;
  password: string;
  token?: string;
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    authenticated: false,
    loading: false,
  }),

  actions: {
    async authenticateUser({ username, password }: LoginPayload) {
      this.loading = true;

      // Simulate API authentication using mock data
      const user = users.find(
        (u: User) => u.username === username && u.password === password
      );

      if (user) {
        const token = this.generateToken(user);
        localStorage.setItem('token', token); // Store token in localStorage
        this.authenticated = true;
        this.loading = false;
        return { authenticated: true };
      } else {
        localStorage.removeItem('token');
        this.authenticated = false;
        this.loading = false;
        return { authenticated: false };
      }
    },

    logUserOut() {
      localStorage.removeItem('token');
      this.authenticated = false;
    },

    generateToken(user: User): string {
      // Simulated token generation
      return `Bearer-${user.id}-${user.username}`;
    },

    checkAuthOnStartup() {
      const token = localStorage.getItem('token');
      this.authenticated = !!token;
    }
  }
});
