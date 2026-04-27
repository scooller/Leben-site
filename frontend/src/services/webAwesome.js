/**
 * Servicio para inicializar Web Awesome y aplicar temas dinÃ¡micos
 * https://webawesome.com/docs/themes
 * https://webawesome.com/docs/color-palettes
 *
 * Web Awesome soporta personalizaciÃ³n mediante CSS Custom Properties (Design Tokens)
 */
// Import Web Awesome Pro base utilities and styles
import { setBasePath } from '@web.awesome.me/webawesome-pro/dist/webawesome.js'
import '@web.awesome.me/webawesome-pro/dist/styles/webawesome.css'

// Configurar basePath para que Web Awesome encuentre assets (Font Awesome icons, etc.)
// SegÃºn documentaciÃ³n oficial: https://webawesome.com/docs/installation#setting-the-base-path
// Cuando usas bundler, debes configurar explÃ­citamente la ruta a los assets
//
// Opciones para Vite:
// - En desarrollo: Vite sirve desde node_modules directamente
// - En producciÃ³n: Assets se copian a dist/assets/
//
// Vite copia automÃ¡ticamente los assets de Web Awesome al build,
// pero necesitamos decirle a Web Awesome dÃ³nde buscarlos.
// Usamos import.meta.env para detectar el entorno
if (import.meta.env.DEV) {
  // Desarrollo: apuntar a node_modules
  setBasePath('/node_modules/@web.awesome.me/webawesome-pro/dist')
} else {
  // Producción: Vite copiará los assets, intentar auto-detección
  // Si los iconos no cargan, ajustar esta ruta manualmente
  setBasePath('/assets')
}

// Importar componentes que se usan en la aplicación
// Según la documentación oficial, cada componente debe importarse explícitamente
// https://webawesome.com/docs/installation
import '@web.awesome.me/webawesome-pro/dist/components/animation/animation.js'
import '@web.awesome.me/webawesome-pro/dist/components/badge/badge.js'
import '@web.awesome.me/webawesome-pro/dist/components/button/button.js'
import '@web.awesome.me/webawesome-pro/dist/components/button-group/button-group.js'
import '@web.awesome.me/webawesome-pro/dist/components/callout/callout.js'
import '@web.awesome.me/webawesome-pro/dist/components/card/card.js'
import '@web.awesome.me/webawesome-pro/dist/components/details/details.js'
import '@web.awesome.me/webawesome-pro/dist/components/dialog/dialog.js'
import '@web.awesome.me/webawesome-pro/dist/components/drawer/drawer.js'
import '@web.awesome.me/webawesome-pro/dist/components/divider/divider.js'
import '@web.awesome.me/webawesome-pro/dist/components/file-input/file-input.js'
import '@web.awesome.me/webawesome-pro/dist/components/icon/icon.js'
import '@web.awesome.me/webawesome-pro/dist/components/input/input.js'
import '@web.awesome.me/webawesome-pro/dist/components/option/option.js'
import '@web.awesome.me/webawesome-pro/dist/components/radio/radio.js'
import '@web.awesome.me/webawesome-pro/dist/components/radio-group/radio-group.js'
import '@web.awesome.me/webawesome-pro/dist/components/select/select.js'
import '@web.awesome.me/webawesome-pro/dist/components/scroller/scroller.js'
import '@web.awesome.me/webawesome-pro/dist/components/tag/tag.js'
import '@web.awesome.me/webawesome-pro/dist/components/textarea/textarea.js'
import '@web.awesome.me/webawesome-pro/dist/components/tooltip/tooltip.js'
import '@web.awesome.me/webawesome-pro/dist/components/toast/toast.js'
import '@web.awesome.me/webawesome-pro/dist/components/skeleton/skeleton.js'

const themeImports = {
  default: () => import('@web.awesome.me/webawesome-pro/dist/styles/themes/default.css'),
  awesome: () => import('@web.awesome.me/webawesome-pro/dist/styles/themes/awesome.css'),
  shoelace: () => import('@web.awesome.me/webawesome-pro/dist/styles/themes/shoelace.css'),
  active: () => import('@web.awesome.me/webawesome-pro/dist/styles/themes/active.css'),
  brutalist: () => import('@web.awesome.me/webawesome-pro/dist/styles/themes/brutalist.css'),
  glossy: () => import('@web.awesome.me/webawesome-pro/dist/styles/themes/glossy.css'),
  matter: () => import('@web.awesome.me/webawesome-pro/dist/styles/themes/matter.css'),
  mellow: () => import('@web.awesome.me/webawesome-pro/dist/styles/themes/mellow.css'),
  playful: () => import('@web.awesome.me/webawesome-pro/dist/styles/themes/playful.css'),
  premium: () => import('@web.awesome.me/webawesome-pro/dist/styles/themes/premium.css'),
  tailspin: () => import('@web.awesome.me/webawesome-pro/dist/styles/themes/tailspin.css'),
}

class WebAwesomeService {
  static themePromise = null;
  static currentTheme = null;
  static customBrandTokenProps = [
    '--wa-color-brand',
    '--wa-color-brand-fill-quiet',
    '--wa-color-brand-fill-normal',
    '--wa-color-brand-fill-loud',
    '--wa-color-brand-border-quiet',
    '--wa-color-brand-border-normal',
    '--wa-color-brand-border-loud',
    '--wa-color-brand-on-quiet',
    '--wa-color-brand-on-normal',
    '--wa-color-brand-on-loud',
    '--wa-color-text-link',
    '--wa-color-focus',
  ];

  /**
   * Aplicar color principal de marca en :root
   * Se expone como --brand-color para uso transversal en estilos propios.
   *
   * @param {string|null|undefined} brandColor - Color HEX/RGB válido
   */
  static applyBrandColor(brandColor) {
    const htmlElement = document.documentElement;

    if (!brandColor) {
      htmlElement.style.removeProperty('--brand-color');
      return;
    }

    htmlElement.style.setProperty('--brand-color', brandColor);
  }

  /**
   * Aplicar paleta de colores de Web Awesome
   * Paletas disponibles: default, bright, shoelace, rudimentary, elegant, mild, natural, anodized, vogue
   * Ref: https://webawesome.com/docs/color-palettes
   *
   * @param {string} paletteName - Nombre de la paleta
   */
  static applyPalette(paletteName = 'default') {
    const htmlElement = document.documentElement;

    // Remover clases de paletas anteriores
    htmlElement.classList.remove(
      'wa-palette-default',
      'wa-palette-bright',
      'wa-palette-shoelace',
      'wa-palette-rudimentary',
      'wa-palette-elegant',
      'wa-palette-mild',
      'wa-palette-natural',
      'wa-palette-anodized',
      'wa-palette-vogue'
    );

    htmlElement.classList.add(`wa-palette-${paletteName}`);
  }

  /**
   * Aplicar colores semÃ¡nticos especÃ­ficos de Web Awesome
   * Estructura: wa-{semantic}-{color}
   * Semantic: brand, neutral, success, warning, danger
   * Colors: red, orange, yellow, green, cyan, blue, indigo, purple, pink, gray
   *
   * Ref: https://webawesome.com/docs/color-palettes#semantic-color-overrides
   *
   * @param {Object} colors - Objeto con los colores semÃ¡nticos del backend
   * @param {string} colors.semantic_brand_color - Color para brand
   * @param {string} colors.semantic_neutral_color - Color para neutral
   * @param {string} colors.semantic_success_color - Color para success
   * @param {string} colors.semantic_warning_color - Color para warning
   * @param {string} colors.semantic_danger_color - Color para danger
   */
  static applySemanticColors(colors = {}) {
    const htmlElement = document.documentElement;
    const usePrimaryBrandColor = colors.semantic_brand_color === 'brand_color';

    // Mapeo de colores semÃ¡nticos a clases CSS
    const semanticGroups = ['brand', 'neutral', 'success', 'warning', 'danger'];
    const availableColors = ['red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'indigo', 'purple', 'pink', 'gray'];

    // Remover todas las clases de colores semÃ¡nticos anteriores
    semanticGroups.forEach(group => {
      availableColors.forEach(color => {
        htmlElement.classList.remove(`wa-${group}-${color}`);
      });
    });

    // Aplicar nuevos colores semÃ¡nticos
    if (usePrimaryBrandColor) {
      this.applyBrandTokensFromPrimaryColor();
    } else {
      this.clearCustomBrandTokenOverrides();
    }

    if (colors.semantic_brand_color && !usePrimaryBrandColor) {
      htmlElement.classList.add(`wa-brand-${colors.semantic_brand_color}`);
    }

    if (colors.semantic_neutral_color) {
      htmlElement.classList.add(`wa-neutral-${colors.semantic_neutral_color}`);
    }

    if (colors.semantic_success_color) {
      htmlElement.classList.add(`wa-success-${colors.semantic_success_color}`);
    }

    if (colors.semantic_warning_color) {
      htmlElement.classList.add(`wa-warning-${colors.semantic_warning_color}`);
    }

    if (colors.semantic_danger_color) {
      htmlElement.classList.add(`wa-danger-${colors.semantic_danger_color}`);
    }
  }

  static applyBrandTokensFromPrimaryColor() {
    const htmlElement = document.documentElement;
    const brandColor = getComputedStyle(htmlElement).getPropertyValue('--brand-color').trim();

    if (!brandColor) {
      this.clearCustomBrandTokenOverrides();
      return;
    }

    htmlElement.style.setProperty('--wa-color-brand', brandColor);
    htmlElement.style.setProperty('--wa-color-brand-fill-loud', brandColor);
    htmlElement.style.setProperty('--wa-color-brand-fill-normal', `color-mix(in oklab, ${brandColor} 82%, transparent)`);
    htmlElement.style.setProperty('--wa-color-brand-fill-quiet', `color-mix(in oklab, ${brandColor} 24%, transparent)`);
    htmlElement.style.setProperty('--wa-color-brand-border-loud', `color-mix(in oklab, ${brandColor} 86%, transparent)`);
    htmlElement.style.setProperty('--wa-color-brand-border-normal', `color-mix(in oklab, ${brandColor} 60%, transparent)`);
    htmlElement.style.setProperty('--wa-color-brand-border-quiet', `color-mix(in oklab, ${brandColor} 32%, transparent)`);
    htmlElement.style.setProperty('--wa-color-brand-on-loud', '#ffffff');
    htmlElement.style.setProperty('--wa-color-brand-on-normal', brandColor);
    htmlElement.style.setProperty('--wa-color-brand-on-quiet', brandColor);
    htmlElement.style.setProperty('--wa-color-text-link', brandColor);
    htmlElement.style.setProperty('--wa-color-focus', brandColor);
  }

  static clearCustomBrandTokenOverrides() {
    const htmlElement = document.documentElement;

    this.customBrandTokenProps.forEach((prop) => {
      htmlElement.style.removeProperty(prop);
    });
  }

  /**
   * Aplicar tipografÃ­a personalizada
   * Configura las CSS Custom Properties de Web Awesome para fuentes
   * Ref: https://webawesome.com/docs/tokens/typography
   *
   * Web Awesome Typography Tokens:
   * - --wa-font-family-body: Fuente para texto general del body
   * - --wa-font-family-heading: Fuente para headings (h1-h6)
   * - --wa-font-size-*: Escalas de tamaÃ±o (2x-small a 4x-large)
   * - --wa-font-weight-*: Pesos (light, normal, semibold, bold)
   * - --wa-letter-spacing-*: Espaciado entre letras (dense, normal, loose)
   * - --wa-line-height-*: Altura de lÃ­nea (dense, normal, loose)
   *
   * @param {Object} fonts - Objeto con fuentes del backend
   * @param {string} fonts.font_family_body - Fuente para texto general
   * @param {string} fonts.font_family_heading - Fuente para encabezados
   */
  static applyFonts(fonts = {}) {
    if (!fonts.font_family_body && !fonts.font_family_heading) {
      return;
    }

    const htmlElement = document.documentElement;

    // Aplicar fuente del cuerpo segÃºn documentaciÃ³n oficial de Web Awesome
    if (fonts.font_family_body) {
      htmlElement.style.setProperty('--wa-font-family-body', fonts.font_family_body);
    }

    // Aplicar fuente de encabezados segÃºn documentaciÃ³n oficial de Web Awesome
    if (fonts.font_family_heading) {
      htmlElement.style.setProperty('--wa-font-family-heading', fonts.font_family_heading);
    }
  }

  /**
   * Aplicar tema predefinido de Web Awesome como clase en <html>
   * Temas disponibles: default, awesome, shoelace, active, brutalist, glossy, matter, mellow, playful, premium, tailspin
   * Ref: https://webawesome.com/docs/themes
   *
   * @param {string} themeName - Nombre del tema predefinido
   */
  static async applyPrebuiltTheme(themeName = 'default') {
    const theme = themeImports[themeName] ? themeName : 'default';

    if (this.currentTheme === theme && this.themePromise) {
      return this.themePromise;
    }

    if (this.themePromise) {
      await this.themePromise;
    }

    this.themePromise = this.runApplyTheme(theme);
    return this.themePromise;
  }

  static async runApplyTheme(theme) {
    await themeImports[theme]();
    const htmlElement = document.documentElement;

    // Remover clases de temas anteriores
    htmlElement.classList.remove(
      'wa-theme-default',
      'wa-theme-awesome',
      'wa-theme-shoelace',
      'wa-theme-active',
      'wa-theme-brutalist',
      'wa-theme-glossy',
      'wa-theme-matter',
      'wa-theme-mellow',
      'wa-theme-playful',
      'wa-theme-premium',
      'wa-theme-tailspin'
    );

    htmlElement.classList.add(`wa-theme-${theme}`);
    this.currentTheme = theme;
    // console.log(`Web Awesome: Tema "${theme}" aplicado exitosamente.`);
  }
}

export default WebAwesomeService;
