import { useMemo } from 'react';
import { useSiteConfig } from '../contexts/SiteConfigContext';

const normalizeFooterMenu = (menuItems) => {
  if (!Array.isArray(menuItems)) {
    return [];
  }

  return menuItems
    .filter((item) => item && typeof item === 'object')
    .map((item) => ({
      label: `${item.label ?? ''}`.trim(),
      url: `${item.url ?? ''}`.trim(),
      newTab: Boolean(item.new_tab),
    }))
    .filter((item) => item.label !== '' && item.url !== '');
};

function SiteFooter({ config, onNavigate }) {
  const { colorMode } = useSiteConfig();
  const footerMenuItems = useMemo(() => normalizeFooterMenu(config?.footer_menu), [config?.footer_menu]);
  const isCatalogEnabled = Boolean(config?.mostrar_plantas ?? true);
  const hasLegalText = Boolean(config?.footer_legal_text && config.footer_legal_text.trim() !== '');
  const socialLinks = useMemo(() => {
    const social = config?.social || {};

    return [
      {
        key: 'facebook',
        label: 'Facebook',
        icon: 'facebook',
        url: social.facebook,
      },
      {
        key: 'instagram',
        label: 'Instagram',
        icon: 'instagram',
        url: social.instagram,
      },
      {
        key: 'linkedin',
        label: 'LinkedIn',
        icon: 'linkedin-in',
        url: social.linkedin,
      },
      {
        key: 'youtube',
        label: 'YouTube',
        icon: 'youtube',
        url: social.youtube,
      },
      {
        key: 'twitter',
        label: 'X',
        icon: 'x-twitter',
        url: social.twitter,
      },
    ].filter((item) => Boolean(item.url));
  }, [config?.social]);

  const logoSrc = colorMode === 'dark'
    ? (config?.logo_dark || config?.logo)
    : (config?.logo || config?.logo_dark);

  const handleFooterNavigation = (event, url, newTab = false) => {
    const nextUrl = `${url ?? ''}`.trim();

    if (nextUrl === '' || newTab) {
      return;
    }

    if (nextUrl === '#') {
      event.preventDefault();
      return;
    }

    if (nextUrl.startsWith('/') && onNavigate) {
      event.preventDefault();
      onNavigate(nextUrl);
    }
  };

  return (
    <footer className="site-footer wa-stack wa-gap-l wa-mt-3xl">
      {hasLegalText && (
        <wa-card appearance="filled">
          <div className="wa-stack wa-gap-s wa-align-items-center wa-text-align-center wa-font-size-3xs" style={{ padding: 'var(--wa-space-l)' }}>
            <div dangerouslySetInnerHTML={{ __html: config.footer_legal_text }} />
          </div>
        </wa-card>
      )}

      <wa-card appearance="filled">
        <section className="wa-stack wa-gap-l" style={{ padding: 'var(--wa-space-l)' }}>
          <div className="wa-split wa-gap-m wa-align-items-center" style={{ flexWrap: 'wrap' }}>
            <div className="wa-stack wa-gap-s">
              {logoSrc ? (
                <img
                  src={logoSrc}
                  alt={config?.site_name || 'Logo'}
                  style={{ maxWidth: '190px', width: '100%', height: 'auto', objectFit: 'contain' }}
                />
              ) : (
                <strong>{config?.site_name || 'iLeben'}</strong>
              )}

              {config?.site_description && (
                <span className="wa-color-text-quiet wa-font-size-sm">{config.site_description}</span>
              )}

              <small className="wa-color-text-quiet">
                Todos los derechos reservados {new Date().getFullYear()} {config?.site_name || 'iLeben'}
              </small>
            </div>

            {socialLinks.length > 0 && (
              <div className="wa-stack wa-gap-2xs wa-align-items-end" style={{ marginLeft: 'auto' }}>
                <span>Síguenos en:</span>
                <div className="wa-cluster wa-gap-xs">
                  {socialLinks.map((socialItem) => (
                    <wa-button
                      appearance="plain"
                      key={socialItem.key}
                      href={socialItem.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      aria-label={socialItem.label}
                      pill
                    >
                      <wa-icon name={socialItem.icon} family="brands"></wa-icon>
                    </wa-button>
                  ))}
                </div>
              </div>
            )}
          </div>

          {isCatalogEnabled && footerMenuItems.length > 0 && (
            <nav className="wa-cluster wa-gap-m wa-justify-content-center wa-text-align-center" aria-label="Menú legal del sitio">
              {footerMenuItems.map((menuItem, index) => (
                <wa-button
                  key={`${menuItem.label}-${index}`}
                  appearance="plain"
                  href={menuItem.url}
                  target={menuItem.newTab ? '_blank' : undefined}
                  rel={menuItem.newTab ? 'noopener noreferrer' : undefined}
                  onClick={(event) => handleFooterNavigation(event, menuItem.url, menuItem.newTab)}
                >
                  {menuItem.label}
                </wa-button>
              ))}
            </nav>
          )}
        </section>
      </wa-card>
    </footer>
  );
}

export default SiteFooter;
