import { createContext, useContext, useState, useEffect, useRef, useCallback } from 'react';
import siteConfigService from '../services/siteConfig';
import { initializeFacebookPixel, initializeTagManager } from '../utils/tagManager';
import { setUtmDefaultOverrides } from '../utils/utmSession';

export const SiteConfigContext = createContext(null);

const COLOR_MODE_STORAGE_KEY = 'ileben-color-mode';
let webAwesomeServicePromise = null;

const getWebAwesomeService = async () => {
  if (!webAwesomeServicePromise) {
    webAwesomeServicePromise = import('../services/webAwesome').then((module) => module.default);
  }

  return webAwesomeServicePromise;
};

const resolveInitialColorMode = () => {
  if (typeof window === 'undefined') {
    return 'dark';
  }

  const storedMode = window.localStorage.getItem(COLOR_MODE_STORAGE_KEY);

  if (storedMode === 'light' || storedMode === 'dark') {
    return storedMode;
  }

  return 'dark';
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
  const [colorMode, setColorModeState] = useState(resolveInitialColorMode);
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

      setUtmDefaultOverrides({
        utm_campaign: data?.seo?.utm_campaign_default,
      });

      setError(null);

      cancelDeferredSetupRef.current = runWhenBrowserIdle(async () => {
        try {
          const WebAwesomeService = await getWebAwesomeService();
          const theme = data.webawesome_theme || 'mellow';
          const palette = data.webawesome_palette || 'natural';

          await WebAwesomeService.applyPrebuiltTheme(theme);
          WebAwesomeService.applyPalette(palette);
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

    applyColorModeToDocument(resolveInitialColorMode());

    loadConfig();
  }, [loadConfig]);

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
