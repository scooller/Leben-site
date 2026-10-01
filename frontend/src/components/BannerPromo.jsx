/**
 * Componente de Banner Promocional
 * Se muestra antes del hero section si está configurado en el admin
 */
import { appendSessionUtmsToExternalUrl, isExternalHttpUrl } from '../utils/externalLinks';

export default function BannerPromo({ banner }) {
  if (!banner?.image) {
    return null;
  }

  const handleBannerClick = () => {
    if (banner.link) {
      const trackedLink = appendSessionUtmsToExternalUrl(banner.link);

      // Si tiene link externo, abrir en nueva pestaña
      if (isExternalHttpUrl(trackedLink)) {
        window.open(trackedLink, '_blank');
      } else {
        // Si es ruta interna, usar navegación
        window.location.href = trackedLink;
      }
    }
  };

  return (
    <div className="banner-promo" role="img" aria-label="Banner promocional">
      <img
        src={banner.image}
        alt="Banner Promocional"
        className="banner-promo-image"
        loading="lazy"
        decoding="async"
        onClick={handleBannerClick}
        style={{ cursor: banner.link ? 'pointer' : 'default' }}
      />
    </div>
  );
}
