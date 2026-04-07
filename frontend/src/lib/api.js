import axios from 'axios';

const defaultAuthToken = import.meta.env.AUTH_TOKEN?.trim();

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Interceptor para agregar token de autenticación
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token') || defaultAuthToken;

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

// Interceptor para manejar errores de autenticación sin forzar navegación.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Limpiar credenciales locales y propagar el error para que la UI lo muestre.
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
    }

    return Promise.reject(error);
  }
);

export default api;
