import { useState, useRef } from 'react';

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
  onPageChange
}) {
  const [selectedPlant, setSelectedPlant] = useState(null);
  const dialogRef = useRef(null);

  const openPlantDetail = (plant) => {
    setSelectedPlant(plant);
    if (dialogRef.current) {
      dialogRef.current.open = true;
    }
  };

  const handleCheckoutFromDialog = () => {
    if (!selectedPlant) return;
    // Cerrar el diálogo
    if (dialogRef.current) {
      dialogRef.current.open = false;
    }
    // Llamar al checkout con la planta seleccionada
    onQuickCheckout(selectedPlant);
  };
  
  // Skeleton de carga
  if (loading) {
    return (
      <div className="plants-grid">
        {[...Array(6)].map((_, i) => (
          <wa-card key={i} className="skeleton-card">
            <wa-skeleton effect="pulse" style={{ height: '200px', marginBottom: '1rem' }}></wa-skeleton>
            <wa-skeleton effect="pulse" style={{ height: '40px', marginBottom: '0.5rem' }}></wa-skeleton>
            <wa-skeleton effect="pulse" style={{ height: '80px', marginBottom: '0.5rem' }}></wa-skeleton>
            <wa-skeleton effect="pulse" style={{ height: '30px', marginBottom: '0.5rem' }}></wa-skeleton>
            <wa-skeleton effect="pulse" style={{ height: '100px' }}></wa-skeleton>
          </wa-card>
        ))}
      </div>
    );
  }

  // Estado vacío
  if (plants.length === 0) {
    return (
      <div className="empty-plants">
        {typeof totalPlants === 'number' && (
          <div className="plants-count">Total: {totalPlants} planta{totalPlants === 1 ? '' : 's'}</div>
        )}
        <p><wa-icon name="heart-crack"></wa-icon> No hay plantas disponibles</p>
      </div>
    );
  }

  // Grid de plantas
  return (
    <>
      {typeof totalPlants === 'number' && (
        <div className="plants-count">Total: {totalPlants} planta{totalPlants === 1 ? '' : 's'}</div>
      )}
      <div className="plants-grid wa-grid">
        {plants.map((plant) => (
          <wa-card key={plant.id} className="plant-card" appearance="filled">
            {/* {plant.imagen && ( */}
            <img slot="media" src="https://sale.ileben.cl/wp-content/uploads/2022/11/portada_bold_terraza.jpg" alt={plant.nombre} className="plant-image" />
            {/* )} */}

            <div slot="header" className="plant-header-wrapper">
              <div className="wa-cluster wa-gap-m wa-align-items-center plant-header wa-heading-l">
                <span>{plant.proyectoNombre}</span>
                <span>Planta {plant.nombre}</span>               
              </div>              
            </div>
            {plant.categoria && (
                <wa-badge variant="brand" slot="header-actions">{plant.categoria}</wa-badge>
            )}
            <div className="plant-body">
                <div className="wa-split">
                    <div className="wa-cluster wa-gap-xs wa-align-items-center">              
                        <wa-icon name="location-dot" style={{ fontSize: '1em' }}></wa-icon>
                        <span>{plant.proyectoComuna}</span>
                    </div>                
                    {/* Tags para información adicional */}
                    <div className="wa-cluster wa-gap-xs plant-tags" style={{ '--spacing': '0' }}>
                        {plant.orientacion && (
                        <wa-card appearance="plain" className="wa-align-self-center wa-align-items-center">
                            <wa-icon name="compass" slot="header"></wa-icon>
                            <span>Orient. {plant.orientacion}</span>
                        </wa-card>
                        )}
                        {plant.piso && (
                        <wa-card appearance="plain" className="wa-align-self-center wa-align-items-center">
                            <wa-icon name="building" slot="header"></wa-icon>                     
                            <span>Piso {plant.piso}</span>
                        </wa-card>
                        )}
                        {plant.superficie_util && (
                        <wa-card appearance="plain" className="wa-align-self-center wa-align-items-center">
                            <wa-icon name="ruler" slot="header"></wa-icon>
                            <span>Sup. {plant.superficie_util} m²</span>
                        </wa-card>
                        )}
                    </div>
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
                            <span className="price-label-discount">Precio sale: </span>
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
                <wa-button size="small" onClick={() => openPlantDetail(plant)}>
                  <wa-icon name="building-circle-exclamation" slot="start"></wa-icon>
                  Ver Detalles
                </wa-button>
                <wa-button 
                  size="small" 
                  variant="brand" 
                  disabled={checkoutLoading} 
                  {...(checkoutLoading && { loading: true })} 
                  onClick={() => onQuickCheckout(plant)}
                >
                  {checkoutLoading ? 'Cargando...' : <><wa-icon name="comments-dollar" slot="start"></wa-icon>Cotizar</>}
                </wa-button>
              </wa-button-group>
            </div>
          </wa-card>
        ))}
      </div>

      {/* Diálogo - Detalles de Planta */}
      <wa-dialog
        ref={dialogRef}
        label={selectedPlant?.nombre || 'Detalle de Planta'}
        style={{ '--width': '600px' }}
        light-dismiss
      >
        {selectedPlant && (
          <>
            {selectedPlant.imagen && (
              <img src={selectedPlant.imagen} alt={selectedPlant.nombre} className="detail-img" />
            )}

            <div className="detail-content">
              {selectedPlant.proyectoNombre && (
                <div className="detail-row">
                  <strong>Proyecto:</strong>
                  <span>{selectedPlant.proyectoNombre}</span>
                </div>
              )}
              {selectedPlant.proyectoComuna && (
                <div className="detail-row">
                  <strong>Ubicación:</strong>
                  <span>{selectedPlant.proyectoComuna}</span>
                </div>
              )}
              {selectedPlant.proyectoDescripcion && (
                <div className="detail-row proyecto-desc-row">
                  <strong>Descripción del Proyecto:</strong>
                  <span>{selectedPlant.proyectoDescripcion}</span>
                </div>
              )}
              <div className="detail-row">
                <strong>Nombre:</strong>
                <span>{selectedPlant.nombre}</span>
              </div>
              {selectedPlant.categoria && (
                <div className="detail-row">
                  <strong>Categoría:</strong>
                  <wa-badge variant="brand">{selectedPlant.categoria}</wa-badge>
                </div>
              )}
              {selectedPlant.programa && (
                <div className="detail-row">
                  <strong>Programa:</strong>
                  <span>{selectedPlant.programa}</span>
                </div>
              )}
              {selectedPlant.orientacion && (
                <div className="detail-row">
                  <strong>Orientación:</strong>
                  <wa-tag variant="primary">{selectedPlant.orientacion}</wa-tag>
                </div>
              )}
              {selectedPlant.piso && (
                <div className="detail-row">
                  <strong>Piso:</strong>
                  <wa-tag variant="primary">{selectedPlant.piso}</wa-tag>
                </div>
              )}
              {(selectedPlant.superficie_total_principal !== null && selectedPlant.superficie_total_principal !== undefined
                || selectedPlant.superficie_interior !== null && selectedPlant.superficie_interior !== undefined
                || selectedPlant.superficie_util !== null && selectedPlant.superficie_util !== undefined
                || selectedPlant.superficie_terraza !== null && selectedPlant.superficie_terraza !== undefined
                || selectedPlant.superficie_vendible !== null && selectedPlant.superficie_vendible !== undefined) && (
                <div className="detail-row">
                  <strong>Superficies:</strong>
                  <div
                    style={{
                      display: 'grid',
                      gridTemplateColumns: '1fr 1fr',
                      gap: '8px 16px',
                      marginTop: '8px',
                    }}
                  >
                    {selectedPlant.superficie_total_principal !== null && selectedPlant.superficie_total_principal !== undefined && (
                      <div>
                        <div className="wa-cluster wa-gap-xs wa-align-items-center">
                          <wa-icon name="house" style={{ fontSize: '0.9em' }}></wa-icon>
                          <span>Total principal</span>
                        </div>
                        <strong>{selectedPlant.superficie_total_principal} m²</strong>
                      </div>
                    )}
                    {selectedPlant.superficie_interior !== null && selectedPlant.superficie_interior !== undefined && (
                      <div>
                        <div className="wa-cluster wa-gap-xs wa-align-items-center">
                          <wa-icon name="door-open" style={{ fontSize: '0.9em' }}></wa-icon>
                          <span>Interior</span>
                        </div>
                        <strong>{selectedPlant.superficie_interior} m²</strong>
                      </div>
                    )}
                    {selectedPlant.superficie_util !== null && selectedPlant.superficie_util !== undefined && (
                      <div>
                        <div className="wa-cluster wa-gap-xs wa-align-items-center">
                          <wa-icon name="ruler" style={{ fontSize: '0.9em' }}></wa-icon>
                          <span>Útil</span>
                        </div>
                        <strong>{selectedPlant.superficie_util} m²</strong>
                      </div>
                    )}
                    {selectedPlant.superficie_terraza !== null && selectedPlant.superficie_terraza !== undefined && (
                      <div>
                        <div className="wa-cluster wa-gap-xs wa-align-items-center">
                          <wa-icon name="umbrella-beach" style={{ fontSize: '0.9em' }}></wa-icon>
                          <span>Terraza</span>
                        </div>
                        <strong>{selectedPlant.superficie_terraza} m²</strong>
                      </div>
                    )}
                    {selectedPlant.superficie_vendible !== null && selectedPlant.superficie_vendible !== undefined && (
                      <div>
                        <div className="wa-cluster wa-gap-xs wa-align-items-center">
                          <wa-icon name="layer-group" style={{ fontSize: '0.9em' }}></wa-icon>
                          <span>Vendible</span>
                        </div>
                        <strong>{selectedPlant.superficie_vendible} m²</strong>
                      </div>
                    )}
                  </div>
                </div>
              )}
              {(selectedPlant.precioBase || selectedPlant.precioLista) && (
                <div className="detail-row price-row">
                  <strong>Precio:</strong>
                  <div className="price-details">
                    {selectedPlant.precioLista && selectedPlant.precioBase && selectedPlant.precioLista !== selectedPlant.precioBase && (
                      <div className="price-original-detail">
                        <span className="price-label-small">Precio lista:</span>
                        <span className="price-crossed">
                          UF {selectedPlant.precioLista.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                        </span>
                      </div>
                    )}
                    <span className="price-highlight">
                      UF {(selectedPlant.precioBase || selectedPlant.precioLista).toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                    </span>
                    {selectedPlant.precioBase && selectedPlant.precioLista && selectedPlant.precioBase < selectedPlant.precioLista && (
                      <span className="discount-badge">¡Con descuento!</span>
                    )}
                  </div>
                </div>
              )}
            </div>

            <wa-button 
              slot="footer"
              variant="neutral"
              data-dialog="close"
            >
              Cerrar
            </wa-button>
            <wa-button 
              slot="footer"
              variant="brand" 
              disabled={checkoutLoading} 
              {...(checkoutLoading && { loading: true })} 
              onClick={handleCheckoutFromDialog}
            >
              {checkoutLoading ? 'Cargando...' : 'Cotizar Ahora'}
            </wa-button>
          </>
        )}
      </wa-dialog>

      {/* Paginación */}
      {totalPages > 1 && (
        <div className="wa-cluster wa-gap-l wa-align-items-center pagination">
          <wa-button disabled={page === 1} onClick={() => onPageChange(page - 1)}>
            ← Anterior
          </wa-button>
          <span className="pagination-info">Página {page} de {totalPages}</span>
          <wa-button disabled={page === totalPages} onClick={() => onPageChange(page + 1)}>
            Siguiente →
          </wa-button>
        </div>
      )}
    </>
  );
}

export default PlantsGrid;
