import { getStoredUtmParams } from './utmSession';

const GTM_SCRIPT_ID = 'ileben-gtm-script';
const GTM_NOSCRIPT_ID = 'ileben-gtm-noscript';
const FB_PIXEL_SCRIPT_ID = 'ileben-fb-pixel-script';

const FBQ_STANDARD_EVENTS = {
  page_view: 'PageView',
  plant_click: 'ViewContent',
  reserve_click: 'AddToCart',
  checkout_start: 'InitiateCheckout',
  reservation_success: 'Purchase',
};

const resolveContainerId = (value) => `${value ?? ''}`.trim().toUpperCase();
const resolvePixelId = (value) => `${value ?? ''}`.trim();

const isValidContainerId = (value) => /^GTM-[A-Z0-9]+$/.test(value);
const isValidPixelId = (value) => /^[0-9]+$/.test(value);

const ensureDataLayer = () => {
  if (typeof window === 'undefined') {
    return [];
  }

  window.dataLayer = window.dataLayer || [];

  return window.dataLayer;
};

const ensureFacebookPixelQueue = () => {
  if (typeof window === 'undefined') {
    return null;
  }

  if (typeof window.fbq === 'function') {
    return window.fbq;
  }

  const fbq = (...args) => {
    if (typeof fbq.callMethod === 'function') {
      fbq.callMethod(...args);
      return;
    }

    fbq.queue.push(args);
  };

  fbq.push = fbq;
  fbq.loaded = false;
  fbq.version = '2.0';
  fbq.queue = [];

  window.fbq = fbq;
  window._fbq = fbq;

  return fbq;
};

const sendFacebookEvent = (eventName, payload = {}) => {
  const normalizedEventName = `${eventName ?? ''}`.trim();

  if (normalizedEventName === '') {
    return;
  }

  // Prevenir que el mismo evento dispare más de una vez en 3 segundos
  if (!window.__ilebenLastEventTime) {
    window.__ilebenLastEventTime = {};
  }

  const now = Date.now();
  const lastTime = window.__ilebenLastEventTime[normalizedEventName];

  if (lastTime && now - lastTime < 3000) {
    return;
  }

  window.__ilebenLastEventTime[normalizedEventName] = now;

  const fbq = ensureFacebookPixelQueue();

  if (!fbq) {
    return;
  }

  const standardEventName = FBQ_STANDARD_EVENTS[normalizedEventName];

  if (standardEventName) {
    fbq('track', standardEventName, payload);
    return;
  }

  fbq('trackCustom', normalizedEventName, payload);
};

export const initializeFacebookPixel = (pixelId) => {
  if (typeof window === 'undefined') {
    return null;
  }

  const normalizedId = resolvePixelId(pixelId);
  const fbq = ensureFacebookPixelQueue();

  if (!isValidPixelId(normalizedId) || !fbq) {
    return null;
  }

  fbq('init', normalizedId);

  return normalizedId;
};

export const initializeTagManager = (containerId) => {
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return null;
  }

  ensureFacebookPixelQueue();

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

  const eventPayload = {
    event: normalizedEventName,
    event_time: new Date().toISOString(),
    ...getStoredUtmParams(),
    ...payload,
  };

  const dataLayer = ensureDataLayer();

  dataLayer.push(eventPayload);
  sendFacebookEvent(normalizedEventName, eventPayload);
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
