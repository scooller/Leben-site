import { createContext, useContext, useState, useEffect, useRef, useCallback } from 'react';
import siteConfigService from '../services/siteConfig';
import WebAwesomeService from '../services/webAwesome';
import { initializeFacebookPixel, initializeTagManager } from '../utils/tagManager';
import { setUtmDefaultOverrides } from '../utils/utmSession';

export const SiteConfigContext = createContext(null);

const COLOR_MODE_STORAGE_KEY = 'ileben-color-mode';

const getSystemColorMode = () => {
  if (typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    return 'dark';
  }
  return 'light';
};

const resolveColorMode = (defaultColorMode = 'system') => {
  if (typeof window === 'undefined') {
    return 'dark';
  }

  const storedMode = window.localStorage.getItem(COLOR_MODE_STORAGE_KEY);

  if (storedMode === 'light' || storedMode === 'dark') {
    return storedMode;
  }

  if (defaultColorMode === 'system') {
    return getSystemColorMode();
  }

  return defaultColorMode === 'light' ? 'light' : 'dark';
};

const runWhenBrowserIdle = (callback, timeout = 1200) => {
  if (typeof window === 'undefined') {
    callback();
    return () => {};
  }

  if (typeof window.requestIdleCallback === 'function') {
    const idleHandle = window.requestIdleCallback(callback, { timeout });

    return () => {
      if (typeof window.cancelIdleCallback === 'function') {
        window.cancelIdleCallback(idleHandle);
      }
    };
  }

  const timer = window.setTimeout(callback, 450);

  return () => {
    window.clearTimeout(timer);
  };
};

export const SiteConfigProvider = ({ children }) => {
  const [config, setConfig] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [colorMode, setColorModeState] = useState(() => resolveColorMode('system'));
  const hasLoadedConfig = useRef(false);
  const cancelDeferredSetupRef = useRef(null);

  const applyColorModeToDocument = (mode) => {
    const htmlElement = document.documentElement;

    htmlElement.classList.remove('wa-light', 'wa-dark');
    htmlElement.classList.add(mode === 'light' ? 'wa-light' : 'wa-dark');
  };

  const setColorMode = (mode) => {
    const nextMode = mode === 'light' ? 'light' : 'dark';

    setColorModeState(nextMode);

    if (typeof window !== 'undefined') {
      window.localStorage.setItem(COLOR_MODE_STORAGE_KEY, nextMode);
    }

    applyColorModeToDocument(nextMode);
  };

  const toggleColorMode = () => {
    setColorMode(colorMode === 'dark' ? 'light' : 'dark');
  };

  const loadConfig = useCallback(async (forceRefresh = false) => {
    try {
      setLoading(true);

      if (cancelDeferredSetupRef.current) {
        cancelDeferredSetupRef.current();
        cancelDeferredSetupRef.current = null;
      }

      const data = await siteConfigService.getConfig(forceRefresh);
      setConfig(data);

      const storedUserMode = typeof window !== 'undefined' ? window.localStorage.getItem(COLOR_MODE_STORAGE_KEY) : null;
      if (!storedUserMode || (storedUserMode !== 'light' && storedUserMode !== 'dark')) {
        const resolved = resolveColorMode(data?.default_color_mode || 'system');
        setColorModeState(resolved);
        applyColorModeToDocument(resolved);
      }

      // Aplicar configuración al documento
      if (data.site_name) {
        siteConfigService.setTitle(data.site_name);
      }

      if (data.favicon) {
        siteConfigService.setFavicon(data.favicon);
      }

      if (data.site_description || data.seo) {
        siteConfigService.setMetaTags({
          site_description: data.site_description,
          ...data.seo,
        });
      }

      const isSale = Boolean(data?.evento_sale);
      const currentChannelSlug = (typeof window !== 'undefined' && window.location)
        ? (new URLSearchParams(window.location.search).get('channel') || 'sale')
        : 'sale';
      const allowedChannelSlugs = Array.isArray(data?.seo?.sale_utm_campaign_channel_slugs)
        ? data.seo.sale_utm_campaign_channel_slugs
        : null;
      const isChannelEligible = !allowedChannelSlugs || allowedChannelSlugs.includes(currentChannelSlug);

      const saleCampaignOverride = isSale && isChannelEligible
        ? (data?.seo?.sale_campaign_override || data?.seo?.sale_event?.utm_campaign || data?.seo?.sale_utm_campaign || null)
        : null;

      const saleUtmCampaign = isSale
        ? (data?.seo?.sale_utm_campaign || data?.seo?.sale_campaign_override || data?.seo?.sale_event?.utm_campaign || null)
        : null;

      setUtmDefaultOverrides(
        {
          utm_source: data?.seo?.utm_source_default,
          utm_medium: data?.seo?.utm_medium_default,
          utm_campaign: data?.seo?.utm_campaign_default,
          utm_term: data?.seo?.utm_term_default,
          utm_content: data?.seo?.utm_content_default,
          utm_site: data?.seo?.utm_site_default,
        },
        {
          isSaleEvent: isSale,
          saleCampaignOverride,
          saleUtmCampaign,
        }
      );

      setError(null);

      cancelDeferredSetupRef.current = runWhenBrowserIdle(async () => {
        try {
          const theme = data.webawesome_theme || 'mellow';
          const palette = data.webawesome_palette || 'natural';

          await WebAwesomeService.applyPrebuiltTheme(theme);
          await WebAwesomeService.applyPalette(palette);
          WebAwesomeService.applyBrandColor(data.brand_color || '#eb0029');
          WebAwesomeService.applySemanticColors({
            semantic_brand_color: data.semantic_brand_color || 'blue',
            semantic_neutral_color: data.semantic_neutral_color || 'gray',
            semantic_success_color: data.semantic_success_color || 'green',
            semantic_warning_color: data.semantic_warning_color || 'yellow',
            semantic_danger_color: data.semantic_danger_color || 'red',
          });

          const iconFamily = data.icon_family || 'classic';
          document.documentElement.setAttribute('data-font-family', iconFamily);

          if (data.google_fonts_stylesheet) {
            const linkId = 'google-fonts-stylesheet';
            let link = document.getElementById(linkId);

            if (!link) {
              link = document.createElement('link');
              link.id = linkId;
              link.rel = 'stylesheet';
              document.head.appendChild(link);
            }

            link.href = data.google_fonts_stylesheet;
          }

          if (data.font_family_body || data.font_family_heading) {
            WebAwesomeService.applyFonts({
              font_family_body: data.font_family_body,
              font_family_heading: data.font_family_heading,
            });
          }

          if (data.custom_css) {
            siteConfigService.injectCustomCSS(data.custom_css);
          }

          if (!window.__ilebenHeaderScriptsLoaded) {
            siteConfigService.injectHeaderScripts(data.header_scripts);
          }

          siteConfigService.injectFooterScripts(data.footer_scripts);

          if (data?.seo?.tag_manager_id) {
            initializeTagManager(data.seo.tag_manager_id);
          }

          const facebookPixelId = data?.seo?.facebook_pixel_id || data?.seo?.meta_pixel_id;

          if (facebookPixelId) {
            initializeFacebookPixel(facebookPixelId);
          }
        } catch (deferredError) {
          console.error('[SiteConfig] Error aplicando configuracion diferida', deferredError);
        }
      });
    } catch (err) {
      setError(err);
      console.error('[SiteConfig] Error cargando configuracion', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    // Prevenir doble ejecución en React StrictMode
    if (hasLoadedConfig.current) return;
    hasLoadedConfig.current = true;

    applyColorModeToDocument(resolveColorMode('system'));

    loadConfig();
  }, [loadConfig]);

  useEffect(() => {
    if (typeof window === 'undefined' || !window.matchMedia) {
      return;
    }

    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const handleSystemChange = (event) => {
      const stored = window.localStorage.getItem(COLOR_MODE_STORAGE_KEY);
      if (!stored && (config?.default_color_mode ?? 'system') === 'system') {
        const mode = event.matches ? 'dark' : 'light';
        setColorModeState(mode);
        applyColorModeToDocument(mode);
      }
    };

    if (mediaQuery.addEventListener) {
      mediaQuery.addEventListener('change', handleSystemChange);
      return () => mediaQuery.removeEventListener('change', handleSystemChange);
    }

    mediaQuery.addListener(handleSystemChange);
    return () => mediaQuery.removeListener(handleSystemChange);
  }, [config?.default_color_mode]);

  useEffect(() => {
    return () => {
      if (cancelDeferredSetupRef.current) {
        cancelDeferredSetupRef.current();
      }
    };
  }, []);

  const value = {
    config,
    loading,
    error,
    colorMode,
    showThemeToggle: Boolean(config?.show_theme_toggle),
    setColorMode,
    toggleColorMode,
    reload: loadConfig,
  };

  return (
    <SiteConfigContext.Provider value={value}>
      {children}
    </SiteConfigContext.Provider>
  );
};

export const useSiteConfig = () => {
  const context = useContext(SiteConfigContext);
  if (!context) {
    throw new Error('useSiteConfig must be used within a SiteConfigProvider');
  }
  return context;
};
