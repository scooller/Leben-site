import { getStoredUtmParams } from './utmSession';

const SKIPPED_PROTOCOLS_PATTERN = /^(mailto:|tel:|sms:|javascript:|data:|#)/i;

const normalizeValue = (value) => `${value ?? ''}`.trim();

const shouldSkipUtmAppend = (value) => {
  const normalizedValue = normalizeValue(value);

  if (normalizedValue === '') {
    return true;
  }

  return SKIPPED_PROTOCOLS_PATTERN.test(normalizedValue);
};

const isHttpUrl = (url) => url.protocol === 'http:' || url.protocol === 'https:';

export const appendSessionUtmsToExternalUrl = (value) => {
  const normalizedValue = normalizeValue(value);

  if (typeof window === 'undefined' || shouldSkipUtmAppend(normalizedValue)) {
    return normalizedValue;
  }

  try {
    const parsedUrl = new URL(normalizedValue, window.location.origin);

    if (!isHttpUrl(parsedUrl) || parsedUrl.origin === window.location.origin) {
      return normalizedValue;
    }

    const storedUtms = getStoredUtmParams();

    Object.entries(storedUtms).forEach(([key, rawValue]) => {
      const utmValue = normalizeValue(rawValue);

      if (utmValue !== '' && !parsedUrl.searchParams.has(key)) {
        parsedUrl.searchParams.set(key, utmValue);
      }
    });

    return parsedUrl.toString();
  } catch {
    return normalizedValue;
  }
};

export const isExternalHttpUrl = (value) => {
  const normalizedValue = normalizeValue(value);

  if (typeof window === 'undefined' || shouldSkipUtmAppend(normalizedValue)) {
    return false;
  }

  try {
    const parsedUrl = new URL(normalizedValue, window.location.origin);

    return isHttpUrl(parsedUrl) && parsedUrl.origin !== window.location.origin;
  } catch {
    return false;
  }
};
