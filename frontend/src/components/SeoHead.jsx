import { useEffect } from 'react';
import { useSiteConfig } from '../contexts/SiteConfigContext';
import siteConfigService from '../services/siteConfig';

/**
 * Componente reutilizable para etiquetas SEO, Open Graph y Twitter Cards.
 */
export default function SeoHead({
  title,
  description,
  canonical,
  ogImage,
  ogType = 'website',
  ogLogo,
  keywords,
  author,
  robots,
  twitterCard = 'summary_large_image',
  twitterSite,
}) {
  const { config } = useSiteConfig();

  useEffect(() => {
    const seoConfig = config?.seo || {};
    const siteUrl = `${config?.site_url || 'https://sale.ileben.cl'}`.trim().replace(/\/+$/, '');
    const siteName = config?.site_name || 'iLeben';

    const resolvedTitle = title || seoConfig.default_meta_title || `${siteName} | Departamentos en venta`;
    const resolvedDescription = description || seoConfig.default_og_description || config?.site_description || 'Descubre departamentos inmobiliarios disponibles para compra en Chile.';
    const resolvedCanonical = canonical
      ? (canonical.startsWith('http') ? canonical : `${siteUrl}${canonical.startsWith('/') ? '' : '/'}${canonical}`)
      : (typeof window !== 'undefined' ? window.location.href : `${siteUrl}/`);
    const resolvedOgImage = ogImage || seoConfig.og_image || null;
    const resolvedOgLogo = ogLogo || config?.logo || config?.logo_sale || config?.logo_dark || config?.favicon || null;

    siteConfigService.applySeo({
      title: resolvedTitle,
      description: resolvedDescription,
      keywords: keywords || seoConfig.meta_keywords,
      author: author || seoConfig.meta_author,
      canonical: resolvedCanonical,
      robots: robots || seoConfig.robots_default || 'index,follow',
      ogImage: resolvedOgImage,
      ogType,
      ogLogo: resolvedOgLogo,
      ogSiteName: siteName,
      ogLocale: seoConfig.site_locale || 'es-CL',
      twitterCard,
      twitterSite: twitterSite || seoConfig.twitter_site,
    });
  }, [
    author,
    canonical,
    config,
    description,
    keywords,
    ogImage,
    ogLogo,
    ogType,
    robots,
    title,
    twitterCard,
    twitterSite,
  ]);

  return null;
}
