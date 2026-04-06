import { useState, useRef, useEffect, useCallback } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { ScrollSmoother } from 'gsap/ScrollSmoother';
import PlantDetailDialog from './PlantDetailDialog';

gsap.registerPlugin(ScrollTrigger, ScrollSmoother);



/**
 * Componente para el grid de plantas con tarjetas, skeleton, diálogo de detalles y paginación
 */
function PlantsGrid({
  plants,
  loading,
  checkoutLoading,
  onQuickCheckout,
  totalPlants,
  page,
  totalPages,
  onPageChange,
  selectedPlant,
  onSelectPlant,
  onClosePlantDetail,
}) {
  const [internalSelectedPlant, setInternalSelectedPlant] = useState(null);
  const [detailLoadingId, setDetailLoadingId] = useState(null);
  const dialogRef = useRef(null);
  const gridContainerRef = useRef(null);
  const activePlant = selectedPlant ?? internalSelectedPlant;

  const setActivePlant = (plant) => {
    if (typeof onSelectPlant === 'function') {
      onSelectPlant(plant);
      return;
    }

    setInternalSelectedPlant(plant);
  };

  const closeActivePlant = useCallback(() => {
    setDetailLoadingId(null);

    if (typeof onClosePlantDetail === 'function') {
      onClosePlantDetail();
      return;
    }

    setInternalSelectedPlant(null);
  }, [onClosePlantDetail]);

  // GSAP ScrollTrigger para animar cards cuando entran al viewport
  useEffect(() => {
    if (loading || plants.length === 0 || !gridContainerRef.current) {
      return;
    }

    let ctx;
    let isMounted = true;

    // Esperar a que wa-card esté definido para evitar condiciones de carrera al montar web components.
    customElements.whenDefined('wa-card').then(() => {
      if (!isMounted) {
        return;
      }

      let smoother = ScrollSmoother.get();

      if (!smoother) {
        const wrapper = document.querySelector('#smooth-wrapper');
        const content = document.querySelector('#smooth-content');

        if (wrapper && content) {
          smoother = ScrollSmoother.create({
            wrapper,
            content,
            smooth: 1,
            effects: true
          });
        }
      }

      const scrollerTarget = smoother ? smoother.wrapper() : window;

      ctx = gsap.context(() => {
        const cards = gsap.utils.toArray('.plant-card', gridContainerRef.current);

        if (cards.length === 0) {
          return;
        }

        cards.forEach((card, index) => {
          gsap.to(card, {
            opacity: 1,
            scale: 1,
            delay: index % 2 === 0 ? 0 : 0.8, // Stagger para cards pares e impares
            ease: 'Power2.in',
            scrollTrigger: {
              trigger: card,
              scroller: scrollerTarget,
              start: 'top 85%',
              end: 'top 50%',
              toggleActions: 'play none none none',
              markers: false
            }
          });
        });
      }, gridContainerRef);

      ScrollTrigger.refresh();
    });

    return () => {
      isMounted = false;

      if (ctx) {
        ctx.revert();
      }
    };
  }, [plants, loading]);

  const buildPaginationItems = () => {
    if (totalPages <= 7) {
      return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    const items = [1];
    const left = Math.max(2, page - 1);
    const right = Math.min(totalPages - 1, page + 1);

    if (left > 2) {
      items.push('left-ellipsis');
    }

    for (let current = left; current <= right; current += 1) {
      items.push(current);
    }

    if (right < totalPages - 1) {
      items.push('right-ellipsis');
    }

    items.push(totalPages);

    return items;
  };

  const paginationItems = buildPaginationItems();
  const showingFrom = totalPlants > 0 ? (page - 1) * 12 + 1 : 0;
  const showingTo = Math.min((page - 1) * 12 + plants.length, totalPlants || 0);

  const openPlantDetail = (plant) => {
    setDetailLoadingId(plant.id);
    setActivePlant(plant);
  };

  const handleCheckoutFromDialog = () => {
    if (!activePlant) return;
    // Cerrar el diálogopar
    if (dialogRef.current) {
      dialogRef.current.open = false;
    }
    // Llamar al checkout con la planta seleccionada
    onQuickCheckout(activePlant);
  };

  useEffect(() => {
    if (!dialogRef.current) {
      return;
    }

    if (activePlant) {
      dialogRef.current.open = true;
    } else {
      dialogRef.current.open = false;
    }
  }, [activePlant]);

  useEffect(() => {
    const dialogElement = dialogRef.current;

    if (!dialogElement) {
      return;
    }

    const handleAfterHide = (event) => {
      if (event.target !== dialogElement) {
        return;
      }

      closeActivePlant();
    };

    dialogElement.addEventListener('wa-after-hide', handleAfterHide);

    return () => {
      dialogElement.removeEventListener('wa-after-hide', handleAfterHide);
    };
  }, [closeActivePlant]);

  // Skeleton de carga
  if (loading) {

    return (
      <div className="plants-grid wa-grid wa-gap-2xl">
        {[...Array(6)].map((_, i) => (
          <wa-card key={i} className="skeleton-card" appearance="filled">
            <wa-skeleton slot="media" effect="pulse" style={{ height: '220px' }}></wa-skeleton>

            <div slot="header" className="plant-header-wrapper">
              <div className="wa-cluster wa-gap-m wa-align-items-center plant-header wa-heading-l" style={{ width: '100%' }}>
                <wa-skeleton effect="pulse" style={{ height: '24px', width: '52%' }}></wa-skeleton>
                <wa-skeleton effect="pulse" style={{ height: '28px', width: '96px', borderRadius: '999px' }}></wa-skeleton>
              </div>
            </div>

            <div slot="header-actions" className="wa-cluster wa-gap-xs">
              <wa-skeleton effect="pulse" style={{ height: '26px', width: '82px', borderRadius: '999px' }}></wa-skeleton>
              <wa-skeleton effect="pulse" style={{ height: '26px', width: '74px', borderRadius: '999px' }}></wa-skeleton>
            </div>

            <div className="plant-body">
              <div className="wa-split wa-gap-xs plant-tags" style={{ '--spacing': '0' }}>
                <div className="wa-cluster wa-gap-xs">
                  <wa-skeleton effect="pulse" style={{ height: '16px', width: '16px', borderRadius: '4px', flexShrink: '0' }}></wa-skeleton>
                  <wa-skeleton effect="pulse" style={{ height: '16px', width: '48px' }}></wa-skeleton>
                </div>
                <div className="wa-cluster wa-gap-xs">
                  <wa-skeleton effect="pulse" style={{ height: '16px', width: '16px', borderRadius: '4px', flexShrink: '0' }}></wa-skeleton>
                  <wa-skeleton effect="pulse" style={{ height: '16px', width: '62px' }}></wa-skeleton>
                </div>
                <div className="wa-cluster wa-gap-xs">
                  <wa-skeleton effect="pulse" style={{ height: '16px', width: '16px', borderRadius: '4px', flexShrink: '0' }}></wa-skeleton>
                  <wa-skeleton effect="pulse" style={{ height: '16px', width: '42px' }}></wa-skeleton>
                </div>
                <div className="wa-cluster wa-gap-xs">
                  <wa-skeleton effect="pulse" style={{ height: '16px', width: '16px', borderRadius: '4px', flexShrink: '0' }}></wa-skeleton>
                  <wa-skeleton effect="pulse" style={{ height: '16px', width: '74px' }}></wa-skeleton>
                </div>
              </div>
            </div>

            <div slot="footer" className="plant-price-wrapper">
              <div className="plant-price-header">
                <div className="price-original">
                  <wa-skeleton effect="pulse" style={{ height: '16px', width: '52%' }}></wa-skeleton>
                </div>
                <div className="price-final">
                  <wa-skeleton effect="pulse" style={{ height: '28px', width: '42%' }}></wa-skeleton>
                </div>
              </div>
            </div>

            <div slot="footer-actions" className="wa-cluster wa-gap-s">
              <wa-button-group label="Skeleton actions">
                <wa-button size="small" disabled>
                  <wa-skeleton effect="pulse" style={{ height: '14px', width: '84px' }}></wa-skeleton>
                </wa-button>
                <wa-button size="small" variant="brand" disabled>
                  <wa-skeleton effect="pulse" style={{ height: '14px', width: '58px' }}></wa-skeleton>
                </wa-button>
              </wa-button-group>
            </div>
          </wa-card>
        ))}
      </div>
    );
  }

  // Estado vacío
  if (plants.length === 0) {
    return (
    <wa-callout variant="warning">
        <wa-icon name="heart-crack" slot="icon"></wa-icon>
        No hay plantas disponibles por el momento. Por favor, vuelve más tarde o contáctanos para más información.
    </wa-callout>
    );
  }

  // Grid de plantas
  return (
    <>
      <div className='wa-stack'>
        {typeof totalPlants === 'number' && (
          <div className="plants-count"><wa-icon name="city"></wa-icon> {totalPlants} planta{totalPlants === 1 ? '' : 's'}</div>
        )}
        <div className="plants-grid wa-grid wa-gap-2xl" ref={gridContainerRef}>
          {plants.map((plant) => (
            <wa-card key={plant.id} className="plant-card box-shadow-2" appearance="filled">
                <div slot="media" className="plant-media">
                  <img
                    src={plant.imageUrl}
                    alt={plant.nombre}
                    onClick={() => openPlantDetail(plant)}
                    className="plant-image"
                  />
                  {plant.discountPercentage > 0 && (
                  <div className="discount-seal" aria-label={`Descuento ${plant.discountPercentage}%`}>
                    <span className="discount-seal-value">{plant.discountPercentage}%</span>
                    <span className="discount-seal-label">descto.</span>
                  </div>
                  )}
                </div>
                <div slot="header" className="plant-header-wrapper">
                    <div className="wa-cluster wa-gap-m wa-align-items-center plant-header wa-heading-l">
                        <span>{plant.proyectoNombre}</span>
                        <wa-badge appearance="filled-outlined" variant="neutral">Planta {plant.nombre}</wa-badge>
                    </div>
                </div>

                <div slot="header-actions" className="wa-cluster wa-gap-xs">
                  {plant.proyectoEtapa && (
                  <wa-badge variant="success" style={{ fontSize: 'var(--wa-font-size-xs)' }}>{plant.proyectoEtapa}</wa-badge>
                  )}
                    {plant.proyectoComuna && (
                    <wa-badge variant={plant.isPaid ? 'neutral' : 'brand'}><wa-icon slot="start" name="map-location"></wa-icon>{plant.proyectoComuna}</wa-badge>
                    )}
                    {plant.isPaid && (
                    <wa-badge variant="neutral"><wa-icon name="shop-slash" slot="start"></wa-icon>Pagada</wa-badge>
                    )}
                    {plant.isReserved && (
                    <wa-badge variant="warning"><wa-icon name="business-time" slot="start"></wa-icon>Reservado</wa-badge>
                    )}
                </div>

                <div className="plant-body">
                    <div className="wa-split wa-gap-xs plant-tags" style={{ '--spacing': '0' }}>
                        {plant.programa && (
                        <div className="wa-cluster wa-gap-xs">
                            <wa-icon name="kaaba"></wa-icon>
                            <span>{plant.programa}</span>
                        </div>
                        )}
                        {plant.orientacion && (
                        <div className="wa-cluster wa-gap-xs">
                            <wa-icon name="compass" slot="header"></wa-icon>
                            <span>Orient. {plant.orientacion}</span>
                        </div>
                        )}
                        {plant.piso && (
                        <div className="wa-cluster wa-gap-xs">
                            <wa-icon name="arrow-right-to-city" slot="header"></wa-icon>
                            <span>Piso {plant.piso}</span>
                        </div>
                        )}
                        {plant.superficie_util && (
                        <div className="wa-cluster wa-gap-xs">
                            <wa-icon name="ruler" slot="header"></wa-icon>
                            <span>Sup. {plant.superficie_util} m²</span>
                        </div>
                        )}
                    </div>
                </div>
                {/* Ubicación destacada */}
                {plant.proyectoComuna && (
                <div slot="footer" className="plant-price-wrapper">
                    {/* Precios destacados en el header */}
                    {(plant.precioBase || plant.precioLista) && (
                        <div className="plant-price-header">
                        {(0 < plant.precioLista) && (plant.precioLista !== plant.precioBase) && (
                            <div className="price-original">
                            <span className="price-label-small">Precio lista: </span>
                            <span className="price wa-font-weight-bold">
                                <s>UF {plant.precioLista.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</s>
                            </span>
                            </div>
                        )}
                        {(0 < plant.precioBase) && (
                            <div className="price-final">
                            {plant.precioBase < plant.precioLista && (
                            <span className="price-label-discount wa-text-uppercase">Precio Sale: </span>
                            )}
                            <span className="price-sale wa-font-weight-bold wa-heading-xl">
                                UF {(plant.precioBase).toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                            </span>
                            </div>
                        )}
                        </div>
                    )}
                </div>
                )}
                {/* Acciones */}
                <div slot="footer-actions" className="wa-cluster wa-gap-s">
                  <wa-button-group label="Alignment">
                    <wa-button
                      size="small"
                      {...(detailLoadingId === plant.id && { loading: true })}
                      onClick={() => openPlantDetail(plant)}
                    >
                      <wa-icon name="building-circle-exclamation" slot="start"></wa-icon>
                      Ver Detalles
                    </wa-button>
                    <wa-button
                      size="small"
                      variant="brand"
                      disabled={checkoutLoading || plant.isReserved || plant.isPaid || plant.isAvailable === false}
                      {...(checkoutLoading && { loading: true })}
                      onClick={() => onQuickCheckout(plant)}
                    >
                      {plant.isPaid
                        ? <><wa-icon name="house-circle-xmark" slot="start"></wa-icon>Pagada</>
                        : plant.isReserved
                          ? <><wa-icon name="house-lock" slot="start"></wa-icon>Reservado</>
                          : plant.isAvailable === false
                            ? <><wa-icon name="house-chimney-crack" slot="start"></wa-icon>No disponible</>
                        : checkoutLoading
                          ? 'Cargando...'
                          : <><wa-icon name="comments-dollar" slot="start"></wa-icon>Reservar</>
                      }
                    </wa-button>
                  </wa-button-group>
                </div>
              </wa-card>
          ))}
        </div>
      </div>

      {/* Diálogo - Detalles de Planta */}
      <PlantDetailDialog
        plant={activePlant}
        dialogRef={dialogRef}
        checkoutLoading={checkoutLoading}
        onCheckout={handleCheckoutFromDialog}
      />

      {/* Paginación */}
      {totalPages > 1 && (
        <div className="wa-stack pagination">
          <wa-divider></wa-divider>
          <div className="wa-split wa-align-items-center">
            <span className="wa-caption-m pagination-info">
              Mostrando {showingFrom} a {showingTo} de {totalPlants || 0} resultados
            </span>
            <wa-button-group orientation="horizontal" label="Paginación">
              <wa-button appearance="outlined" disabled={page === 1} onClick={() => onPageChange(page - 1)}>
                <wa-icon name="chevron-left"></wa-icon>
              </wa-button>

              {paginationItems.map((item, index) => {
                if (typeof item === 'string') {
                  return (
                    <wa-button key={`${item}-${index}`} appearance="outlined" disabled>
                      ...
                    </wa-button>
                  );
                }

                const isActivePage = item === page;

                return (
                  <wa-button
                    key={item}
                    appearance={isActivePage ? 'accent' : 'outlined'}
                    {...(isActivePage ? { variant: 'brand' } : {})}
                    onClick={() => onPageChange(item)}
                  >
                    {item}
                  </wa-button>
                );
              })}

              <wa-button appearance="outlined" disabled={page === totalPages} onClick={() => onPageChange(page + 1)}>
                <wa-icon name="chevron-right"></wa-icon>
              </wa-button>
            </wa-button-group>
          </div>
        </div>
      )}
    </>
  );
}

export default PlantsGrid;
