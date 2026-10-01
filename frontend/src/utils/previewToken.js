const PREVIEW_TOKEN_PARAM = 'preview_token';
const PREVIEW_TOKEN_STORAGE_KEY = 'ileben_preview_token';

/**
 * Obtiene el preview token activo.
 * Prioridad:
 * 1. Query parameter en window.location.search (lo guarda en sessionStorage)
 * 2. Fallback en sessionStorage
 *
 * @returns {string|null}
 */
export const getActivePreviewToken = () => {
  if (typeof window === 'undefined') {
    return null;
  }

  try {
    const searchToken = new URLSearchParams(window.location.search).get(PREVIEW_TOKEN_PARAM);
    if (searchToken && searchToken.trim() !== '') {
      const cleanToken = searchToken.trim();
      sessionStorage.setItem(PREVIEW_TOKEN_STORAGE_KEY, cleanToken);
      return cleanToken;
    }
  } catch {
    // ignore
  }

  try {
    const storedToken = sessionStorage.getItem(PREVIEW_TOKEN_STORAGE_KEY);
    if (storedToken && storedToken.trim() !== '') {
      return storedToken.trim();
    }
  } catch {
    // ignore
  }

  return null;
};

/**
 * Añade el preview_token activo a una URL (relativa o absoluta).
 *
 * @param {string} url
 * @returns {string}
 */
export const appendPreviewTokenToUrl = (url) => {
  if (!url || typeof url !== 'string') {
    return url;
  }

  const token = getActivePreviewToken();
  if (!token) {
    return url;
  }

  // Ignorar protocolos especiales o anclas vacías
  if (
    url.startsWith('mailto:') ||
    url.startsWith('tel:') ||
    url.startsWith('javascript:') ||
    url === '#' ||
    url.startsWith('#')
  ) {
    return url;
  }

  try {
    const isAbsolute = /^https?:\/\//i.test(url);
    if (isAbsolute) {
      const parsed = new URL(url);
      const isInternalHost = typeof window !== 'undefined' && parsed.hostname === window.location.hostname;
      if (!isInternalHost) {
        return url;
      }
      if (parsed.searchParams.has(PREVIEW_TOKEN_PARAM)) {
        return url;
      }
      parsed.searchParams.set(PREVIEW_TOKEN_PARAM, token);
      return parsed.href;
    }

    const parsed = new URL(url, 'http://localhost');
    if (parsed.searchParams.has(PREVIEW_TOKEN_PARAM)) {
      return url;
    }
    parsed.searchParams.set(PREVIEW_TOKEN_PARAM, token);
    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
  } catch {
    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}${PREVIEW_TOKEN_PARAM}=${encodeURIComponent(token)}`;
  }
};

/**
 * Si existe un preview token activo en sessionStorage pero no está en la URL actual,
 * actualiza window.history.replaceState para mantenerlo visible y persistente.
 */
export const syncPreviewTokenToCurrentUrl = () => {
  if (typeof window === 'undefined') {
    return;
  }

  const token = getActivePreviewToken();
  if (!token) {
    return;
  }

  try {
    const searchParams = new URLSearchParams(window.location.search);
    if (!searchParams.has(PREVIEW_TOKEN_PARAM)) {
      searchParams.set(PREVIEW_TOKEN_PARAM, token);
      const newSearch = searchParams.toString();
      const newUrl = `${window.location.pathname}${newSearch ? `?${newSearch}` : ''}${window.location.hash || ''}`;
      window.history.replaceState(window.history.state || {}, '', newUrl);
    }
  } catch {
    // ignore
  }
};
