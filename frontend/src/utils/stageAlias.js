function normalizeStageKey(value) {
  if (!value || typeof value !== 'string') {
    return '';
  }

  return value
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
}

function normalizeProjectSlug(value) {
  if (!value || typeof value !== 'string') {
    return '';
  }

  return value
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

const STAGE_ALIAS_MAP = (() => {
  try {
    const rawValue = import.meta.env.VITE_STAGE_ALIAS_MAP;

    if (!rawValue) {
      return {};
    }

    const parsedValue = JSON.parse(rawValue);

    if (!parsedValue || typeof parsedValue !== 'object' || Array.isArray(parsedValue)) {
      return {};
    }

    return parsedValue;
  } catch {
    return {};
  }
})();

const STAGE_KEY_ALIASES = (() => {
  try {
    const rawValue = import.meta.env.VITE_STAGE_KEY_ALIASES;

    if (!rawValue) {
      return {};
    }

    const parsedValue = JSON.parse(rawValue);

    if (!parsedValue || typeof parsedValue !== 'object' || Array.isArray(parsedValue)) {
      return {};
    }

    return Object.entries(parsedValue).reduce((accumulator, [sourceKey, targetKey]) => {
      const normalizedSource = normalizeStageKey(sourceKey);
      const normalizedTarget = normalizeStageKey(targetKey);

      if (!normalizedSource || !normalizedTarget) {
        return accumulator;
      }

      accumulator[normalizedSource] = normalizedTarget;

      return accumulator;
    }, {});
  } catch {
    return {};
  }
})();

const STAGE_ALIAS_BY_PROJECT_SLUG = (() => {
  try {
    const rawValue = import.meta.env.VITE_STAGE_ALIAS_BY_PROJECT_SLUG;

    if (!rawValue) {
      return {};
    }

    const parsedValue = JSON.parse(rawValue);

    if (!parsedValue || typeof parsedValue !== 'object' || Array.isArray(parsedValue)) {
      return {};
    }

    return Object.entries(parsedValue).reduce((accumulator, [slug, alias]) => {
      const normalizedSlug = normalizeProjectSlug(slug);
      const normalizedAlias = typeof alias === 'string' ? alias.trim() : '';

      if (!normalizedSlug || !normalizedAlias) {
        return accumulator;
      }

      accumulator[normalizedSlug] = normalizedAlias;

      return accumulator;
    }, {});
  } catch {
    return {};
  }
})();

export { normalizeProjectSlug, normalizeStageKey };

const canonicalStageKey = (value) => {
  const normalizedStage = normalizeStageKey(value);

  if (!normalizedStage) {
    return '';
  }

  return STAGE_KEY_ALIASES[normalizedStage] ?? normalizedStage;
};

export const resolveStageAlias = (stage, projectSlug = '', entregaInmediata = false) => {
  if (entregaInmediata === true || entregaInmediata === '1' || entregaInmediata === 1) {
    return 'Entrega inmediata';
  }

  const originalStage = typeof stage === 'string' ? stage.trim() : '';
  const normalizedStage = canonicalStageKey(stage);

  if (normalizedStage === 'entrega_inmediata') {
    return 'Entrega inmediata';
  }

  const normalizedProjectSlug = normalizeProjectSlug(projectSlug);

  if (normalizedProjectSlug && STAGE_ALIAS_BY_PROJECT_SLUG[normalizedProjectSlug]) {
    return STAGE_ALIAS_BY_PROJECT_SLUG[normalizedProjectSlug];
  }

  if (!normalizedStage) {
    return originalStage;
  }

  return STAGE_ALIAS_MAP[normalizedStage] ?? originalStage;
};

export const getStageKeysByAlias = (alias) => {
  if (!alias || typeof alias !== 'string') {
    return [];
  }

  return Object.entries(STAGE_ALIAS_MAP)
    .filter(([, mappedAlias]) => mappedAlias === alias)
    .map(([stageKey]) => canonicalStageKey(stageKey))
    .filter(Boolean)
    .filter((stageKey, index, items) => items.indexOf(stageKey) === index);
};

export const getProjectSlugsByAlias = (alias) => {
  if (!alias || typeof alias !== 'string') {
    return [];
  }

  return Object.entries(STAGE_ALIAS_BY_PROJECT_SLUG)
    .filter(([, mappedAlias]) => mappedAlias === alias)
    .map(([slug]) => slug);
};

export const getUniqueStageAliases = (stageValues) => {
  if (!Array.isArray(stageValues)) {
    return [];
  }

  const seenAliases = new Set();
  const aliases = [];

  stageValues.forEach((stageValue) => {
    const alias = resolveStageAlias(stageValue);

    if (!alias || seenAliases.has(alias)) {
      return;
    }

    seenAliases.add(alias);
    aliases.push(alias);
  });

  return aliases;
};

export const getConfiguredEntregaAliases = (stageValues = []) => {
  const aliasesFromProjects = Array.from(new Set(Object.values(STAGE_ALIAS_BY_PROJECT_SLUG).filter(Boolean)));

  if (aliasesFromProjects.length > 0) {
    return aliasesFromProjects;
  }

  return getUniqueStageAliases(stageValues);
};
