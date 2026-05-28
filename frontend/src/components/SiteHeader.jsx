import { useEffect, useRef, useState } from 'react';
import { useSiteConfig } from '../contexts/SiteConfigContext';
import { trackEvent } from '../utils/tagManager';
import { appendSessionUtmsToExternalUrl } from '../utils/externalLinks';

function SiteHeader({ config, currentPath = '/', onNavigate, onMenuClick }) {
  const { colorMode } = useSiteConfig();
  const drawerRef = useRef(null);
  const mobileDrawerId = 'site-header-mobile-menu';
  const [isMobileView, setIsMobileView] = useState(() => {
    if (typeof window === 'undefined') {
      return false;
    }

    return window.matchMedia('(max-width: 768px)').matches;
  });
  const isPlantsActive = currentPath === '/plantas' || currentPath.startsWith('/p/') || currentPath === '/f' || currentPath.startsWith('/f/');
  const isCatalogEnabled = Boolean(config?.mostrar_plantas ?? true);

  useEffect(() => {
    if (typeof window === 'undefined') {
      return undefined;
    }

    const mediaQuery = window.matchMedia('(max-width: 768px)');

    const handleChange = (event) => {
      setIsMobileView(event.matches);

      if (!event.matches) {
        drawerRef.current?.hide?.();
      }
    };

    setIsMobileView(mediaQuery.matches);

    if (typeof mediaQuery.addEventListener === 'function') {
      mediaQuery.addEventListener('change', handleChange);

      return () => {
        mediaQuery.removeEventListener('change', handleChange);
      };
    }

    mediaQuery.addListener(handleChange);

    return () => {
      mediaQuery.removeListener(handleChange);
    };
  }, []);

  const closeMobileMenu = () => {
    drawerRef.current?.hide?.();
  };

  const goToHome = () => {
    onNavigate?.('/');
    window.requestAnimationFrame(closeMobileMenu);
  };

  const siteUrl = `${config?.site_url || '/'}`.trim() || '/';
  const trackedSiteUrl = appendSessionUtmsToExternalUrl(siteUrl);
  const logoSrc = colorMode === 'dark'
    ? (config?.logo_dark || config?.logo)
    : (config?.logo || config?.logo_dark);

  const goToPlants = () => {
    if (!isCatalogEnabled) {
      window.requestAnimationFrame(closeMobileMenu);
      return;
    }

    if (currentPath === '/plantas') {
      onMenuClick?.();
      window.requestAnimationFrame(closeMobileMenu);
      return;
    }

    onNavigate?.('/plantas');
    window.requestAnimationFrame(closeMobileMenu);
  };

  const goToMatch = () => {
    if (!isCatalogEnabled) {
      window.requestAnimationFrame(closeMobileMenu);
      return;
    }

    if (currentPath === '/match') {
      onMenuClick?.();
      window.requestAnimationFrame(closeMobileMenu);
      return;
    }

    onNavigate?.('/match');
    window.requestAnimationFrame(closeMobileMenu);
  };

  const contactHref = '/contacto';

  const handleContactClick = () => {
    trackEvent('wa_link', {
      source: isMobileView ? 'site_header_mobile' : 'site_header_desktop',
      action: 'advisor_cta_click',
      destination: contactHref,
      current_path: currentPath,
    });

    window.requestAnimationFrame(closeMobileMenu);
  };

  return (
    <>
      <header className="site-header box-shadow-1 wa-px-xl wa-py-m">
        <div className="wa-split wa-gap-s wa-align-items-center" style={{ width: '100%' }}>
          <wa-button appearance="plain" href={trackedSiteUrl} target="_blank">
            {logoSrc ? (
              <img src={logoSrc} alt={config?.site_name || 'Logo'} className="site-logo" />
            ) : (
              <span className="site-name">{config?.site_name || 'iLeben'}</span>
            )}
          </wa-button>

          {isMobileView ? (
            <wa-button
              appearance="plain"
              aria-label="Abrir menú"
              pill
              data-drawer={`open ${mobileDrawerId}`}
            >
              <wa-icon name="bars"></wa-icon>
            </wa-button>
          ) : (
            <nav className="site-header-nav wa-cluster wa-gap-2xs" aria-label="Navegación principal">
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

                <wa-button appearance={currentPath === '/match' ? 'filled-outlined' : 'plain'} onClick={goToMatch}>
                  <wa-icon name="champagne-glasses" slot="start"></wa-icon>
                  Match
                </wa-button>
                </>
              )}
              <wa-button appearance={currentPath === '/contacto' ? 'filled-outlined' : 'plain'} href={contactHref} onClick={handleContactClick} variant="danger">
                <wa-icon name="envelope" slot="start"></wa-icon>
                Asesorate aquí
              </wa-button>
            </nav>
          )}
        </div>
      </header>

      {isMobileView && (
        <wa-drawer
          id={mobileDrawerId}
          ref={drawerRef}
          placement="start"
          className="wa-cloak"
        >
            <span slot='label'>
            {logoSrc ? (
              <img src={logoSrc} alt={config?.site_name || 'Logo'} className="site-logo" />
            ) : (
              <span className="site-name">{config?.site_name || 'iLeben'}</span>
            )}
            </span>
            <div className="wa-stack wa-gap-xs wa-align-items-stretch">
                <wa-button appearance={currentPath === '/' ? 'filled-outlined' : 'plain'} onClick={goToHome}>
                    <wa-icon name="house" slot="start"></wa-icon>
                    Home
                </wa-button>
                {isCatalogEnabled && (
                  <>
                    <wa-button appearance={isPlantsActive ? 'filled-outlined' : 'plain'} onClick={goToPlants}>
                      <wa-icon name="city" slot="start"></wa-icon>
                      Plantas
                    </wa-button>

                    <wa-button appearance={currentPath === '/match' ? 'filled-outlined' : 'plain'} onClick={goToMatch}>
                      <wa-icon name="sparkles" slot="start"></wa-icon>
                      Match
                    </wa-button>
                  </>
                )}
                <wa-button appearance={currentPath === '/contacto' ? 'filled-outlined' : 'plain'} href={contactHref} onClick={handleContactClick}>
                    <wa-icon name="envelope" slot="start"></wa-icon>
                    Asesorate aquí
                </wa-button>
            </div>
        </wa-drawer>
      )}
    </>
  );
}

export default SiteHeader;
