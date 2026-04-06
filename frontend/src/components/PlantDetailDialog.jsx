import { Fancybox } from '@fancyapps/ui';
import '@fancyapps/ui/dist/fancybox/fancybox.css';

function PlantDetailDialog({ plant, dialogRef, checkoutLoading, onCheckout }) {
    const sanitizePhone = (value) => `${value ?? ''}`.replace(/\D+/g, '');
    const mobile = typeof window !== 'undefined' && window.matchMedia('(max-width: 768px)').matches;

    const getCurrentPlantUrl = () => {
        if (typeof window === 'undefined') {
            return '';
        }

        return `${window.location.origin}${window.location.pathname}`;
    };

    const getWhatsappMessage = (advisorName) => {
        const unitName = `${plant?.nombre || ''}`.trim();
        const projectName = `${plant?.proyectoNombre || ''}`.trim();
        const name = `${advisorName || ''}`.trim();
        const plantUrl = getCurrentPlantUrl();

        const baseMessage = `Hola ${name}, me interesa la planta ${unitName} del proyecto ${projectName}. ¿Me puedes compartir más información, por favor?`;

        if (!plantUrl) {
            return baseMessage;
        }

        return `${baseMessage} URL: ${plantUrl}`;
    };

    const getWhatsappUrl = (advisor) => {
        const phone = sanitizePhone(advisor?.whatsapp);

        if (!phone) {
            return null;
        }

        const advisorName = advisor?.fullName || advisor?.firstName || 'asesor';
        const message = getWhatsappMessage(advisorName);

        return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
    };

    const getAdvisorInitials = (advisor) => {
        const source = `${advisor?.fullName || `${advisor?.firstName || ''} ${advisor?.lastName || ''}`}`.trim();

        if (!source) {
            return 'AS';
        }

        return source
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase() || '')
            .join('');
    };

    const openImageZoom = (event) => {
        event.preventDefault();

        if (!plant?.detailImageUrl) {
            return;
        }

        const imageUrl = plant.detailImageUrl;
        const isSvg = /\.svg($|[?#])/i.test(imageUrl) || imageUrl.startsWith('data:image/svg+xml');

        const slide = isSvg
            ? {
                type: 'html',
                html: `<img src="${imageUrl.replace(/"/g, '&quot;')}" alt="Planta ${plant?.nombre || ''}" style="display:block;width:min(92vw,1400px);height:auto;max-height:90vh;object-fit:contain;margin:0 auto;" />`,
                caption: `Planta ${plant?.nombre || ''}`,
            }
            : {
                src: imageUrl,
                type: 'image',
                caption: `Planta ${plant?.nombre || ''}`,
            };

        Fancybox.show(
            [slide],
            {
                Thumbs: false,
                Toolbar: {
                    display: {
                        left: [],
                        middle: [],
                        right: ['close'],
                    },
                },
            }
        );
    };

  return (
    <wa-dialog
      ref={dialogRef}
      className="plant-detail-dialog"
      style={mobile ? { '--width': '100dvw','--wa-space-2xl': '1rem' } : { '--width': '80vw' }} // --width desktop 80vw, mobile 95vw
    >
      {plant && (
        <>
            <span slot="label"><wa-icon name="building-circle-exclamation"></wa-icon> Planta - {plant?.nombre || 'Detalle'}</span>
            <div className="wa-grid wa-gap-l plant-detail-layout">
                <div className="plant-detail-media">
                    <a href={plant.detailImageUrl} onClick={openImageZoom}>
                        <img
                            src={plant.detailImageUrl}
                            alt={plant.nombre}
                            className="plant-detail-image"
                            style={{ cursor: 'zoom-in' }}
                        />
                    </a>
                    {plant.projectLogoUrl && (
                        <wa-badge variant="neutral" className="project-logo-badge" aria-label={`Logo de ${plant.proyectoNombre || 'proyecto'}`}>
                            <img
                                src={plant.projectLogoUrl}
                                alt={`Logo de ${plant.proyectoNombre || 'proyecto'}`}
                                className="project-logo-image"
                            />
                        </wa-badge>
                    )}
                    {plant.discountPercentage > 0 && (
                        <div className="discount-seal" aria-label={`Descuento ${plant.discountPercentage}%`}>
                            <span className="discount-seal-value">{plant.discountPercentage}%</span>
                            <span className="discount-seal-label">descto.</span>
                        </div>
                    )}
                </div>
                <div className="wa-stack wa-gap-m plant-detail-content">
                    <wa-scroller orientation="vertical" style={{ maxHeight: '35dvh' }}>
                        <wa-details summary="Detalles" appearance="plain" open>
                            <div className='wa-grid wa-gap-m' style={{ '--min-column-size': '14rem' }}>
                                {plant.proyectoNombre && (
                                <div className="wa-split wa-align-items-center">
                                    <strong>Proyecto</strong>
                                    <span>{plant.proyectoNombre}</span>
                                </div>
                                )}
                                {plant.proyectoComuna && (
                                <div className="wa-split wa-align-items-center">
                                    <strong>Ubicación</strong>
                                    <span>{plant.proyectoComuna}</span>
                                </div>
                                )}
                                {plant.proyectoEtapa && (
                                <div className="wa-split wa-align-items-center">
                                    <strong>Etapa</strong>
                                    <wa-badge variant="success">{plant.proyectoEtapa}</wa-badge>
                                </div>
                                )}
                                {plant.proyectoDescripcion && (
                                <div className="wa-stack wa-gap-xs">
                                    <strong>Descripción del Proyecto</strong>
                                    <span>{plant.proyectoDescripcion}</span>
                                </div>
                                )}
                                <div className="wa-split wa-align-items-center">
                                <strong>Numero</strong>
                                <span>{plant.nombre}</span>
                                </div>
                                {plant.programa && (
                                <div className="wa-split wa-align-items-center">
                                    <strong>Programa</strong>
                                    <wa-badge variant="brand">{plant.programa}</wa-badge>
                                </div>
                                )}
                                {plant.orientacion && (
                                <div className="wa-split wa-align-items-center">
                                    <strong>Orientación</strong>
                                    <wa-tag variant="primary">{plant.orientacion}</wa-tag>
                                </div>
                                )}
                                {plant.piso && (
                                <div className="wa-split wa-align-items-center">
                                    <strong>Piso</strong>
                                    <wa-tag variant="primary">{plant.piso}</wa-tag>
                                </div>
                                )}
                            </div>
                        </wa-details>
                        <wa-divider></wa-divider>
                        {(plant.superficie_total_principal !== null && plant.superficie_total_principal !== undefined
                        || plant.superficie_interior !== null && plant.superficie_interior !== undefined
                        || plant.superficie_util !== null && plant.superficie_util !== undefined
                        || plant.superficie_terraza !== null && plant.superficie_terraza !== undefined
                        || plant.superficie_vendible !== null && plant.superficie_vendible !== undefined) && (
                        <wa-details summary="Superficies" appearance="plain" open>
                            <div className="wa-grid wa-gap-s" style={{ '--min-column-size': '12rem' }}>
                                {plant.superficie_total_principal !== null && plant.superficie_total_principal !== undefined && (
                                <div className="wa-stack wa-gap-2xs">
                                    <div className="wa-cluster wa-gap-xs wa-align-items-center">
                                    <wa-icon name="house" style={{ fontSize: '0.9em' }}></wa-icon>
                                    <span>Total principal</span>
                                    </div>
                                    <wa-tag variant="primary">{plant.superficie_total_principal} m²</wa-tag>
                                </div>
                                )}
                                {plant.superficie_interior !== null && plant.superficie_interior !== undefined && (
                                <div className="wa-stack wa-gap-2xs">
                                    <div className="wa-cluster wa-gap-xs wa-align-items-center">
                                    <wa-icon name="door-open" style={{ fontSize: '0.9em' }}></wa-icon>
                                    <span>Interior</span>
                                    </div>
                                    <wa-tag variant="primary">{plant.superficie_interior} m²</wa-tag>
                                </div>
                                )}
                                {plant.superficie_util !== null && plant.superficie_util !== undefined && (
                                <div className="wa-stack wa-gap-2xs">
                                    <div className="wa-cluster wa-gap-xs wa-align-items-center">
                                    <wa-icon name="ruler" style={{ fontSize: '0.9em' }}></wa-icon>
                                    <span>Útil</span>
                                    </div>
                                    <wa-tag variant="primary">{plant.superficie_util} m²</wa-tag>
                                </div>
                                )}
                                {plant.superficie_terraza !== null && plant.superficie_terraza !== undefined && (
                                <div className="wa-stack wa-gap-2xs">
                                    <div className="wa-cluster wa-gap-xs wa-align-items-center">
                                    <wa-icon name="umbrella-beach" style={{ fontSize: '0.9em' }}></wa-icon>
                                    <span>Terraza</span>
                                    </div>
                                    <wa-tag variant="primary">{plant.superficie_terraza} m²</wa-tag>
                                </div>
                                )}
                                {plant.superficie_vendible !== null && plant.superficie_vendible !== undefined && (
                                <div className="wa-stack wa-gap-2xs">
                                    <div className="wa-cluster wa-gap-xs wa-align-items-center">
                                    <wa-icon name="layer-group" style={{ fontSize: '0.9em' }}></wa-icon>
                                    <span>Vendible</span>
                                    </div>
                                    <wa-tag variant="primary">{plant.superficie_vendible} m²</wa-tag>
                                </div>
                                )}
                            </div>
                        </wa-details>
                        )}

                        {Array.isArray(plant.asesores) && plant.asesores.length > 0 && (
                        <>
                            <wa-divider></wa-divider>
                            <wa-details summary="Contacto" appearance="plain" open>
                                <div className="wa-stack wa-gap-s advisor-contact-list">
                                    {plant.asesores.map((advisor) => {
                                        const advisorName = advisor.fullName || `${advisor.firstName || ''} ${advisor.lastName || ''}`.trim() || 'Asesor';
                                        const whatsappUrl = getWhatsappUrl(advisor);

                                        return (
                                            <article key={advisor.id} className="advisor-contact-card">
                                                <div className="wa-cluster wa-gap-s wa-align-items-center advisor-contact-header">
                                                    {advisor.avatarUrl ? (
                                                        <img src={advisor.avatarUrl} alt={advisorName} className="advisor-avatar" />
                                                    ) : (
                                                        <span className="advisor-avatar-fallback" aria-hidden="true">
                                                            {getAdvisorInitials(advisor)}
                                                        </span>
                                                    )}
                                                    <div className="wa-stack wa-gap-3xs advisor-contact-meta">
                                                        <strong>{advisorName}</strong>
                                                        {advisor.email && (
                                                            <span>{advisor.email}</span>
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="wa-cluster wa-gap-xs advisor-contact-actions">
                                                    {whatsappUrl ? (
                                                        <a
                                                            className="advisor-whatsapp-link"
                                                            href={whatsappUrl}
                                                            target="_blank"
                                                            rel="noreferrer noopener"
                                                        >
                                                            <wa-icon name="whatsapp" family="brands" slot="start"></wa-icon>
                                                            Contactar por WhatsApp
                                                        </a>
                                                    ) : (
                                                        <wa-badge variant="neutral">Sin WhatsApp</wa-badge>
                                                    )}
                                                </div>
                                            </article>
                                        );
                                    })}
                                </div>
                            </wa-details>
                        </>
                        )}
                    </wa-scroller>
                </div>
            </div>

            <div slot="footer" className="plant-detail-dialog-footer">
                {(plant.precioBase || plant.precioLista) && (
                <>
                <div className="wa-stack wa-gap-xs precio-detail wa-order-0 wa-order-mobile-1">
                    <div className="wa-cluster wa-caption-s">
                    {plant.precioLista && plant.precioBase && plant.precioLista !== plant.precioBase && (
                        <div className="wa-split wa-gap-xs wa-mt-m">
                            <span>Precio lista:</span>
                            <span style={{ textDecoration: 'line-through', opacity: 0.7 }}>
                                UF {plant.precioLista.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                            </span>
                        </div>
                    )}
                        <div className="wa-split wa-gap-xs">
                            <span className='wa-text-uppercase price-label-discount wa-font-weight-bold'>Precio Sale:</span>
                            <span className="wa-heading-xl wa-font-weight-bold">
                                UF {(plant.precioBase || plant.precioLista).toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                            </span>
                        </div>
                    </div>
                </div>
                </>
                )}
                <div className="wa-cluster wa-gap-s wa-order-1 wa-order-mobile-0">
                    <wa-button
                        variant="neutral"
                        data-dialog="close"
                    >
                        Cerrar
                    </wa-button>
                {(plant.isPaid || plant.isReserved || plant.isAvailable === false) ? (
                    <wa-button
                        variant="neutral"
                        disabled
                    >
                        <wa-icon name="house-circle-xmark" slot="start"></wa-icon>
                        {plant.isPaid
                            ? 'Pagada'
                            : plant.isReserved
                                ? 'Reservada'
                                : 'No disponible'
                        }
                    </wa-button>
                ) : (
                    <wa-button
                        variant="brand"
                        disabled={checkoutLoading}
                        {...(checkoutLoading && { loading: true })}
                        onClick={onCheckout}
                    >
                        {checkoutLoading ? 'Cargando...' : <>
                            <wa-icon name="hand-holding-dollar" slot="start"></wa-icon> Cotizar Ahora
                        </>}
                    </wa-button>
                )}
                </div>
            </div>
        </>
      )}
    </wa-dialog>
  );
}

export default PlantDetailDialog;
