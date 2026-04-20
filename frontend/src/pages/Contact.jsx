import { useEffect, useMemo, useState } from 'react';
import { useSiteConfig } from '../contexts/SiteConfigContext';
import contactSubmissionsService from '../services/contactSubmissions';
import { trackEvent } from '../utils/tagManager';
import { getStoredUtmParams } from '../utils/utmSession';
import { proyectosService } from '../services/proyectos';
import SiteHeader from '../components/SiteHeader';
import SiteFooter from '../components/SiteFooter';
import '../styles/contact.scss' with { type: 'css' };

const CONTACT_RANGE_FIELD = {
  key: 'rango',
  label: 'Rango de renta',
  icon: 'coins',
  type: 'select',
  placeholder: 'Selecciona un rango de renta',
  required: false,
  options: [],
};

const CONTACT_COMUNA_FIELD = {
  key: 'comuna',
  label: 'Comuna',
  icon: 'map-location',
  type: 'select',
  placeholder: 'Selecciona una comuna',
  required: false,
  options: [],
};

const CONTACT_PROJECT_FIELD = {
  key: 'proyecto',
  label: 'Proyecto',
  icon: 'building',
  type: 'select',
  placeholder: 'Selecciona un proyecto',
  required: false,
  options: [],
};

const formatRut = (value) => {
  const cleaned = String(value ?? '')
    .replace(/[^0-9kK]/g, '')
    .toUpperCase()
    .slice(0, 9);

  if (cleaned.length <= 1) {
    return cleaned;
  }

  const body = cleaned.slice(0, -1);
  const dv = cleaned.slice(-1);

  return `${body}-${dv}`;
};

const isValidRut = (value) => {
  const formatted = formatRut(value);

  if (!/^\d{7,8}-[0-9K]$/.test(formatted)) {
    return false;
  }

  const cleaned = formatted.replace('-', '').toLowerCase();
  const body = cleaned.slice(0, -1);
  const dv = cleaned.slice(-1);

  let sum = 0;
  let multiplier = 2;

  for (let index = body.length - 1; index >= 0; index -= 1) {
    sum += Number(body[index]) * multiplier;
    multiplier = multiplier === 7 ? 2 : multiplier + 1;
  }

  const remainder = 11 - (sum % 11);
  const expectedDv = remainder === 11 ? '0' : remainder === 10 ? 'k' : `${remainder}`;

  return dv === expectedDv;
};

const normalizeFieldOptions = (options = []) => {
  if (!Array.isArray(options)) {
    return [];
  }

  return options
    .map((option) => {
      if (option && typeof option === 'object') {
        const value = `${option.value ?? option.label ?? ''}`.trim();
        const label = `${option.label ?? option.value ?? ''}`.trim();

        return {
          value,
          label: label || value,
          projects: normalizeProjects(option.projects ?? option.proyectos ?? option.project_types),
        };
      }

      const value = `${option ?? ''}`.trim();

      return {
        value,
        label: value,
        projects: [],
      };
    })
    .filter((option) => option.value !== '');
};

const normalizeProjects = (projects) => {
  const normalizeValue = (value) => `${value ?? ''}`.trim().toLowerCase();

  if (Array.isArray(projects)) {
    return [...new Set(projects
      .map((project) => normalizeValue(project))
      .filter((project) => project !== ''))];
  }

  if (typeof projects === 'string') {
    return normalizeProjects(projects.includes(',') ? projects.split(',') : [projects]);
  }

  return [];
};

const buildRangeOptionValue = (rangeValue, projectTypes) => {
  const normalizedRangeValue = `${rangeValue ?? ''}`.trim();
  const normalizedProjects = normalizeProjects(projectTypes);

  if (normalizedProjects.length === 0) {
    return normalizedRangeValue;
  }

  return `${normalizedProjects.join(',')}::${normalizedRangeValue}`;
};

function Contact({ onNavigate, currentPath }) {
  const { config, loading: configLoading } = useSiteConfig();
  const [values, setValues] = useState({});
  const [fieldErrors, setFieldErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [submitSuccess, setSubmitSuccess] = useState('');
  const [submitError, setSubmitError] = useState('');
  const [projectCatalog, setProjectCatalog] = useState([]);
  const [projectsLoading, setProjectsLoading] = useState(true);

  const storedUtmParams = useMemo(() => getStoredUtmParams(), []);

  const socialLinks = useMemo(() => {
    const social = config?.social || {};

    return [
      {
        key: 'facebook',
        label: 'Facebook',
        icon: 'facebook',
        url: social.facebook,
      },
      {
        key: 'instagram',
        label: 'Instagram',
        icon: 'instagram',
        url: social.instagram,
      },
      {
        key: 'linkedin',
        label: 'LinkedIn',
        icon: 'linkedin-in',
        url: social.linkedin,
      },
      {
        key: 'youtube',
        label: 'YouTube',
        icon: 'youtube',
        url: social.youtube,
      },
      {
        key: 'twitter',
        label: 'X',
        icon: 'x-twitter',
        url: social.twitter,
      },
    ].filter((item) => Boolean(item.url));
  }, [config?.social]);

  const title = config?.contact_page?.title || 'Contacto';
  const subtitle = config?.contact_page?.subtitle || '';
  const content = config?.contact_page?.content;
  const contactHeroDesktopImage = config?.hero?.contact?.image_desktop || config?.hero?.contact?.image || null;
  const contactHeroMobileImage = config?.hero?.contact?.image_mobile || contactHeroDesktopImage;
  const formFields = useMemo(() => {
    const configuredFields = config?.contact_page?.form_fields;

    if (!Array.isArray(configuredFields) || configuredFields.length === 0) {
      return [];
    }

    return configuredFields
      .filter((field) => field && field.key)
      .map((field, index) => ({
        renderKey: `${`${field.key}`.trim()}-${index}`,
        key: `${field.key}`.trim(),
        icon: field.icon ? `${field.icon}`.trim() : undefined,
        label: `${field.label || field.key}`.trim(),
        type: `${field.type || 'text'}`.trim(),
        placeholder: `${field.placeholder || ''}`,
        required: Boolean(field.required),
        options: normalizeFieldOptions(field.options),
        projects: normalizeProjects(field.projects ?? field.proyectos ?? field.project_types),
      }))
      .filter((field) => field.key !== '');
  }, [config?.contact_page?.form_fields]);

  const hasConfiguredRangeField = useMemo(
    () => formFields.some((field) => field.key === CONTACT_RANGE_FIELD.key),
    [formFields]
  );

  const hasConfiguredProjectField = useMemo(
    () => formFields.some((field) => field.key === CONTACT_PROJECT_FIELD.key),
    [formFields]
  );

  const hasConfiguredComunaField = useMemo(
    () => formFields.some((field) => field.key === CONTACT_COMUNA_FIELD.key),
    [formFields]
  );

  const configuredRangeField = useMemo(
    () => formFields.find((field) => field.key === CONTACT_RANGE_FIELD.key) || CONTACT_RANGE_FIELD,
    [formFields]
  );

  const configuredProjectField = useMemo(
    () => formFields.find((field) => field.key === CONTACT_PROJECT_FIELD.key) || CONTACT_PROJECT_FIELD,
    [formFields]
  );

  const configuredComunaField = useMemo(
    () => formFields.find((field) => field.key === CONTACT_COMUNA_FIELD.key) || CONTACT_COMUNA_FIELD,
    [formFields]
  );

  const rangeFieldOptions = useMemo(() => {
    const optionMap = new Map();

    formFields
      .filter((field) => field.key === CONTACT_RANGE_FIELD.key && field.type === 'select')
      .forEach((field) => {
        field.options.forEach((option) => {
          const normalizedValue = `${option.value ?? ''}`.trim();

          if (normalizedValue === '') {
            return;
          }

          const existingOption = optionMap.get(normalizedValue);
          const optionProjects = option.projects.length > 0 ? option.projects : field.projects;
          const mergedProjects = [...new Set([...(existingOption?.projects ?? []), ...optionProjects])];

          optionMap.set(normalizedValue, {
            value: buildRangeOptionValue(normalizedValue, mergedProjects),
            label: `${option.label ?? normalizedValue}`.trim() || normalizedValue,
            submissionValue: normalizedValue,
            projects: mergedProjects,
          });
        });
      });

    return [...optionMap.values()];
  }, [formFields]);

  const hasRangeProjectMapping = useMemo(
    () => rangeFieldOptions.some((option) => option.projects.length > 0),
    [rangeFieldOptions]
  );

  const selectedProjects = useMemo(() => {
    const selectedRange = `${values.rango ?? ''}`.trim();

    if (selectedRange !== '') {
      const selectedRangeOption = rangeFieldOptions.find((option) => option.value === selectedRange);

      if ((selectedRangeOption?.projects?.length ?? 0) > 0) {
        return selectedRangeOption.projects;
      }
    }

    const selectedProjectName = `${values.proyecto ?? ''}`.trim().toLowerCase();

    if (selectedProjectName === '') {
      return [];
    }

    return [selectedProjectName];
  }, [rangeFieldOptions, values.proyecto, values.rango]);

  const comunaFieldOptions = useMemo(() => {
    if (hasRangeProjectMapping && selectedProjects.length === 0) {
      return [];
    }

    const filteredProjects = hasRangeProjectMapping
      ? projectCatalog.filter((project) => selectedProjects.includes(`${project.name ?? ''}`.trim().toLowerCase()))
      : projectCatalog;

    return [...new Map(
      filteredProjects
        .map((project) => `${project.comuna ?? ''}`.trim())
        .filter((comuna) => comuna !== '')
        .map((comuna) => [comuna.toLowerCase(), { value: comuna, label: comuna }])
    ).values()].sort((left, right) => left.label.localeCompare(right.label, 'es', { sensitivity: 'base' }));
  }, [hasRangeProjectMapping, projectCatalog, selectedProjects]);

  const selectedRangeSubmissionValue = useMemo(() => {
    const selectedRange = `${values.rango ?? ''}`.trim();

    if (selectedRange === '') {
      return '';
    }

    return rangeFieldOptions.find((option) => option.value === selectedRange)?.submissionValue || selectedRange;
  }, [rangeFieldOptions, values.rango]);

  const hasSelectedComuna = useMemo(
    () => `${values.comuna ?? ''}`.trim() !== '',
    [values.comuna]
  );

  const hasSelectedRange = useMemo(
    () => `${values.rango ?? ''}`.trim() !== '',
    [values.rango]
  );

  const projectFieldOptions = useMemo(() => {
    if (hasRangeProjectMapping && selectedProjects.length === 0) {
      return [];
    }

    const selectedComuna = `${values.comuna ?? ''}`.trim().toLowerCase();

    if (selectedComuna === '') {
      return [];
    }

    const filteredProjects = hasRangeProjectMapping
      ? projectCatalog.filter((project) => selectedProjects.includes(`${project.name ?? ''}`.trim().toLowerCase()))
      : projectCatalog;

    return [...new Map(
      filteredProjects
        .filter((project) => `${project.comuna ?? ''}`.trim().toLowerCase() === selectedComuna)
        .map((project) => `${project.name ?? ''}`.trim())
        .filter((name) => name !== '')
        .map((name) => [name.toLowerCase(), { value: name, label: name }])
    ).values()].sort((left, right) => left.label.localeCompare(right.label, 'es', { sensitivity: 'base' }));
  }, [hasRangeProjectMapping, projectCatalog, selectedProjects, values.comuna]);

  const activeFormFields = useMemo(() => {
    const renderedSpecialKeys = new Set();
    const renderedConditionalKeys = new Set();

    return formFields.filter((field) => {
      if (field.key === CONTACT_RANGE_FIELD.key || field.key === CONTACT_COMUNA_FIELD.key || field.key === CONTACT_PROJECT_FIELD.key) {
        if (renderedSpecialKeys.has(field.key)) {
          return false;
        }

        renderedSpecialKeys.add(field.key);

        return true;
      }

      const isConditionalField = Array.isArray(field.projects) && field.projects.length > 0;

      if (!isConditionalField) {
        return true;
      }

      if (selectedProjects.length === 0) {
        return false;
      }

      const matchesSelectedProject = field.projects.some((project) => selectedProjects.includes(project));

      if (!matchesSelectedProject) {
        return false;
      }

      if (renderedConditionalKeys.has(field.key)) {
        return false;
      }

      renderedConditionalKeys.add(field.key);

      return true;
    });
  }, [formFields, selectedProjects]);

  useEffect(() => {
    const nextValues = {
      [CONTACT_RANGE_FIELD.key]: '',
      [CONTACT_COMUNA_FIELD.key]: '',
      [CONTACT_PROJECT_FIELD.key]: '',
    };

    formFields.forEach((field) => {
      nextValues[field.key] = '';
    });

    setValues(nextValues);
    setFieldErrors({});
  }, [formFields]);

  useEffect(() => {
    const fetchProjects = async () => {
      try {
        setProjectsLoading(true);

        const response = await proyectosService.getProyectos({
          perPage: 100,
          fields: 'id,name,comuna',
        });

        const items = Array.isArray(response?.data)
          ? response.data
          : Array.isArray(response)
            ? response
            : [];

        setProjectCatalog(
          items
            .map((project) => ({
              id: project?.id ?? '',
              name: `${project?.name ?? ''}`.trim(),
              comuna: `${project?.comuna ?? ''}`.trim(),
            }))
            .filter((project) => project.name !== '')
        );
      } catch {
        setProjectCatalog([]);
      } finally {
        setProjectsLoading(false);
      }
    };

    fetchProjects();
  }, []);

  useEffect(() => {
    if (values.comuna && !comunaFieldOptions.some((option) => option.value === values.comuna)) {
      setValues((current) => ({
        ...current,
        comuna: '',
        proyecto: '',
      }));
    }
  }, [comunaFieldOptions, values.comuna]);

  useEffect(() => {
    if (values.proyecto && !projectFieldOptions.some((option) => option.value === values.proyecto)) {
      setValues((current) => ({
        ...current,
        proyecto: '',
      }));
    }
  }, [projectFieldOptions, values.proyecto]);

  const validateField = (field, candidateValue) => {
    const nextValue = `${candidateValue ?? ''}`.trim();

    if (field.type === 'rut' && nextValue !== '' && !isValidRut(nextValue)) {
      return 'RUT inválido. Revisa el dígito verificador.';
    }

    return '';
  };

  const handleFieldChange = (field, value) => {
    const nextValue = field.type === 'rut' ? formatRut(value) : value;

    setValues((current) => ({
      ...current,
      [field.key]: nextValue,
      ...(field.key === CONTACT_RANGE_FIELD.key ? {
        [CONTACT_COMUNA_FIELD.key]: '',
        [CONTACT_PROJECT_FIELD.key]: '',
      } : {}),
      ...(field.key === CONTACT_COMUNA_FIELD.key ? { [CONTACT_PROJECT_FIELD.key]: '' } : {}),
    }));

    setFieldErrors((current) => {
      const nextErrors = { ...current };
      const validationMessage = validateField(field, nextValue);

      if (validationMessage) {
        nextErrors[field.key] = validationMessage;
      } else {
        delete nextErrors[field.key];
      }

      return nextErrors;
    });
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    setSubmitSuccess('');
    setSubmitError('');
    setSubmitting(true);

    const nextFieldErrors = {};

    activeFormFields.forEach((field) => {
      const validationMessage = validateField(field, values[field.key] || '');

      if (validationMessage) {
        nextFieldErrors[field.key] = validationMessage;
      }
    });

    if (Object.keys(nextFieldErrors).length > 0) {
      trackEvent('form_error', {
        form_name: 'contact',
        reason: 'frontend_validation',
      });

      setFieldErrors(nextFieldErrors);
      setSubmitError('Revisa los campos marcados antes de enviar.');
      setSubmitting(false);

      return;
    }

    try {
      const submissionValues = {
        ...values,
        ...(selectedRangeSubmissionValue !== '' ? { rango: selectedRangeSubmissionValue } : {}),
      };

      await contactSubmissionsService.create({
        ...storedUtmParams,
        ...submissionValues,
      });

      trackEvent('form_submit', {
        form_name: 'contact',
        income_range: selectedRangeSubmissionValue || null,
        commune: values.comuna || null,
        project: values.proyecto || null,
      });

      setSubmitSuccess('Tu mensaje fue enviado correctamente.');
      setFieldErrors({});

      const resetValues = {
        [CONTACT_RANGE_FIELD.key]: '',
        [CONTACT_COMUNA_FIELD.key]: '',
        [CONTACT_PROJECT_FIELD.key]: '',
      };

      formFields.forEach((field) => {
        resetValues[field.key] = '';
      });

      setValues(resetValues);
    } catch (error) {
      const backendErrors = error?.response?.data?.errors || {};
      const nextErrors = {};

      Object.entries(backendErrors).forEach(([key, messages]) => {
        if (!key.startsWith('fields.') || !Array.isArray(messages) || !messages[0]) {
          return;
        }

        nextErrors[key.replace('fields.', '')] = messages[0];
      });

      if (Object.keys(nextErrors).length > 0) {
        setFieldErrors((current) => ({ ...current, ...nextErrors }));
      }

      const message = error?.response?.data?.message || 'No pudimos enviar tu mensaje. Intenta nuevamente.';

      trackEvent('form_error', {
        form_name: 'contact',
        reason: 'backend_response',
        error_message: message,
      });

      setSubmitError(message);
    } finally {
      setSubmitting(false);
    }
  };

  const resolveFieldIcon = (field) => {
    if (field.icon) {
      return field.icon;
    }

    if (field.type === 'rut') {
      return 'id-card';
    }

    if (field.type === 'email') {
      return 'envelope';
    }

    if (field.type === 'tel') {
      return 'phone';
    }

    if (field.type === 'select') {
      return 'chevron-down';
    }

    if(field.type === 'textarea') {
      return null;
    }

    return 'book';
  };

  const renderFieldLabel = (field, includeIcon = true) => {
    const fieldIcon = includeIcon ? resolveFieldIcon(field) : null;

    return (
      <span slot="label" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
        {fieldIcon && <wa-icon name={fieldIcon}></wa-icon>}
        <span>{field.label}</span>
      </span>
    );
  };

  const renderField = (field) => {
    const value = values[field.key] || '';
    const errorMessage = fieldErrors[field.key];
    const fieldIcon = resolveFieldIcon(field);

    if (field.type === 'textarea') {
      return (
        <div key={field.renderKey || field.key} className="wa-stack wa-gap-3xs">
          <wa-textarea
            value={value}
            rows="5"
            placeholder={field.placeholder || undefined}
            required={field.required}
            disabled={field.disabled}
            onInput={(event) => handleFieldChange(field, event.target.value || '')}
          >
            {renderFieldLabel(field)}
          </wa-textarea>

          {errorMessage && <small className="wa-color-danger">{errorMessage}</small>}
        </div>
      );
    }

    if (field.type === 'select') {
      return (
        <div key={field.renderKey || field.key} className="wa-stack wa-gap-3xs">
          <wa-select
            value={value}
            placeholder={field.placeholder || 'Selecciona una opción'}
            required={field.required}
            disabled={field.disabled}
            clearable={!field.required}
            onChange={(event) => handleFieldChange(field, event.target.value || '')}
          >
            {renderFieldLabel(field)}
            {field.options.map((option) => (
              <wa-option key={`${field.renderKey || field.key}-${option.value}`} value={option.value}>
                {fieldIcon && <wa-icon name={fieldIcon} slot="start"></wa-icon>}
                {option.label}
              </wa-option>
            ))}
          </wa-select>

          {errorMessage && <small className="wa-color-danger">{errorMessage}</small>}
        </div>
      );
    }

    const inputType = field.type === 'email' ? 'email' : field.type === 'number' ? 'number' : field.type === 'tel' ? 'tel' : 'text';

    return (
      <div key={field.renderKey || field.key} className="wa-stack wa-gap-3xs">
        <wa-input
          type={inputType}
          value={value}
          placeholder={field.placeholder || undefined}
          required={field.required}
          disabled={field.disabled}
          input-icon={field.type !== 'tel' && fieldIcon ? fieldIcon : undefined}
          pattern={field.type === 'rut' ? '^[0-9]{7,8}-[0-9Kk]$' : undefined}
          maxlength={field.type === 'rut' ? '10' : field.type === 'tel' ? '9' : undefined}
          onInput={(event) => handleFieldChange(field, event.target.value || '')}
        >
            {field.type === 'tel' ? (
              <div slot="start">
                <span>+56</span>
              </div>
            ) : null}
            {renderFieldLabel(field)}
        </wa-input>

        {field.type === 'rut' && (
          <small className={errorMessage ? 'wa-color-danger' : 'wa-color-text-quiet'}>
            {errorMessage || 'Formato: 12345678-5'}
          </small>
        )}

        {field.type !== 'rut' && errorMessage && <small className="wa-color-danger">{errorMessage}</small>}
      </div>
    );
  };

  const isContactLoading = configLoading || projectsLoading || !config;

  if (isContactLoading) {
    return (
      <div className="contact-page">
        <SiteHeader config={config} currentPath={currentPath} onNavigate={onNavigate} />

        <section className="contact-hero home-container">
          <wa-card appearance="filled" className="contact-hero-card">
            <div className="wa-stack wa-gap-s">
              <wa-skeleton effect="pulse" style={{ height: '2.5rem', width: '16rem' }}></wa-skeleton>
              <wa-skeleton effect="pulse" style={{ height: '1rem', width: '24rem', maxWidth: '100%' }}></wa-skeleton>
            </div>
          </wa-card>
        </section>

        <section className="home-container contact-grid">
          <wa-card appearance="outlined" className="contact-content-card">
            <div className="wa-stack wa-gap-m">
              <wa-skeleton effect="pulse" style={{ height: '1.75rem', width: '10rem' }}></wa-skeleton>
              <wa-skeleton effect="pulse" style={{ height: '1rem', width: '100%' }}></wa-skeleton>
              <wa-skeleton effect="pulse" style={{ height: '1rem', width: '82%' }}></wa-skeleton>

              <div className="wa-stack wa-gap-s contact-form">
                {[...Array(5)].map((_, index) => (
                  <wa-skeleton
                    key={`contact-skeleton-${index}`}
                    effect="pulse"
                    style={{ height: index === 4 ? '6rem' : '3rem', width: '100%' }}
                  ></wa-skeleton>
                ))}

                <wa-skeleton effect="pulse" style={{ height: '2.75rem', width: '10rem' }}></wa-skeleton>
              </div>
            </div>
          </wa-card>

          <wa-card appearance="filled" className="contact-info-card">
            <div className="wa-stack wa-gap-s">
              <wa-skeleton effect="pulse" style={{ height: '1.75rem', width: '8rem' }}></wa-skeleton>
              <wa-skeleton effect="pulse" style={{ height: '1rem', width: '75%' }}></wa-skeleton>
              <wa-skeleton effect="pulse" style={{ height: '1rem', width: '65%' }}></wa-skeleton>
              <wa-skeleton effect="pulse" style={{ height: '1rem', width: '85%' }}></wa-skeleton>
            </div>
          </wa-card>
        </section>
      </div>
    );
  }

  return (
    <div className="contact-page">
      <SiteHeader config={config} currentPath={currentPath} onNavigate={onNavigate} />

        {/* Hero section */}
      <section className="contact-hero home-container">
        {contactHeroDesktopImage ? (
          <picture className="contact-hero-picture">
            <source media="(max-width: 768px)" srcSet={contactHeroMobileImage || contactHeroDesktopImage} />
            <img src={contactHeroDesktopImage} alt={config?.hero?.contact?.alt || 'Contacto'} className="contact-hero-image" />
          </picture>
        ) : (
          <wa-card appearance="filled" className="contact-hero-card">
            <div className="wa-stack wa-gap-s">
              <h1>{title}</h1>
              <p>{subtitle}</p>
            </div>
          </wa-card>
        )}
      </section>

      <section className="home-container contact-grid">
        <wa-card appearance="outlined" className="contact-content-card">
          <div className="wa-stack wa-gap-m">
            <h1>{title}</h1>
            <p>{subtitle}</p>
            {content ? (
              <div dangerouslySetInnerHTML={{ __html: content }} />
            ) : (
              <p>Pronto publicaremos toda la información de contacto.</p>
            )}

            <form className="contact-form wa-stack wa-gap-s" onSubmit={handleSubmit}>

              {Object.entries(storedUtmParams).map(([key, value]) => (
                <input key={key} type="hidden" name={key} value={value || ''} readOnly />
              ))}

              {activeFormFields.map((field) => {
                if (field.key === CONTACT_RANGE_FIELD.key) {
                  return renderField({
                    ...field,
                    options: rangeFieldOptions.map((option) => ({ value: option.value, label: option.label })),
                    icon: configuredRangeField.icon || CONTACT_RANGE_FIELD.icon,
                    placeholder: field.placeholder || CONTACT_RANGE_FIELD.placeholder,
                  });
                }

                if (field.key === CONTACT_COMUNA_FIELD.key) {
                  return renderField({
                    ...field,
                    options: comunaFieldOptions,
                    icon: configuredComunaField.icon || CONTACT_COMUNA_FIELD.icon,
                    disabled: !hasSelectedRange || comunaFieldOptions.length === 0,
                    placeholder: hasSelectedRange
                      ? (field.placeholder || CONTACT_COMUNA_FIELD.placeholder)
                      : 'Selecciona primero un rango de renta',
                  });
                }

                if (field.key === CONTACT_PROJECT_FIELD.key) {
                  return renderField({
                    ...field,
                    options: projectFieldOptions,
                    icon: configuredProjectField.icon || CONTACT_PROJECT_FIELD.icon,
                    disabled: !hasSelectedComuna || projectFieldOptions.length === 0,
                    placeholder: hasSelectedComuna
                      ? (field.placeholder || CONTACT_PROJECT_FIELD.placeholder)
                      : 'Selecciona primero una comuna',
                  });
                }

                return renderField(field);
              })}

              {!hasConfiguredRangeField && renderField({
                ...CONTACT_RANGE_FIELD,
                options: rangeFieldOptions.map((option) => ({ value: option.value, label: option.label })),
              })}

              {!hasConfiguredComunaField && renderField({
                ...CONTACT_COMUNA_FIELD,
                options: comunaFieldOptions,
                disabled: !hasSelectedRange || comunaFieldOptions.length === 0,
                placeholder: hasSelectedRange
                  ? CONTACT_COMUNA_FIELD.placeholder
                  : 'Selecciona primero un rango de renta',
              })}

              {!hasConfiguredProjectField && renderField({
                ...CONTACT_PROJECT_FIELD,
                options: projectFieldOptions,
                disabled: !hasSelectedComuna || projectFieldOptions.length === 0,
                placeholder: hasSelectedComuna
                  ? CONTACT_PROJECT_FIELD.placeholder
                  : 'Selecciona primero una comuna',
              })}

              {submitSuccess && (
                <wa-callout variant="success">
                  <wa-icon slot="icon" name="circle-check"></wa-icon>
                  {submitSuccess}
                </wa-callout>
              )}

              {submitError && (
                <wa-callout variant="danger">
                  <wa-icon slot="icon" name="triangle-exclamation"></wa-icon>
                  {submitError}
                </wa-callout>
              )}

              <wa-button type="submit" variant="brand" disabled={submitting}>
                <wa-icon name="paper-plane" slot="start"></wa-icon>
                {submitting ? 'Enviando...' : 'Enviar mensaje'}
              </wa-button>
            </form>
          </div>
        </wa-card>

        <wa-card appearance="filled" className="contact-info-card">
          <div className="wa-stack wa-gap-s">
            <h2>Información</h2>

            {config?.contact?.email && (
              <a href={`mailto:${config.contact.email}`} className="contact-link wa-font-size-sm">
                <wa-icon name="envelope"></wa-icon>
                {config.contact.email}
              </a>
            )}

            {config?.contact?.phone && (
              <a href={`tel:${config.contact.phone}`} className="contact-link wa-font-size-sm">
                <wa-icon name="phone"></wa-icon>
                {config.contact.phone}
              </a>
            )}

            {config?.contact?.address && (
              <div className="contact-link contact-address wa-font-size-sm">
                <wa-icon name="location-dot"></wa-icon>
                <span>{config.contact.address}</span>
              </div>
            )}

            {socialLinks.length > 0 && (
              <div className="wa-stack wa-gap-xs">
                <span className="wa-color-text-quiet">Redes sociales</span>
                <div className="wa-cluster wa-gap-xs">
                  {socialLinks.map((socialItem) => (
                    <wa-button
                      appearance="filled"
                      key={socialItem.key}
                      href={socialItem.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      aria-label={socialItem.label}
                      pill
                    >
                      <wa-icon name={socialItem.icon} family="brands"></wa-icon>
                    </wa-button>
                  ))}
                </div>
              </div>
            )}

            {/* <wa-button variant="brand" onClick={() => onNavigate?.('/')}>
              Volver al Home
            </wa-button> */}
          </div>
        </wa-card>
      </section>

      <SiteFooter config={config} onNavigate={onNavigate} />
    </div>
  );
}

export default Contact;
