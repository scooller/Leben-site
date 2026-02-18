import { useEffect, useRef, useState } from 'react';
import { useSiteConfig } from '../contexts/SiteConfigContext';
import PlantsService from '../services/plants';
import CheckoutService from '../services/checkout';
import { proyectosService } from '../services/proyectos';
import ErrorNotification from '../components/ErrorNotification';
import PlantsGrid from '../components/PlantsGrid';
import { isRetryableError } from '../utils/errorHandler';
import '../styles/home.scss' with { type: 'css' };

/**
 * Página principal - Catálogo de plantas
 * Usa Web Awesome components de forma nativa con íconos integrados
 */
function Home() {
  const { config, loading: configLoading } = useSiteConfig();
  const [plants, setPlants] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [checkoutError, setCheckoutError] = useState(null);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalPlants, setTotalPlants] = useState(0);
  const [gateways, setGateways] = useState([]);
  const [checkoutLoading, setCheckoutLoading] = useState(false);
  const [plantForCheckout, setPlantForCheckout] = useState(null);
  const [selectedGateway, setSelectedGateway] = useState('');
  
  // Estados para filtros
  const [proyectos, setProyectos] = useState([]);
  const [selectedProyecto, setSelectedProyecto] = useState('');
  const [selectedDormitorios, setSelectedDormitorios] = useState('');
  const [selectedBanos, setSelectedBanos] = useState('');
  const [selectedPrecioMin, setSelectedPrecioMin] = useState('');
  const [selectedPrecioMax, setSelectedPrecioMax] = useState('');
  
  // Estados temporales para filtros (antes de aplicar)
  const [tempProyecto, setTempProyecto] = useState('');
  const [tempDormitorios, setTempDormitorios] = useState('');
  const [tempBanos, setTempBanos] = useState('');
  const [tempPrecioMin, setTempPrecioMin] = useState('');
  const [tempPrecioMax, setTempPrecioMax] = useState('');
  
  const gatewayDialogRef = useRef(null);

  // Cargar proyectos para el filtro
  useEffect(() => {
    const fetchProyectos = async () => {
      try {
        const data = await proyectosService.getProyectos({ perPage: 100 });
        setProyectos(data.data || []);
      } catch (err) {
        console.error('Error cargando proyectos:', err);
      }
    };
    fetchProyectos();
  }, []);

  // Cargar plantas cuando cambian los filtros
  useEffect(() => {
    console.log('🔄 [USEEFFECT] Detectado cambio en filtros');
    console.log('📊 Estados actuales:', {
      page,
      selectedProyecto,
      selectedDormitorios,
      selectedBanos,
      selectedPrecioMin,
      selectedPrecioMax
    });
    
    const loadPlants = async () => {
      try {
        setLoading(true);
        setError(null);
        
        const filters = {
          page,
          perPage: 12,
        };
        
        if (selectedProyecto) {
          filters.salesforce_proyecto_id = selectedProyecto;
        }
        
        if (selectedDormitorios) {
          filters.programa = selectedDormitorios;
        }
        
        if (selectedBanos) {
          filters.programa2 = selectedBanos;
        }
        
        if (selectedPrecioMin) {
          filters.min_precio = selectedPrecioMin;
        }
        
        if (selectedPrecioMax) {
          filters.max_precio = selectedPrecioMax;
        }
        
        console.log('🚀 [API] Enviando petición con filtros:', filters);
        
        const data = await PlantsService.getAll(filters);
        
        const totalCount = data.total ?? data.data?.length ?? 0;

        console.log('📦 [API] Respuesta recibida:', {
          totalPlantas: totalCount,
          totalPages: data.last_page,
          currentPage: data.current_page
        });
        
        const mappedPlants = (data.data || []).map(plant => ({
          ...plant,
          nombre: plant.name,
          categoria: plant.programa,
          precioBase: Number(plant.precio_base) || 0,
          precioLista: Number(plant.precio_lista) || 0,
          proyectoNombre: plant.proyecto?.name,
          proyectoDescripcion: plant.proyecto?.descripcion,
          proyectoDireccion: plant.proyecto?.direccion,
          proyectoComuna: plant.proyecto?.comuna,
        }));
        
        // Debug: Ver valores de precios
        if (mappedPlants.length > 0) {
          console.log('🔍 [DEBUG] Primeras 3 plantas con precios:', 
            mappedPlants.slice(0, 3).map(p => ({
              id: p.id,
              nombre: p.nombre,
              precioBase: p.precioBase,
              precioLista: p.precioLista,
              diferencia: p.precioBase !== p.precioLista,
              baseEsMenor: p.precioBase < p.precioLista
            }))
          );
        }
        
        console.log('✅ [MAPEO] Plantas mapeadas:', mappedPlants.length);
        
        setPlants(mappedPlants);
        setTotalPages(data.last_page || 1);
        setTotalPlants(totalCount);
      } catch (err) {
        console.error('❌ [ERROR] Error al cargar plantas:', err);
        const errorInfo = {
          type: err.type || 'unknown',
          message: err.message || 'Error al cargar las plantas',
          userMessage: err.userMessage || 'No se pudieron cargar las plantas. Por favor, intenta de nuevo.',
          title: 'Error al cargar plantas',
          canRetry: isRetryableError(err),
        };
        setError(errorInfo);
        console.error('Error cargando plantas:', err);
      } finally {
        setLoading(false);
        console.log('🏁 [CARGA] Finalizada');
      }
    };

    loadPlants();
  }, [page, selectedProyecto, selectedDormitorios, selectedBanos, selectedPrecioMin, selectedPrecioMax]);

  // Aplicar filtros
  const handleApplyFilters = () => {
    console.log('🔍 [FILTROS] Aplicando filtros...');
    console.log('📋 Valores temporales:', {
      tempProyecto,
      tempDormitorios,
      tempBanos,
      tempPrecioMin,
      tempPrecioMax
    });
    
    setSelectedProyecto(tempProyecto);
    setSelectedDormitorios(tempDormitorios);
    setSelectedBanos(tempBanos);
    setSelectedPrecioMin(tempPrecioMin);
    setSelectedPrecioMax(tempPrecioMax);
    setPage(1); // Volver a la primera página al aplicar filtros
    
    console.log('✅ [FILTROS] Estados actualizados');
  };

  // Limpiar filtros
  const handleClearFilters = () => {
    console.log('🧹 [FILTROS] Limpiando filtros...');
    setTempProyecto('');
    setTempDormitorios('');
    setTempBanos('');
    setTempPrecioMin('');
    setTempPrecioMax('');
    setSelectedProyecto('');
    setSelectedDormitorios('');
    setSelectedBanos('');
    setSelectedPrecioMin('');
    setSelectedPrecioMax('');
    setPage(1);
    console.log('✅ [FILTROS] Filtros limpiados');
  };

  const closeGatewayDialog = () => {
    if (gatewayDialogRef.current) {
      gatewayDialogRef.current.open = false;
    }
    setPlantForCheckout(null);
  };

  // Cargar pasarelas disponibles
  useEffect(() => {
    const fetchGateways = async () => {
      try {
        const availableGateways = await CheckoutService.getAvailableGateways();
        setGateways(availableGateways);
      } catch (err) {
        // Error no crítico, el usuario puede no ver las pasarelas pero no bloquea la app
        console.error('Error cargando pasarelas:', err);
        setCheckoutError({
          type: err.type || 'gateway',
          message: err.message || 'Error al cargar pasarelas',
          userMessage: err.userMessage || 'No se pudieron cargar las pasarelas de pago. Intenta recargar la página.',
          title: 'Aviso',
        });
      }
    };
    fetchGateways();
  }, []);

  // Manejar compra directo desde la tarjeta
  const handleQuickCheckout = async (plant) => {
    setPlantForCheckout(plant);
    setSelectedGateway(gateways.length > 0 ? gateways[0].id : '');
    if (gatewayDialogRef.current) {
      gatewayDialogRef.current.open = true;
    }
  };

  // Confirmar checkout con pasarela seleccionada
  const handleConfirmCheckout = async () => {
    if (!plantForCheckout || !selectedGateway) {
      setCheckoutError({
        type: 'validation',
        message: 'Datos incompletos',
        userMessage: 'Por favor selecciona una planta y una pasarela de pago',
        title: 'Error de validación',
      });
      return;
    }

    try {
      setCheckoutLoading(true);
      setCheckoutError(null);
      const response = await CheckoutService.initiate(plantForCheckout.id, 1, selectedGateway);
      
      // Cerrar diálogo antes de redirigir
      if (gatewayDialogRef.current) {
        gatewayDialogRef.current.open = false;
      }
      
      // Redirigir a la pasarela
      CheckoutService.redirect(response.redirect_url);
    } catch (err) {
      setCheckoutError({
        type: err.type || 'unknown',
        message: err.message || 'Error en checkout',
        userMessage: err.userMessage || 'Error al iniciar el checkout. Por favor, intenta de nuevo.',
        title: 'Error en el pago',
        details: err.details,
      });
      setCheckoutLoading(false);
    }
  };

  if (configLoading) {
    return (
      <div className="home-container">
        <div className="loading-skeletons">
          <wa-skeleton effect="pulse" style={{ height: '100px', marginBottom: '2rem' }}></wa-skeleton>
          <wa-skeleton effect="pulse" style={{ height: '60px', marginBottom: '2rem' }}></wa-skeleton>
          
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
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="home-container">
        <wa-card>
          <div slot="header">
            <h2>{error.title || 'Error'}</h2>
          </div>
          <wa-callout variant="danger">
            <wa-icon slot="icon" name="circle-exclamation" variant="regular" animation="beat"></wa-icon>
            <strong>No se pudieron cargar las plantas</strong>
            <div style={{ marginTop: '8px' }}>
              {error.userMessage || error.message}
            </div>
          </wa-callout>
          <div style={{ marginTop: '16px', display: 'flex', gap: '8px' }}>
            <wa-button onClick={() => loadPlants()} variant="primary">
              <wa-icon slot="start" name="arrow-rotate-right" animation="spin"></wa-icon>
              Reintentar
            </wa-button>
            {error.canRetry && (
              <wa-button onClick={() => window.location.reload()} variant="default">
                Recargar página
              </wa-button>
            )}
          </div>
        </wa-card>
      </div>
    );
  }

  return (
    <div className="home-container">
      {/* Hero Section */}
      <div className="hero-section">
        {config?.logo && (
          <img src={config.logo} alt={config?.site_name} className="hero-logo" />
        )}
        <h1>{config?.site_name}</h1>
        <p>{config?.site_description}</p>
      </div>

      {/* Header de Plantas */}
      <div className="plants-header">
        <div className="wa-cluster wa-gap-s wa-align-items-center">
          <h2>Nuestras Plantas</h2>
          {(selectedProyecto || selectedDormitorios || selectedBanos || selectedPrecioMin || selectedPrecioMax) && (
            <wa-badge variant="brand" pill>
              {[selectedProyecto, selectedDormitorios, selectedBanos, selectedPrecioMin, selectedPrecioMax].filter(Boolean).length} {[selectedProyecto, selectedDormitorios, selectedBanos, selectedPrecioMin, selectedPrecioMax].filter(Boolean).length === 1 ? 'filtro' : 'filtros'} activo{[selectedProyecto, selectedDormitorios, selectedBanos, selectedPrecioMin, selectedPrecioMax].filter(Boolean).length === 1 ? '' : 's'}
            </wa-badge>
          )}
        </div>
        <p>Descubre nuestra colección disponible</p>
      </div>

      {/* Filtros */}
      <wa-details summary="Filtros" className="filters-details">
        <div className="wa-stack wa-gap-m">
          <div className="wa-cluster wa-gap-m filters-inputs">
            <wa-select
              label="Proyecto"
              placeholder="Todos los proyectos"
              value={tempProyecto}
              onChange={(e) => {
                const value = e.target.value || '';
                console.log('🏢 [FILTRO] Proyecto cambiado:', value);
                setTempProyecto(value);
              }}
              multiple
              clearable
            >
              <wa-option value="">Todos los proyectos</wa-option>
              {proyectos.map((proyecto) => (
                <wa-option key={proyecto.id} value={proyecto.salesforce_id}>
                  {proyecto.name}
                </wa-option>
              ))}
            </wa-select>

            <wa-select
              label="Dormitorios"
              placeholder="Todos"
              value={tempDormitorios}
              onChange={(e) => {
                const value = e.target.value || '';
                console.log('🛏️ [FILTRO] Dormitorios cambiado:', value);
                setTempDormitorios(value);
              }}
              clearable
            >
              <wa-option value="">Todos</wa-option>
              <wa-option value="ST">Studio</wa-option>
              <wa-option value="1D">1 Dormitorio</wa-option>
              <wa-option value="2D">2 Dormitorios</wa-option>
              <wa-option value="3D">3 Dormitorios</wa-option>
              <wa-option value="4D">4 Dormitorios</wa-option>
            </wa-select>

            <wa-select
              label="Baños"
              placeholder="Todos"
              value={tempBanos}
              onChange={(e) => {
                const value = e.target.value || '';
                console.log('🚿 [FILTRO] Baños cambiado:', value);
                setTempBanos(value);
              }}
              clearable
            >
              <wa-option value="">Todos</wa-option>
              <wa-option value="1B">1 Baño</wa-option>
              <wa-option value="2B">2 Baños</wa-option>
              <wa-option value="3B">3 Baños</wa-option>
            </wa-select>

            <wa-input
              type="number"
              label="Precio Mínimo"
              placeholder="Desde UF"
              value={tempPrecioMin}
              onChange={(e) => {
                const value = e.target.value || '';
                console.log('💰 [FILTRO] Precio mínimo cambiado:', value);
                setTempPrecioMin(value);
              }}
              clearable
            >
              <wa-icon slot="start" name="dollar-sign"></wa-icon>
            </wa-input>

            <wa-input
              type="number"
              label="Precio Máximo"
              placeholder="Hasta UF"
              value={tempPrecioMax}
              onChange={(e) => {
                const value = e.target.value || '';
                console.log('💵 [FILTRO] Precio máximo cambiado:', value);
                setTempPrecioMax(value);
              }}
              clearable
            >
              <wa-icon slot="start" name="dollar-sign"></wa-icon>
            </wa-input>
          </div>

          <div className="wa-cluster wa-gap-s filters-actions">
            <wa-button 
              variant="brand"
              onClick={handleApplyFilters}
            >
              <wa-icon slot="start" name="filter"></wa-icon>
              Aplicar Filtros
            </wa-button>

            {(selectedProyecto || selectedDormitorios || selectedBanos || selectedPrecioMin || selectedPrecioMax) && (
              <wa-button 
                variant="neutral"
                onClick={handleClearFilters}
              >
                <wa-icon slot="start" name="xmark"></wa-icon>
                Limpiar Filtros
              </wa-button>
            )}
          </div>
        </div>
      </wa-details>

      {/* Plantas Grid */}
      <PlantsGrid 
        plants={plants}
        loading={loading}
        checkoutLoading={checkoutLoading}
        onQuickCheckout={handleQuickCheckout}
        totalPlants={totalPlants}
        page={page}
        totalPages={totalPages}
        onPageChange={setPage}
      />

      {/* Diálogo - Selección de Pasarela de Pago */}
      <wa-dialog
        ref={gatewayDialogRef}
        label="Seleccionar Pasarela de Pago"
        style={{ '--width': '500px' }}
        light-dismiss
      >
        <div className="gateway-selection">
          <p className="gateway-instructions">Selecciona cómo deseas realizar el pago:</p>
          
          {gateways.length > 0 ? (
            <wa-radio-group
              value={selectedGateway}
              onWaChange={(e) => setSelectedGateway(e.target.value)}
            >
              {gateways.map((gateway) => (
                <wa-radio key={gateway.id} value={gateway.id}>
                  <div className="gateway-option-content">
                    <strong>{gateway.name}</strong>
                    <br />
                    <small>{gateway.description}</small>
                  </div>
                </wa-radio>
              ))}
            </wa-radio-group>
          ) : (
            <wa-callout variant="warning">
              No hay pasarelas de pago configuradas
            </wa-callout>
          )}
        </div>

        <wa-button 
          slot="footer"
          variant="neutral"
          data-dialog="close" 
          disabled={checkoutLoading}
        >
          Cancelar
        </wa-button>
        <wa-button 
          slot="footer"
          variant="brand" 
          onClick={handleConfirmCheckout}
          disabled={checkoutLoading || !selectedGateway}
          {...(checkoutLoading && { loading: true })}
        >
          {checkoutLoading ? 'Procesando...' : 'Continuar al Pago'}
        </wa-button>
      </wa-dialog>

      {/* Notificación de errores de checkout */}
      <ErrorNotification 
        error={checkoutError} 
        onClose={() => setCheckoutError(null)}
        duration={6000}
      />
    </div>
  );
}

export default Home;
