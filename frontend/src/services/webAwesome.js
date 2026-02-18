/**
 * Servicio para inicializar Web Awesome y aplicar temas dinámicos
 * https://webawesome.com/docs/themes
 * https://webawesome.com/docs/color-palettes
 * 
 * Web Awesome soporta personalización mediante CSS Custom Properties (Design Tokens)
 */

class WebAwesomeService {
  /**
   * Inicializar Web Awesome
   * Debe llamarse en el componente raíz (App.jsx) dentro de useEffect
   */
  static init() {
    return new Promise((resolve, reject) => {
      // Evitar inicializar dos veces
      if (window.WebAwesome) {
        console.log('✓ Web Awesome ya está cargado');
        resolve();
        return;
      }

      // Cargar el loader de Web Awesome
      const script = document.createElement('script');
      script.type = 'module';
      script.src = '/webawesome/dist-cdn/webawesome.loader.js';
      script.setAttribute('data-webawesome', '/webawesome/dist-cdn');
      script.onload = () => {
        console.log('✓ Web Awesome cargado correctamente');
        resolve();
      };
      script.onerror = () => {
        console.error('✗ Error al cargar Web Awesome');
        reject(new Error('Failed to load Web Awesome'));
      };
      document.head.appendChild(script);
    });
  }

  /**
   * Cargar stylesheet de Web Awesome
   */
  static loadStylesheet() {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/webawesome/dist-cdn/styles/webawesome.css';
    document.head.appendChild(link);
  }

  /**
   * Aplicar tema personalizado usando colores del backend
   * 
   * Web Awesome Design Tokens (CSS Custom Properties):
   * - --wa-color-primary: Color primario
   * - --wa-color-secondary: Color secundario
   * - --wa-color-accent: Color de acento
   * - --wa-color-surface-base: Color de fondo
   * - --wa-color-text-base: Color de texto
   * - --wa-color-success, warning, danger: Colores semánticos
   * 
   * @param {Object} colors - Objeto con colores del backend
   * @param {string} colors.primary_color - Color primario (ej: #667eea)
   * @param {string} colors.secondary_color - Color secundario (ej: #764ba2)
   * @param {string} colors.accent_color - Color de acento
   * @param {string} colors.background_color - Color de fondo
   * @param {string} colors.text_color - Color de texto
   */
  static applyTheme(colors = {}) {
    if (!colors || Object.keys(colors).length === 0) {
      console.warn('⚠ Colores vacíos, usando valores por defecto');
      colors = this.getDefaultTheme();
    }

    const htmlElement = document.documentElement;

    // Map frontend colors to Web Awesome design tokens
    const tokens = {
      '--wa-color-primary': colors.primary_color || '#667eea',
      '--wa-color-secondary': colors.secondary_color || '#764ba2',
      '--wa-color-accent': colors.accent_color || '#f59e0b',
      '--wa-color-text-base': colors.text_color || '#1f2937',
      '--wa-color-surface-base': colors.background_color || '#ffffff',
    };

    // Aplicar tokens a :root
    Object.entries(tokens).forEach(([token, value]) => {
      htmlElement.style.setProperty(token, value);
    });

    console.log('✓ Tema Web Awesome aplicado:', tokens);
  }

  /**
   * Obtener tema por defecto (valores iniciales)
   */
  static getDefaultTheme() {
    return {
      primary_color: '#667eea',
      secondary_color: '#764ba2',
      accent_color: '#f59e0b',
      background_color: '#ffffff',
      text_color: '#1f2937',
    };
  }

  /**
   * Aplicar tema predefinido de Web Awesome como clase en <html>
   * Opciones: 'default', 'awesome', 'shoelace'
   * 
   * @param {string} themeName - Nombre del tema predefinido
   */
  static applyPrebuiltTheme(themeName = 'awesome') {
    const htmlElement = document.documentElement;
    
    // Remover clases de temas anteriores
    htmlElement.classList.remove('default', 'awesome', 'shoelace');
    
    // Agregar nueva clase de tema
    if (['default', 'awesome', 'shoelace'].includes(themeName)) {
      htmlElement.classList.add(themeName);
      console.log(`✓ Tema Web Awesome "${themeName}" aplicado`);
    } else {
      console.warn(`⚠ Tema desconocido: ${themeName}`);
    }
  }

  /**
   * Verificar si Web Awesome está disponible
   */
  static isAvailable() {
    return !!window.WebAwesome;
  }
}

export default WebAwesomeService;
