import axios from 'axios';
import { APP_HTTP_ERROR_EVENT, normalizeHttpError } from '../utils/errorHandler';

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
    if (error?.code === 'ERR_CANCELED') {
      return Promise.reject(error);
    }

    if (error.response?.status === 401) {
      // Limpiar credenciales locales y propagar el error para que la UI lo muestre.
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
    }

    const appError = normalizeHttpError(error, 'api.interceptor');
    const shouldBroadcast = appError.type === 'network'
      || [401, 422, 500, 502, 503, 504].includes(appError.status);

    if (shouldBroadcast && typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
      window.dispatchEvent(new CustomEvent(APP_HTTP_ERROR_EVENT, { detail: appError }));
    }

    return Promise.reject(appError);
  }
);

export default api;
