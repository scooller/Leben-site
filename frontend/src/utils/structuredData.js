const SCRIPT_PREFIX = 'ileben-jsonld-';

const getScriptId = (key) => `${SCRIPT_PREFIX}${key}`;

export const removeStructuredData = (key) => {
  if (typeof document === 'undefined') {
    return;
  }

  const script = document.getElementById(getScriptId(key));

  if (script) {
    script.remove();
  }
};

export const setStructuredData = (key, payload) => {
  if (typeof document === 'undefined') {
    return;
  }

  if (!payload || typeof payload !== 'object') {
    removeStructuredData(key);
    return;
  }

  const scriptId = getScriptId(key);
  let script = document.getElementById(scriptId);

  if (!script) {
    script = document.createElement('script');
    script.id = scriptId;
    script.type = 'application/ld+json';
    document.head.appendChild(script);
  }

  script.textContent = JSON.stringify(payload);
};
