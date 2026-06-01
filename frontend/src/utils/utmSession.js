const UTM_SESSION_STORAGE_KEY = 'ileben-utms-session';

let utmDefaultOverrides = {};

export const UTM_PARAM_CONFIG = [
  { key: 'utm_source', defaultValue: 'direct' },
  { key: 'utm_medium', defaultValue: 'organic' },
  { key: 'utm_campaign', defaultValue: 'campaign' },
  { key: 'utm_term', defaultValue: 'none' },
  { key: 'utm_content', defaultValue: 'none' },
  { key: 'utm_site', defaultValue: '' },
];

const TRACKED_UTM_KEYS = UTM_PARAM_CONFIG.map((config) => config.key);

const normalizeUtmValue = (value) => {
  if (value === null || value === undefined) {
    return '';
  }

  try {
    return decodeURIComponent(`${value}`.trim());
  } catch {
    return `${value}`.trim();
  }
};

const resolveDefaultValue = (config) => {
  const overriddenDefaultValue = normalizeUtmValue(utmDefaultOverrides?.[config.key]);
  const effectiveDefaultValue = overriddenDefaultValue !== '' ? overriddenDefaultValue : config.defaultValue;

  if (config.key === 'utm_site') {
    if (typeof window === 'undefined') {
      return normalizeUtmValue(effectiveDefaultValue);
    }

    return normalizeUtmValue(window.location.hostname || effectiveDefaultValue || '');
  }

  return normalizeUtmValue(effectiveDefaultValue);
};

export const setUtmDefaultOverrides = (overrides = {}) => {
  if (!overrides || typeof overrides !== 'object') {
    return;
  }

  utmDefaultOverrides = {
    ...utmDefaultOverrides,
    ...Object.entries(overrides).reduce((accumulator, [key, value]) => {
      accumulator[key] = normalizeUtmValue(value);
      return accumulator;
    }, {}),
  };

  const storedValues = readStoredUtms();
  const nextValues = { ...storedValues };
  const forcedCampaignOverride = normalizeUtmValue(utmDefaultOverrides.utm_campaign);

  const legacyDefaultValuesByKey = {
    utm_campaign: ['auto-tagging', 'campaign'],
    utm_content: ['none'],
    utm_term: ['none'],
  };

  UTM_PARAM_CONFIG.forEach((config) => {
    if (config.key === 'utm_campaign' && forcedCampaignOverride !== '') {
      nextValues[config.key] = forcedCampaignOverride;
      return;
    }

    const fallbackValue = resolveDefaultValue(config);
    const currentValue = normalizeUtmValue(nextValues[config.key]);
    const isLegacyValue = (legacyDefaultValuesByKey[config.key] || []).includes(currentValue.toLowerCase());

    if (currentValue !== '' && !isLegacyValue) {
      return;
    }

    if (fallbackValue !== '') {
      nextValues[config.key] = fallbackValue;
    }
  });

  persistStoredUtms(nextValues);
};

const readStoredUtms = () => {
  if (typeof window === 'undefined') {
    return {};
  }

  try {
    const rawValue = window.sessionStorage.getItem(UTM_SESSION_STORAGE_KEY);

    if (!rawValue) {
      return {};
    }

    const parsedValue = JSON.parse(rawValue);

    return parsedValue && typeof parsedValue === 'object' ? parsedValue : {};
  } catch {
    return {};
  }
};

const persistStoredUtms = (values) => {
  if (typeof window === 'undefined') {
    return;
  }

  window.sessionStorage.setItem(UTM_SESSION_STORAGE_KEY, JSON.stringify(values));
};

const removeTrackedUtmsFromSearchParams = (searchParams) => {
  let hasChanges = false;

  TRACKED_UTM_KEYS.forEach((key) => {
    if (!searchParams.has(key)) {
      return;
    }

    searchParams.delete(key);
    hasChanges = true;
  });

  return hasChanges;
};

export const captureUtmParamsFromUrl = (search = '') => {
  if (typeof window === 'undefined') {
    return {};
  }

  const params = new URLSearchParams(search || window.location.search || '');
  const storedValues = readStoredUtms();
  const nextValues = { ...storedValues };

  UTM_PARAM_CONFIG.forEach((config) => {
    const incomingValue = normalizeUtmValue(params.get(config.key));

    if (incomingValue !== '') {
      nextValues[config.key] = incomingValue;
      return;
    }

    if (normalizeUtmValue(nextValues[config.key]) === '') {
      const fallbackValue = resolveDefaultValue(config);

      if (fallbackValue !== '') {
        nextValues[config.key] = fallbackValue;
      }
    }
  });

  persistStoredUtms(nextValues);

  return nextValues;
};

export const cleanTrackedUtmsFromCurrentUrl = (search = '') => {
  if (typeof window === 'undefined' || typeof window.history?.replaceState !== 'function') {
    return false;
  }

  const currentSearch = search || window.location.search || '';

  if (currentSearch === '') {
    return false;
  }

  const searchParams = new URLSearchParams(currentSearch);
  const hasChanges = removeTrackedUtmsFromSearchParams(searchParams);

  if (!hasChanges) {
    return false;
  }

  const nextSearch = searchParams.toString();
  const nextUrl = `${window.location.pathname}${nextSearch !== '' ? `?${nextSearch}` : ''}${window.location.hash || ''}`;

  window.history.replaceState(window.history.state, '', nextUrl);

  return true;
};

export const getStoredUtmParams = () => {
  if (typeof window === 'undefined') {
    return {};
  }

  const storedValues = readStoredUtms();

  if (Object.keys(storedValues).length === 0) {
    return captureUtmParamsFromUrl(window.location.search || '');
  }

  const nextValues = { ...storedValues };

  UTM_PARAM_CONFIG.forEach((config) => {
    if (normalizeUtmValue(nextValues[config.key]) === '') {
      const fallbackValue = resolveDefaultValue(config);

      if (fallbackValue !== '') {
        nextValues[config.key] = fallbackValue;
      }
    }
  });

  persistStoredUtms(nextValues);

  return nextValues;
};
