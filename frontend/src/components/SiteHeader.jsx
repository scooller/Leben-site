import { useSiteConfig } from '../contexts/SiteConfigContext';
import { trackEvent } from '../utils/tagManager';
import { appendSessionUtmsToExternalUrl } from '../utils/externalLinks';

function SiteHeader({ config, currentPath = '/', onNavigate, onMenuClick }) {
  const { colorMode } = useSiteConfig();
  const isPlantsActive = currentPath === '/plantas' || currentPath.startsWith('/p/') || currentPath === '/f' || currentPath.startsWith('/f/');
  const isCatalogEnabled = Boolean(config?.mostrar_plantas ?? true);

  const goToHome = () => {
    onNavigate?.('/');
  };

  const siteUrl = `${config?.site_url || '/'}`.trim() || '/';
  const trackedSiteUrl = appendSessionUtmsToExternalUrl(siteUrl);
  const logoSrc = colorMode === 'dark'
    ? (config?.logo_dark || config?.logo)
    : (config?.logo || config?.logo_dark);

  const goToPlants = () => {
    if (!isCatalogEnabled) {
      return;
    }

    if (currentPath === '/plantas') {
      onMenuClick?.();
      return;
    }

    onNavigate?.('/plantas');
  };

  const contactHref = '/contacto';

  const handleContactClick = (source = 'site_header_desktop') => {
    trackEvent('wa_link', {
      source,
      action: 'advisor_cta_click',
      destination: contactHref,
      current_path: currentPath,
    });
  };

  return (
    <>
      <header slot="header" className="site-header box-shadow-1 wa-px-xl wa-py-m">
        <div className="wa-split wa-gap-s wa-align-items-center" style={{ width: '100%' }}>
          <wa-button appearance="plain" href={trackedSiteUrl} target="_blank">
            {logoSrc ? (
              <img src={logoSrc} alt={config?.site_name || 'Logo'} className="site-logo" />
            ) : (
              <span className="site-name">{config?.site_name || 'iLeben'}</span>
            )}
          </wa-button>

          <nav className="site-header-nav header-nav wa-cluster wa-gap-2xs" aria-label="Navegación principal">
            {isCatalogEnabled && (
              <>
                <wa-button appearance={currentPath === '/' ? 'filled-outlined' : 'plain'} onClick={goToHome}>
                  <wa-icon name="house" slot="start"></wa-icon>
                  Home
                </wa-button>

                <wa-button appearance={isPlantsActive ? 'filled-outlined' : 'plain'} onClick={goToPlants}>
                  <wa-icon name="city" slot="start"></wa-icon>
                  Plantas
                </wa-button>
              </>
            )}
            <wa-button
              appearance={currentPath === '/contacto' ? 'filled-outlined' : 'plain'}
              href={contactHref}
              onClick={() => handleContactClick('site_header_desktop')}
              variant="danger"
            >
              <wa-icon name="envelope" slot="start"></wa-icon>
              Asesorate aquí
            </wa-button>
          </nav>

          <wa-button
            data-toggle-nav
            appearance="plain"
            className="wa-mobile-only"
            aria-label="Abrir menú"
            pill
          >
            <wa-icon name="bars"></wa-icon>
          </wa-button>
        </div>
      </header>

      <nav slot="navigation" className="site-mobile-nav wa-stack wa-gap-s wa-p-l" aria-label="Navegación móvil">
        <div className="wa-py-s wa-border-bottom">
          {logoSrc ? (
            <img src={logoSrc} alt={config?.site_name || 'Logo'} className="site-logo" />
          ) : (
            <span className="site-name">{config?.site_name || 'iLeben'}</span>
          )}
        </div>
        <div className="wa-stack wa-gap-xs wa-align-items-stretch">
          <wa-button
            appearance={currentPath === '/' ? 'filled-outlined' : 'plain'}
            onClick={goToHome}
            data-drawer="close"
          >
            <wa-icon name="house" slot="start"></wa-icon>
            Home
          </wa-button>
          {isCatalogEnabled && (
            <wa-button
              appearance={isPlantsActive ? 'filled-outlined' : 'plain'}
              onClick={goToPlants}
              data-drawer="close"
            >
              <wa-icon name="city" slot="start"></wa-icon>
              Plantas
            </wa-button>
          )}
          <wa-button
            appearance={currentPath === '/contacto' ? 'filled-outlined' : 'plain'}
            href={contactHref}
            onClick={() => handleContactClick('site_header_mobile')}
            data-drawer="close"
            variant="danger"
          >
            <wa-icon name="envelope" slot="start"></wa-icon>
            Asesorate aquí
          </wa-button>
        </div>
      </nav>
    </>
  );
}

export default SiteHeader;
