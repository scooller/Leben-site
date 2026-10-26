import axios from 'axios';
import { APP_HTTP_ERROR_EVENT, normalizeHttpError } from '../utils/errorHandler';
import { getActivePreviewToken } from '../utils/previewToken';

const defaultAuthToken = import.meta.env.VITE_API_AUTH_TOKEN?.trim();

const resolvePreviewToken = () => getActivePreviewToken();

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: {
    'Accept': 'application/json',
  },
});

// Interceptor para agregar token de autenticación
api.interceptors.request.use((config) => {
  if (config.data instanceof FormData) {
    if (config.headers?.delete) {
      config.headers.delete('Content-Type');
    } else if (config.headers) {
      delete config.headers['Content-Type'];
      delete config.headers['content-type'];
    }
  } else if (config.headers && !config.headers['Content-Type'] && !config.headers['content-type']) {
    config.headers['Content-Type'] = 'application/json';
  }

  const previewToken = resolvePreviewToken();
  const userToken = localStorage.getItem('auth_token');
  const token = userToken || defaultAuthToken;

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  if (previewToken) {
    config.headers['X-Preview-Token'] = previewToken;

    const method = (config.method || 'get').toLowerCase();
    if (['get', 'delete', 'head', 'options'].includes(method)) {
      config.params = {
        ...(config.params || {}),
        preview_token: config.params?.preview_token ?? previewToken,
      };
    }
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
