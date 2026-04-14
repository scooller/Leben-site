import { createContext, useContext, useState, useEffect, useRef } from 'react';
import siteConfigService from '../services/siteConfig';
import WebAwesomeService from '../services/webAwesome';import { initializeTagManager } from '../utils/tagManager';
export const SiteConfigContext = createContext(null);

const COLOR_MODE_STORAGE_KEY = 'ileben-color-mode';

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

export const SiteConfigProvider = ({ children }) => {
  const [config, setConfig] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [colorMode, setColorModeState] = useState(resolveInitialColorMode);
  const hasLoadedConfig = useRef(false);

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

  useEffect(() => {
    // Prevenir doble ejecución en React StrictMode
    if (hasLoadedConfig.current) return;
    hasLoadedConfig.current = true;

    applyColorModeToDocument(resolveInitialColorMode());

    loadConfig();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const loadConfig = async () => {
    try {
      setLoading(true);
      const data = await siteConfigService.getConfig();
      setConfig(data);

      // Aplicar configuración al documento
      if (data.site_name) {
        siteConfigService.setTitle(data.site_name);
      }

      // Aplicar tema predefinido de Web Awesome
      // Los colores semánticos (brand, success, warning, danger, neutral) están definidos por el tema
      const theme = data.webawesome_theme || 'mellow';
      await WebAwesomeService.applyPrebuiltTheme(theme);

      // Aplicar paleta de colores
      // Define los tonos y matices específicos de los colores
      const palette = data.webawesome_palette || 'natural';
      WebAwesomeService.applyPalette(palette);

      // Aplicar color principal de marca en :root
      WebAwesomeService.applyBrandColor(data.brand_color || '#eb0029');

      // Aplicar colores semánticos específicos (wa-brand-blue, wa-success-green, etc.)
      WebAwesomeService.applySemanticColors({
        semantic_brand_color: data.semantic_brand_color || 'blue',
        semantic_neutral_color: data.semantic_neutral_color || 'gray',
        semantic_success_color: data.semantic_success_color || 'green',
        semantic_warning_color: data.semantic_warning_color || 'yellow',
        semantic_danger_color: data.semantic_danger_color || 'red',
      });

      // Aplicar familia de iconos (data-font-family en HTML)
      const iconFamily = data.icon_family || 'classic';
      document.documentElement.setAttribute('data-font-family', iconFamily);

      // Cargar stylesheet de Google Fonts si existe
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

      // Aplicar tipografía personalizada (Google Fonts u otras fuentes)
      if (data.font_family_body || data.font_family_heading) {
        WebAwesomeService.applyFonts({
          font_family_body: data.font_family_body,
          font_family_heading: data.font_family_heading,
        });
      }

      if (data.custom_css) {
        siteConfigService.injectCustomCSS(data.custom_css);
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

      if (data?.seo?.tag_manager_id) {
        initializeTagManager(data.seo.tag_manager_id);
      }

      setError(null);
    } catch (err) {
      setError(err);
    } finally {
      setLoading(false);
      console.log('Site configuration loaded and applied successfully.');
    }
  };

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
