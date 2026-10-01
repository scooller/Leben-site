import { useSiteConfig } from '../contexts/SiteConfigContext';

export default function ThemeToggle() {
  const { config, loading, colorMode, toggleColorMode, showThemeToggle } = useSiteConfig();

  if ((loading && !config) || !showThemeToggle || config?.show_theme_toggle === false) {
    return null;
  }

  const isDark = colorMode === 'dark';

  return (
    <>
      <wa-button
        variant="neutral"
        appearance="filled"
        onClick={toggleColorMode}
        className="theme-floating-toggle box-shadow-2"
        id="theme-toggle-button"
      >
        <wa-icon name={isDark ? 'sun' : 'cloud-moon'} label={isDark ? 'Modo claro' : 'Modo oscuro'}></wa-icon>
      </wa-button>
      <wa-tooltip for="theme-toggle-button" placement="top">
        Cambiar a {isDark ? 'modo claro' : 'modo oscuro'}
      </wa-tooltip>
    </>
  );
}
