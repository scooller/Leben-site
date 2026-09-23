/**
 * saleEventSchema.js
 * Builders for schema.org JSON-LD structured data during Sale/Cyber events.
 * All data comes from config.seo.sale_event (populated by backend SiteSetting.forFrontend()).
 *
 * Google rich results:
 *   - SpecialAnnouncement: https://developers.google.com/search/docs/appearance/structured-data/special-announcements
 *   - SaleEvent (Event):   https://developers.google.com/search/docs/appearance/structured-data/event
 */

/**
 * @param {string} siteUrl   - Base URL of the site
 * @param {object} saleEvent - config.seo.sale_event from backend
 * @param {string} siteName  - config.site_name
 * @returns {object|null}
 */
export const buildSpecialAnnouncementSchema = (siteUrl, saleEvent, siteName) => {
  if (!saleEvent?.name) return null;

  return {
    '@context': 'https://schema.org',
    '@type': 'SpecialAnnouncement',
    name: `${saleEvent.name}${siteName ? ` — ${siteName}` : ''}`,
    text: saleEvent.description || saleEvent.name,
    ...(saleEvent.start_date ? { datePosted: saleEvent.start_date } : {}),
    ...(saleEvent.end_date   ? { expires: saleEvent.end_date }    : {}),
    url: siteUrl || '/',
    announcementLocation: {
      '@type': 'RealEstateAgent',
      name: siteName || '',
      url: siteUrl  || '/',
    },
    category: 'https://www.wikidata.org/wiki/Q1260060',
  };
};

/**
 * @param {string} siteUrl   - Base URL of the site
 * @param {object} saleEvent - config.seo.sale_event from backend
 * @param {string} siteName  - config.site_name
 * @returns {object|null}
 */
export const buildSaleEventSchema = (siteUrl, saleEvent, siteName) => {
  if (!saleEvent?.name || !saleEvent?.start_date) return null;

  return {
    '@context': 'https://schema.org',
    '@type': 'SaleEvent',
    name: saleEvent.name,
    description: saleEvent.description || saleEvent.name,
    startDate: saleEvent.start_date,
    ...(saleEvent.end_date   ? { endDate: saleEvent.end_date }   : {}),
    ...(saleEvent.og_image   ? { image:   saleEvent.og_image }   : {}),
    url: siteUrl || '/',
    organizer: { '@type': 'Organization', name: siteName || '', url: siteUrl || '/' },
    location:  { '@type': 'VirtualLocation', url: siteUrl || '/' },
    offers: {
      '@type': 'AggregateOffer',
      priceCurrency: 'CLP',
      availability: 'https://schema.org/InStock',
    },
    eventStatus: 'https://schema.org/EventScheduled',
    eventAttendanceMode: 'https://schema.org/OnlineEventAttendanceMode',
  };
};
