import { createContext, useContext, useState, useEffect } from 'react';
import siteConfigService from '../services/siteConfig';
import WebAwesomeService from '../services/webAwesome';

export const SiteConfigContext = createContext(null);

export const SiteConfigProvider = ({ children }) => {
  const [config, setConfig] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    loadConfig();
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

      // Aplicar tema de Web Awesome con los colores del backend
      WebAwesomeService.applyTheme({
        primary_color: data.primary_color,
        secondary_color: data.secondary_color,
        accent_color: data.accent_color,
        background_color: data.background_color,
        text_color: data.text_color,
      });

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

      setError(null);
    } catch (err) {
      console.error('Error loading site config:', err);
      setError(err);
    } finally {
      setLoading(false);
    }
  };

  const value = {
    config,
    loading,
    error,
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
