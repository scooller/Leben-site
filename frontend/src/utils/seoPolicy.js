const FILTER_BASE_PATH = '/f';
const ALLOWED_FILTER_KEYS = new Set(['proyectos', 'comunas', 'tipos', 'tipo']);
const FORCE_NOINDEX_PATHS = new Set(['/contacto', '/pago']);
const TRANSIENT_QUERY_KEYS = new Set([
  'preview_token',
  'payment_id',
  'status_token',
  'status',
  'result',
  'gateway',
  'token',
  'session',
  'checkout',
  'reservation',
]);

export const normalizePathname = (value) => {
  const sanitizedPath = `${value || '/'}`.split('?')[0];
  const normalized = sanitizedPath.replace(/\/+$/, '');

  return normalized === '' ? '/' : normalized;
};

const parseFilterSegments = (pathname) => {
  const normalizedPath = normalizePathname(pathname);

  if (normalizedPath !== FILTER_BASE_PATH && !normalizedPath.startsWith(`${FILTER_BASE_PATH}/`)) {
    return [];
  }

  return normalizedPath
    .slice(FILTER_BASE_PATH.length)
    .split('/')
    .filter(Boolean)
    .map((segment) => decodeURIComponent(segment));
};

export const isTemporaryFilterVariant = (pathname) => {
  const normalizedPath = normalizePathname(pathname);

  if (normalizedPath === FILTER_BASE_PATH) {
    return false;
  }

  const segments = parseFilterSegments(normalizedPath);

  if (segments.length === 0) {
    return false;
  }

  if (segments.length % 2 !== 0) {
    return true;
  }

  const filterGroups = [];

  for (let index = 0; index < segments.length; index += 2) {
    const key = `${segments[index] || ''}`.trim().toLowerCase();
    const valueChunk = `${segments[index + 1] || ''}`.trim();

    if (!ALLOWED_FILTER_KEYS.has(key) || valueChunk === '') {
      return true;
    }

    const values = valueChunk
      .split(',')
      .map((value) => `${value}`.trim())
      .filter(Boolean);

    if (values.length === 0) {
      return true;
    }

    filterGroups.push({ key, values });
  }

  if (filterGroups.length > 2) {
    return true;
  }

  return filterGroups.some((group) => group.values.length > 2);
};

export const hasTransientSeoQueryParams = (search = '') => {
  const params = new URLSearchParams(search || '');

  return [...params.keys()].some((key) => TRANSIENT_QUERY_KEYS.has(`${key}`.toLowerCase()));
};

export const resolveSeoPolicy = ({ pathname, search = '' }) => {
  const normalizedPath = normalizePathname(pathname);
  const hasTransientQuery = hasTransientSeoQueryParams(search);
  const isPreviewTokenRequest = new URLSearchParams(search || '').has('preview_token');
  const temporaryFilterVariant = isTemporaryFilterVariant(normalizedPath);
  const forceNoindex = FORCE_NOINDEX_PATHS.has(normalizedPath) || hasTransientQuery || temporaryFilterVariant;

  if (forceNoindex) {
    return {
      robots: isPreviewTokenRequest ? 'noindex,nofollow' : 'noindex,follow',
      isNoindex: true,
      isTemporaryFilterVariant: temporaryFilterVariant,
    };
  }

  return {
    robots: 'index,follow',
    isNoindex: false,
    isTemporaryFilterVariant: false,
  };
};
