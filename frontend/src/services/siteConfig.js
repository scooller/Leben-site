import api from '../lib/api';

/**
 * Servicio para obtener la configuración del sitio
 */
class SiteConfigService {
  constructor() {
    this.config = null;
    this.loading = false;
  }

  /**
   * Obtener la configuración del sitio desde la API
   * @returns {Promise<Object>}
   */
  async getConfig() {
    if (this.config) {
      return this.config;
    }

    this.loading = true;
    try {
      const response = await api.get('/site-config');
      this.config = response.data;
      return this.config;
    } finally {
      this.loading = false;
    }
  }

  /**
   * Limpiar caché de configuración
   */
  clearCache() {
    this.config = null;
  }

  /**
   * Aplicar tema al documento
   * @param {Object} theme - Objeto con los colores del tema
   */
  applyTheme(theme) {
    if (!theme) return;

    const root = document.documentElement;
    if (theme.primary_color) root.style.setProperty('--color-primary', theme.primary_color);
    if (theme.secondary_color) root.style.setProperty('--color-secondary', theme.secondary_color);
    if (theme.accent_color) root.style.setProperty('--color-accent', theme.accent_color);
    if (theme.background_color) root.style.setProperty('--color-background', theme.background_color);
    if (theme.text_color) root.style.setProperty('--color-text', theme.text_color);
  }

  /**
   * Inyectar CSS personalizado
   * @param {string} css - CSS personalizado
   */
  injectCustomCSS(css) {
    if (!css) return;

    const styleId = 'site-custom-css';
    let styleElement = document.getElementById(styleId);

    if (!styleElement) {
      styleElement = document.createElement('style');
      styleElement.id = styleId;
      document.head.appendChild(styleElement);
    }

    styleElement.textContent = css;
  }

  /**
   * Establecer favicon
   * @param {string} faviconUrl - URL del favicon
   */
  setFavicon(faviconUrl) {
    if (!faviconUrl) return;

    let link = document.querySelector("link[rel~='icon']");
    if (!link) {
      link = document.createElement('link');
      link.rel = 'icon';
      document.head.appendChild(link);
    }
    link.href = faviconUrl;
  }

  /**
   * Establecer título del sitio
   * @param {string} title - Título del sitio
   */
  setTitle(title) {
    if (title) {
      document.title = title;
    }
  }

  /**
   * Establecer meta tags
   * @param {Object} seo - Objeto con información SEO
   */
  setMetaTags(seo) {
    if (!seo) return;

    // Meta description
    if (seo.site_description) {
      this.setMetaTag('description', seo.site_description);
    }

    // Meta keywords
    if (seo.meta_keywords) {
      this.setMetaTag('keywords', seo.meta_keywords);
    }

    // Meta author
    if (seo.meta_author) {
      this.setMetaTag('author', seo.meta_author);
    }

    // Open Graph image
    if (seo.og_image) {
      this.setMetaTag('og:image', seo.og_image, 'property');
    }
  }

  /**
   * Establecer un meta tag
   * @param {string} name - Nombre del meta tag
   * @param {string} content - Contenido del meta tag
   * @param {string} attribute - Atributo (name o property)
   */
  setMetaTag(name, content, attribute = 'name') {
    if (!content) return;

    let meta = document.querySelector(`meta[${attribute}="${name}"]`);
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute(attribute, name);
      document.head.appendChild(meta);
    }
    meta.setAttribute('content', content);
  }
}

export const siteConfigService = new SiteConfigService();
export default siteConfigService;
