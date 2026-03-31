import { Fancybox } from '@fancyapps/ui';
import '@fancyapps/ui/dist/fancybox/fancybox.css';

function PlantDetailDialog({ plant, dialogRef, checkoutLoading, onCheckout }) {
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
                html: `<img src="${imageUrl.replace(/\"/g, '&quot;')}" alt="Planta ${plant?.nombre || ''}" style="display:block;width:min(92vw,1400px);height:auto;max-height:90vh;object-fit:contain;margin:0 auto;" />`,
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
      style={{ '--width': '80vw' }}
      light-dismiss
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
                    </wa-scroller>
                </div>
            </div>

            <div slot="footer" className="plant-detail-dialog-footer">
                {(plant.precioBase || plant.precioLista) && (
                <>
                <div className="wa-stack wa-gap-xs">
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
                            <span>Precio sale:</span>
                            <span className="wa-heading-xl wa-font-weight-bold">
                                UF {(plant.precioBase || plant.precioLista).toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                            </span>
                        </div>
                    </div>
                </div>
                </>
                )}
                <div className="wa-cluster wa-gap-s">
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
