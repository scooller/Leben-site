import { getStoredUtmParams } from './utmSession';

const GTM_SCRIPT_ID = 'ileben-gtm-script';
const GTM_NOSCRIPT_ID = 'ileben-gtm-noscript';

const resolveContainerId = (value) => `${value ?? ''}`.trim().toUpperCase();

const isValidContainerId = (value) => /^GTM-[A-Z0-9]+$/.test(value);

const ensureDataLayer = () => {
  if (typeof window === 'undefined') {
    return [];
  }

  window.dataLayer = window.dataLayer || [];

  return window.dataLayer;
};

export const initializeTagManager = (containerId) => {
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return null;
  }

  const normalizedId = resolveContainerId(containerId);

  if (!isValidContainerId(normalizedId)) {
    return null;
  }

  const dataLayer = ensureDataLayer();

  if (window.__ilebenGtmInitializedFor !== normalizedId) {
    dataLayer.push({
      'gtm.start': Date.now(),
      event: 'gtm.js',
    });

    window.__ilebenGtmInitializedFor = normalizedId;
  }

  if (!document.getElementById(GTM_SCRIPT_ID)) {
    const script = document.createElement('script');
    script.id = GTM_SCRIPT_ID;
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(normalizedId)}`;
    document.head.appendChild(script);
  }

  if (document.body && !document.getElementById(GTM_NOSCRIPT_ID)) {
    const noScript = document.createElement('noscript');
    noScript.id = GTM_NOSCRIPT_ID;
    noScript.innerHTML = `<iframe src="https://www.googletagmanager.com/ns.html?id=${encodeURIComponent(normalizedId)}" height="0" width="0" style="display:none;visibility:hidden"></iframe>`;
    document.body.prepend(noScript);
  }

  return normalizedId;
};

export const trackEvent = (eventName, payload = {}) => {
  if (typeof window === 'undefined') {
    return;
  }

  const normalizedEventName = `${eventName ?? ''}`.trim();

  if (normalizedEventName === '') {
    return;
  }

  const dataLayer = ensureDataLayer();

  dataLayer.push({
    event: normalizedEventName,
    event_time: new Date().toISOString(),
    ...getStoredUtmParams(),
    ...payload,
  });
};

export const trackPageView = ({ path, title } = {}) => {
  if (typeof window === 'undefined') {
    return;
  }

  const pagePath = `${path || window.location.pathname || '/'}`.trim() || '/';
  const pageTitle = `${title || document.title || ''}`.trim();
  const fingerprint = `${pagePath}|${pageTitle}`;

  if (window.__ilebenLastPageView === fingerprint) {
    return;
  }

  window.__ilebenLastPageView = fingerprint;

  trackEvent('page_view', {
    page_path: pagePath,
    page_title: pageTitle,
    page_url: window.location.href,
    page_referrer: document.referrer || null,
  });
};
