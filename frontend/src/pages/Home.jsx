import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useSiteConfig } from '../contexts/SiteConfigContext';
import PlantsService from '../services/plants';
import CheckoutService from '../services/checkout';
import { proyectosService } from '../services/proyectos';
import { authService } from '../services/auth';
import ErrorNotification from '../components/ErrorNotification';
import PlantsGrid from '../components/PlantsGrid';
import BannerPromo from '../components/BannerPromo';
import PaymentGatewayDialog from '../components/PaymentGatewayDialog';
import { isRetryableError } from '../utils/errorHandler';
import gsap from 'gsap';
import '../styles/home.scss' with { type: 'css' };

const PLANT_DETAIL_BASE_PATH = '/p';

const slugifySegment = (value) => (
  `${value ?? ''}`
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[^\w\s-]/g, '')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
);

const parsePlantDetailPath = (pathname) => {
  const matcher = new RegExp(`^${PLANT_DETAIL_BASE_PATH}/([^/]+)/([^/]+)/?$`);
  const matches = pathname.match(matcher);

  if (!matches) {
    return null;
  }

  return {
    projectSlug: decodeURIComponent(matches[1]),
    unitName: decodeURIComponent(matches[2]),
  };
};

const getCurrentBrowserUrl = () => `${window.location.pathname}${window.location.search}${window.location.hash}`;

const normalizeBrowserUrl = (url) => {
  try {
    const parsedUrl = new URL(url, window.location.origin);

    return `${parsedUrl.pathname}${parsedUrl.search}${parsedUrl.hash}`;
  } catch {
    return '/';
  }
};

/**
 * Página principal - Catálogo de plantas
 * Usa Web Awesome components de forma nativa con íconos integrados
 */
function Home() {
  const { config, loading: configLoading, colorMode, toggleColorMode } = useSiteConfig();
  const [plants, setPlants] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [checkoutError, setCheckoutError] = useState(null);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalPlants, setTotalPlants] = useState(0);
  const [gateways, setGateways] = useState([]);
  const [checkoutLoading, setCheckoutLoading] = useState(false);
  const [manualProofLoading, setManualProofLoading] = useState(false);
  const [manualPayment, setManualPayment] = useState(null);
  const [plantForCheckout, setPlantForCheckout] = useState(null);
  const [gatewayDialogOpen, setGatewayDialogOpen] = useState(false);
  const [selectedPlantDetail, setSelectedPlantDetail] = useState(null);
  const [routePlantLoading, setRoutePlantLoading] = useState(false);
  const [routePlantParams, setRoutePlantParams] = useState(() => parsePlantDetailPath(window.location.pathname));
  const isAuthenticated = authService.isAuthenticated();

  // Estados para filtros
  const [proyectos, setProyectos] = useState([]);
  const [pisoOptions, setPisoOptions] = useState([]);
  const [regionOptions, setRegionOptions] = useState([]);
  const [comunaOptions, setComunaOptions] = useState([]);
  const [comunasByRegion, setComunasByRegion] = useState({});
  const [selectedProyecto, setSelectedProyecto] = useState([]);
  const [selectedDormitorios, setSelectedDormitorios] = useState([]);
  const [selectedBanos, setSelectedBanos] = useState([]);
  const [selectedPiso, setSelectedPiso] = useState('');
  const [selectedComuna, setSelectedComuna] = useState('');
  const [selectedRegion, setSelectedRegion] = useState('');
  const [selectedPrecioMin, setSelectedPrecioMin] = useState('');
  const [selectedPrecioMax, setSelectedPrecioMax] = useState('');

  // Estados temporales para filtros (antes de aplicar)
  const [tempProyecto, setTempProyecto] = useState([]);
  const [tempDormitorios, setTempDormitorios] = useState([]);
  const [tempBanos, setTempBanos] = useState([]);
  const [tempPiso, setTempPiso] = useState('');
  const [tempComuna, setTempComuna] = useState('');
  const [tempRegion, setTempRegion] = useState('');
  const [tempPrecioMin, setTempPrecioMin] = useState('');
  const [tempPrecioMax, setTempPrecioMax] = useState('');

  const heroRef = useRef(null);

  const normalizeMultiValue = (value) => {
    if (Array.isArray(value)) {
      return value.filter((item) => item !== null && item !== undefined && `${item}`.trim() !== '');
    }

    if (value === null || value === undefined || `${value}`.trim() === '') {
      return [];
    }

    return [value];
  };

  const getMultiSelectValue = (event) => normalizeMultiValue(event?.target?.value);

  const getSingleSelectValue = (event) => {
    const value = event?.target?.value;

    if (Array.isArray(value)) {
      return value[0] || '';
    }

    if (value === null || value === undefined) {
      return '';
    }

    return `${value}`;
  };

  const filteredComunaOptions = useMemo(() => (
    tempRegion
      ? (comunasByRegion[tempRegion] || [])
      : comunaOptions
  ), [comunaOptions, comunasByRegion, tempRegion]);

  const activeFilterCount = selectedProyecto.length
    + selectedDormitorios.length
    + selectedBanos.length
    + (selectedPiso ? 1 : 0)
    + (selectedComuna ? 1 : 0)
    + (selectedRegion ? 1 : 0)
    + (selectedPrecioMin ? 1 : 0)
    + (selectedPrecioMax ? 1 : 0);

  const mapPlant = useCallback((plant) => {
    const precioBase = Number(plant.precio_base) || 0;
    const precioLista = Number(plant.precio_lista) || 0;
    const discountPercentage = precioLista > 0 && precioBase > 0 && precioBase < precioLista
      ? Math.max(0, Math.round(Math.abs(((precioLista - precioBase) / precioLista) * 100)))
      : 0;

    return {
      ...plant,
      nombre: plant.name,
      programa: plant.programa,
      coverImage: plant.cover_image_url || plant.cover_image_media?.url || '',
      interiorImage: plant.interior_image_url || plant.interior_image_media?.url || '',
      precioBase,
      precioLista,
      discountPercentage,
      reservaExigidaPeso: Number(plant.proyecto?.valor_reserva_exigido_defecto_peso) || 0,
      proyectoNombre: plant.proyecto?.name,
      proyectoSlug: plant.proyecto?.slug || slugifySegment(plant.proyecto?.name),
      proyectoDescripcion: plant.proyecto?.descripcion,
      proyectoDireccion: plant.proyecto?.direccion,
      proyectoComuna: plant.proyecto?.comuna,
      proyectoEtapa: plant.proyecto?.etapa,
      asesores: Array.isArray(plant.proyecto?.asesores)
        ? plant.proyecto.asesores.map((asesor) => ({
          id: asesor.id,
          fullName: asesor.full_name,
          firstName: asesor.first_name,
          lastName: asesor.last_name,
          email: asesor.email,
          whatsapp: asesor.whatsapp_owner,
          avatarUrl: asesor.avatar_url,
        }))
        : [],
      isPaid: !!plant.is_paid,
      isAvailable: !!plant.is_available,
      isReserved: !!plant.active_reservation,
    };
  }, []);

  // Cargar proyectos para el filtro
  useEffect(() => {
    const fetchProyectos = async () => {
      try {
        const data = await proyectosService.getProyectos({
          perPage: 100,
          fields: 'id,salesforce_id,name,comuna,region',
        });
        setProyectos(data.data || []);
      } catch {
        return;
      }
    };
    fetchProyectos();
  }, []);

  useEffect(() => {
    const fetchLocationFilters = async () => {
      try {
        const data = await PlantsService.getLocationFilters();
        setRegionOptions(data.regions || []);
        setComunaOptions(data.comunas || []);
        setComunasByRegion(data.comunas_by_region || {});
      } catch {
        return;
      }
    };

    fetchLocationFilters();
  }, []);

  useEffect(() => {
    const fetchPisoOptions = async () => {
      try {
        const data = await PlantsService.getAll({ perPage: 500 });
        const pisos = [...new Set((data.data || []).map((plant) => `${plant?.piso ?? ''}`.trim()).filter((value) => value !== ''))]
          .sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base', numeric: true }));

        setPisoOptions(pisos);
      } catch {
        return;
      }
    };

    fetchPisoOptions();
  }, []);

  useEffect(() => {
    if (tempComuna && !filteredComunaOptions.includes(tempComuna)) {
      setTempComuna('');
    }
  }, [tempComuna, filteredComunaOptions]);

  const loadPlants = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      const filters = {
        page,
        perPage: 12,
        // available: true,
      };

      if (selectedProyecto.length > 0) {
        filters.salesforce_proyecto_id = selectedProyecto;
      }

      if (selectedDormitorios.length > 0) {
        filters.programa = selectedDormitorios;
      }

      if (selectedBanos.length > 0) {
        filters.programa2 = selectedBanos;
      }

      if (selectedPiso) {
        filters.piso = selectedPiso;
      }

      if (selectedComuna) {
        filters.comuna = selectedComuna;
      }

      if (selectedRegion) {
        filters.region = selectedRegion;
      }

      if (selectedPrecioMin) {
        filters.min_precio = selectedPrecioMin;
      }

      if (selectedPrecioMax) {
        filters.max_precio = selectedPrecioMax;
      }

      const data = await PlantsService.getAll(filters);

      const totalCount = data.total ?? data.data?.length ?? 0;

      const mappedPlants = (data.data || []).map((plant) => mapPlant(plant));

      setPlants(mappedPlants);
      setTotalPages(data.last_page || 1);
      setTotalPlants(totalCount);
    } catch (err) {
      const errorInfo = {
        type: err.type || 'unknown',
        message: err.message || 'Error al cargar las plantas',
        userMessage: err.userMessage || 'No se pudieron cargar las plantas. Por favor, intenta de nuevo.',
        title: 'Error al cargar plantas',
        canRetry: isRetryableError(err),
      };
      setError(errorInfo);
    } finally {
      setLoading(false);
    }
  }, [
    page,
    selectedProyecto,
    selectedDormitorios,
    selectedBanos,
    selectedPiso,
    selectedComuna,
    selectedRegion,
    selectedPrecioMin,
    selectedPrecioMax,
    mapPlant,
  ]);

  // Cargar plantas cuando cambian los filtros
  useEffect(() => {
    loadPlants();
  }, [loadPlants]);

  const buildPlantDetailPath = useCallback((plant) => {
    const projectSlug = plant?.proyectoSlug || slugifySegment(plant?.proyectoNombre || plant?.proyecto?.name);
    const unitName = `${plant?.nombre || plant?.name || ''}`.trim();

    if (!projectSlug || !unitName) {
      return '/';
    }

    return `${PLANT_DETAIL_BASE_PATH}/${encodeURIComponent(projectSlug)}/${encodeURIComponent(unitName)}`;
  }, []);

  const handleSelectPlantDetail = useCallback((plant) => {
    if (!plant) {
      return;
    }

    setSelectedPlantDetail(plant);

    const currentUrl = getCurrentBrowserUrl();
    const nextPath = buildPlantDetailPath(plant);

    if (currentUrl !== nextPath) {
      const previousUrl = parsePlantDetailPath(window.location.pathname)
        ? normalizeBrowserUrl(window.history.state?.previousUrl || '/')
        : currentUrl;

      window.history.pushState({ plantDetail: true, previousUrl }, '', nextPath);
    }
  }, [buildPlantDetailPath]);

  const handleClosePlantDetail = useCallback(() => {
    setSelectedPlantDetail(null);

    const parsedPath = parsePlantDetailPath(window.location.pathname);

    if (!parsedPath) {
      return;
    }

    if (window.history.state?.plantDetail && window.history.length > 1) {
      window.history.back();
      return;
    }

    const fallbackUrl = normalizeBrowserUrl(window.history.state?.previousUrl || '/');

    window.history.replaceState({}, '', fallbackUrl);
    setRoutePlantParams(parsePlantDetailPath(window.location.pathname));
  }, []);

  useEffect(() => {
    const handlePopState = () => {
      const parsed = parsePlantDetailPath(window.location.pathname);
      setRoutePlantParams(parsed);

      if (!parsed) {
        setRoutePlantLoading(false);
        setSelectedPlantDetail(null);
      }
    };

    window.addEventListener('popstate', handlePopState);

    return () => {
      window.removeEventListener('popstate', handlePopState);
    };
  }, []);

  useEffect(() => {
    let isMounted = true;

    const loadPlantFromRoute = async () => {
      if (!routePlantParams) {
        setRoutePlantLoading(false);
        return;
      }

      const plantInList = plants.find((plant) => (
        (plant.proyectoSlug || slugifySegment(plant.proyectoNombre)) === routePlantParams.projectSlug
        && `${plant.nombre}`.trim().toLowerCase() === routePlantParams.unitName.trim().toLowerCase()
      ));

      if (plantInList) {
        setRoutePlantLoading(false);
        setSelectedPlantDetail(plantInList);
        return;
      }

      setRoutePlantLoading(true);

      try {
        const plantFromApi = await PlantsService.getByProjectAndUnit(routePlantParams.projectSlug, routePlantParams.unitName);

        if (!isMounted) {
          return;
        }

        setSelectedPlantDetail(mapPlant(plantFromApi));
      } catch {
        if (!isMounted) {
          return;
        }

        setSelectedPlantDetail(null);
      } finally {
        if (isMounted) {
          setRoutePlantLoading(false);
        }
      }
    };

    loadPlantFromRoute();

    return () => {
      isMounted = false;
    };
  }, [routePlantParams, plants, mapPlant]);

  // Animaciones del Hero con GSAP
  useEffect(() => {
    if (configLoading || !heroRef.current) return;

    const ctx = gsap.context(() => {
      const tl = gsap.timeline();

      // Logo - flipInX (0ms)
      const logo = heroRef.current.querySelector('.hero-logo');
      if (logo) {
        tl.fromTo(logo, {
          y: -90,
          opacity: 0,
        }, {
          y: 0,
          opacity: 1,
          duration: 1.8,
          ease: 'back.out(1.7)',
        }, 0);
      }

      // Título y descripción - fadeInDown (500ms)
      tl.fromTo(['.hero-section h1', '.hero-section p'], {
        y: -50,
        opacity: 0,
      }, {
        y: 0,
        opacity: 1,
        duration: 0.8,
        ease: 'power2.out',
        stagger: 0.1
      }, 0.5);

      // Header plantas - fadeIn (700ms)
      tl.fromTo('.plants-header', {
        opacity: 0,
      }, {
        opacity: 1,
        duration: 1,
        ease: 'power1.out'
      }, 0.7);

      // Filtros - fadeIn (1000ms)
      tl.fromTo('.filters-details', {
        opacity: 0,
      }, {
        opacity: 1,
        duration: 1,
        ease: 'power1.out'
      }, 1);
    }, heroRef);

    return () => ctx.revert();
  }, [configLoading]);

  // Aplicar filtros
  const handleApplyFilters = () => {
    setSelectedProyecto(tempProyecto);
    setSelectedDormitorios(tempDormitorios);
    setSelectedBanos(tempBanos);
    setSelectedPiso(tempPiso);
    setSelectedComuna(tempComuna);
    setSelectedRegion(tempRegion);
    setSelectedPrecioMin(tempPrecioMin);
    setSelectedPrecioMax(tempPrecioMax);
    setPage(1); // Volver a la primera página al aplicar filtros
  };

  // Limpiar filtros
  const handleClearFilters = () => {
    setTempProyecto([]);
    setTempDormitorios([]);
    setTempBanos([]);
    setTempPiso('');
    setTempComuna('');
    setTempRegion('');
    setTempPrecioMin('');
    setTempPrecioMax('');
    setSelectedProyecto([]);
    setSelectedDormitorios([]);
    setSelectedBanos([]);
    setSelectedPiso('');
    setSelectedComuna('');
    setSelectedRegion('');
    setSelectedPrecioMin('');
    setSelectedPrecioMax('');
    setPage(1);
  };

  // Manejar compra directo desde la tarjeta
  const handleQuickCheckout = async (plant) => {
    try {
      setCheckoutLoading(true);
      setCheckoutError(null);
      setManualPayment(null);

      const availableGateways = await CheckoutService.getAvailableGateways(plant?.id);

      setGateways(availableGateways);
      setPlantForCheckout(plant);
      setGatewayDialogOpen(true);
    } catch (err) {
      setCheckoutError({
        type: err.type || 'gateway',
        message: err.message || 'Error al cargar pasarelas',
        userMessage: err.userMessage || 'No se pudieron cargar las pasarelas de pago para este proyecto.',
        title: 'Aviso',
      });
    } finally {
      setCheckoutLoading(false);
    }
  };

  // Confirmar checkout con pasarela seleccionada
  const handleConfirmCheckout = async ({ plantId, gateway, sessionToken, userData }) => {
    if (!isAuthenticated) {
      setCheckoutError({
        type: 'auth',
        message: 'Usuario no autenticado',
        userMessage: 'Debes iniciar sesion antes de pagar.',
        title: 'Inicio de sesion requerido',
      });
      return;
    }

    try {
      setCheckoutLoading(true);
      setCheckoutError(null);
      const response = await CheckoutService.initiate(plantId, 1, gateway, userData, sessionToken);

      const currentUser = authService.getCurrentUser();
      if (currentUser) {
        localStorage.setItem('user', JSON.stringify({
          ...currentUser,
          ...userData,
        }));
      }

      if (response.flow === 'manual') {
        setManualPayment(response);
        setCheckoutLoading(false);
        return;
      }

      // Cerrar diálogo antes de redirigir
      setGatewayDialogOpen(false);
      setManualPayment(null);

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

  const handleManualProofSubmission = async ({ paymentId, proofFile }) => {
    try {
      setManualProofLoading(true);
      const response = await CheckoutService.submitManualProof(paymentId, proofFile);

      setManualPayment((current) => (current ? {
        ...current,
        proofSubmitted: true,
      } : current));

      return response;
    } finally {
      setManualProofLoading(false);
    }
  };

  if (configLoading) {
    return (
      <div className="home-container">
        <div className="loading-skeletons wa-stack wa-gap-l">
          <wa-card appearance="filled">
            <div className="wa-stack wa-gap-s" style={{ padding: '1.5rem' }}>
              <wa-skeleton effect="pulse" style={{ height: '28px', width: '35%', margin: '0 auto' }}></wa-skeleton>
              <wa-skeleton effect="pulse" style={{ height: '18px', width: '60%', margin: '0 auto' }}></wa-skeleton>
            </div>
          </wa-card>

          <div className="wa-stack wa-gap-xs">
            <wa-skeleton effect="pulse" style={{ height: '26px', width: '220px' }}></wa-skeleton>
            <wa-skeleton effect="pulse" style={{ height: '16px', width: '320px' }}></wa-skeleton>
          </div>

          <wa-card appearance="outlined">
            <div className="wa-stack wa-gap-m" style={{ padding: '1rem' }}>
              <wa-skeleton effect="pulse" style={{ height: '18px', width: '140px' }}></wa-skeleton>
              <div className="wa-cluster wa-gap-s">
                <wa-skeleton effect="pulse" style={{ height: '42px', width: '220px' }}></wa-skeleton>
                <wa-skeleton effect="pulse" style={{ height: '42px', width: '160px' }}></wa-skeleton>
                <wa-skeleton effect="pulse" style={{ height: '42px', width: '140px' }}></wa-skeleton>
                <wa-skeleton effect="pulse" style={{ height: '42px', width: '150px' }}></wa-skeleton>
                <wa-skeleton effect="pulse" style={{ height: '42px', width: '150px' }}></wa-skeleton>
              </div>
              <div className="wa-cluster wa-gap-s">
                <wa-skeleton effect="pulse" style={{ height: '34px', width: '150px' }}></wa-skeleton>
                <wa-skeleton effect="pulse" style={{ height: '34px', width: '150px' }}></wa-skeleton>
              </div>
            </div>
          </wa-card>

          <div className="plants-grid wa-grid">
            {[...Array(6)].map((_, i) => (
              <wa-card key={i} className="skeleton-card" appearance="filled">
                <wa-skeleton slot="media" effect="pulse" style={{ height: '220px' }}></wa-skeleton>

                <div slot="header" className="wa-stack wa-gap-xs" style={{ width: '100%' }}>
                  <wa-skeleton effect="pulse" style={{ height: '18px', width: '65%' }}></wa-skeleton>
                  <wa-skeleton effect="pulse" style={{ height: '18px', width: '45%' }}></wa-skeleton>
                </div>

                <div slot="header-actions">
                  <wa-skeleton effect="pulse" style={{ height: '24px', width: '70px' }}></wa-skeleton>
                </div>

                <div className="wa-split wa-align-items-center">
                  <wa-skeleton effect="pulse" style={{ height: '16px', width: '35%' }}></wa-skeleton>
                  <div className="wa-cluster wa-gap-xs">
                    <wa-skeleton effect="pulse" style={{ height: '24px', width: '65px' }}></wa-skeleton>
                    <wa-skeleton effect="pulse" style={{ height: '24px', width: '65px' }}></wa-skeleton>
                  </div>
                </div>

                <div slot="footer" className="wa-stack wa-gap-xs">
                  <wa-skeleton effect="pulse" style={{ height: '14px', width: '48%' }}></wa-skeleton>
                  <wa-skeleton effect="pulse" style={{ height: '28px', width: '38%' }}></wa-skeleton>
                </div>

                <div slot="footer-actions">
                  <wa-button-group label="Skeleton actions">
                    <wa-button size="small" disabled>
                      <wa-skeleton effect="pulse" style={{ height: '14px', width: '72px' }}></wa-skeleton>
                    </wa-button>
                    <wa-button size="small" variant="brand" disabled>
                      <wa-skeleton effect="pulse" style={{ height: '14px', width: '56px' }}></wa-skeleton>
                    </wa-button>
                  </wa-button-group>
                </div>
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
    <>
    {/* Banner Promocional */}
    <BannerPromo banner={config?.banner} />

    {/* Hero Section */}
    <div className='video-home wa-position-relative wa-overflow-hidden wa-justify-content-center box-shadow-1'>
        <div className="hero-section wa-position-absolute wa-z-index-1">
            {config?.logo && (
            <img src={config.logo} alt={config?.site_name} className="hero-logo" />
            )}
            <h1>{config?.site_name}</h1>
            <p>{config?.site_description}</p>
        </div>
        <video autoPlay muted loop playsInline className="hero-video">
            <source src="https://viveelsur.ileben.cl/wp-content/uploads/2025/12/Banner-Hero-MobileV2.mp4" type="video/mp4" media="(max-width: 768px)" />
            <source src="https://viveelsur.ileben.cl/wp-content/uploads/2025/12/Banner-Hero-Desktop.mp4" type="video/mp4" media="(min-width: 769px)" />
            Tu navegador no soporta el video.
        </video>
    </div>
    <div className="home-container" ref={heroRef}>
        {/* Header de Plantas */}
        <div className="plants-header">
            <div className="wa-cluster wa-gap-s wa-align-items-center plants-header-main">
                <h2>Nuestras Plantas</h2>
                {activeFilterCount > 0 && (
                <wa-badge variant="brand" pill>
                    {activeFilterCount} {activeFilterCount === 1 ? 'filtro' : 'filtros'} activo{activeFilterCount === 1 ? '' : 's'}
                </wa-badge>
                )}
            </div>
            <p>Descubre nuestra colección disponible</p>
        </div>

        {/* Filtros */}
        <wa-details className="filters-details wa-mb-m">
            <span slot="summary">
                <wa-icon name="filter-circle-dollar"></wa-icon> Filtros
            </span>
                <wa-card
                appearance="filled"
                style={{ '--spacing': 'var(--wa-space-xs)', 'background-color': 'var(--wa-color-surface-lowered)' }}
                >
                    <div className="wa-grid wa-gap-m filters-inputs" style={{ '--min-column-size': '14rem' }}>
                        <wa-select
                            placeholder="Todos los proyectos"
                            size="small"
                            value={tempProyecto}
                            onChange={(e) => {
                            const value = getMultiSelectValue(e);
                            setTempProyecto(value);
                            }}
                            multiple
                            clearable
                        >
                            <span slot='label'><wa-icon name="building"></wa-icon> Proyecto</span>
                            {proyectos.map((proyecto) => (
                            <wa-option key={proyecto.id} value={proyecto.salesforce_id}>
                                <wa-icon name="building" slot="start"></wa-icon>{proyecto.name}
                            </wa-option>
                            ))}
                        </wa-select>

                        <wa-select
                            placeholder="Todos"
                            size="small"
                            value={tempDormitorios}
                            onChange={(e) => {
                            const value = getMultiSelectValue(e);
                            setTempDormitorios(value);
                            }}
                            with-clear
                            multiple
                            clearable
                        >
                            <span slot='label'><wa-icon name="bed"></wa-icon> Dormitorios</span>
                            <wa-option value="ST"><wa-icon name="bed" slot="start"></wa-icon>Studio</wa-option>
                            <wa-option value="1D"><wa-icon name="bed" slot="start"></wa-icon>1 Dormitorio</wa-option>
                            <wa-option value="2D"><wa-icon name="bed" slot="start"></wa-icon>2 Dormitorios</wa-option>
                            <wa-option value="3D"><wa-icon name="bed" slot="start"></wa-icon>3 Dormitorios</wa-option>
                            <wa-option value="4D"><wa-icon name="bed" slot="start"></wa-icon>4 Dormitorios</wa-option>
                        </wa-select>

                        <wa-select
                            placeholder="Todos"
                            size="small"
                            value={tempBanos}
                            onChange={(e) => {
                            const value = getMultiSelectValue(e);
                            setTempBanos(value);
                            }}
                            with-clear
                            multiple
                            clearable
                        >
                            <span slot='label'><wa-icon name="bath"></wa-icon> Baños</span>
                            <wa-option value="1B"><wa-icon name="bath" slot="start"></wa-icon>1 Baño</wa-option>
                            <wa-option value="2B"><wa-icon name="bath" slot="start"></wa-icon>2 Baños</wa-option>
                            <wa-option value="3B"><wa-icon name="bath" slot="start"></wa-icon>3 Baños</wa-option>
                        </wa-select>

                        <wa-select
                            with-clear
                            placeholder="Todos"
                            size="small"
                            value={tempPiso}
                            onChange={(e) => {
                            const value = getSingleSelectValue(e);
                            setTempPiso(value);
                            }}
                            multiple
                            clearable
                        >
                            <span slot='label'><wa-icon name="arrow-right-to-city"></wa-icon> Piso</span>
                            {pisoOptions.map((piso) => (
                            <wa-option key={piso} value={piso}>
                                <wa-icon name="arrow-right-to-city" slot="start"></wa-icon>Piso {piso}
                            </wa-option>
                            ))}
                        </wa-select>

                        <wa-select
                            placeholder="Todas"
                            size="small"
                            value={tempRegion}
                            onChange={(e) => {
                            const value = getSingleSelectValue(e);
                            setTempRegion(value);
                            setTempComuna('');
                            }}
                            clearable
                        >
                            <span slot='label'><wa-icon name="map"></wa-icon> Región</span>
                            {regionOptions.map((region) => (
                            <wa-option key={region} value={region}>
                                <wa-icon name="map" slot="start"></wa-icon>{region}
                            </wa-option>
                            ))}
                        </wa-select>

                        <wa-select
                            with-clear
                            size="small"
                            placeholder={tempRegion ? 'Todas' : 'Primero selecciona una región'}
                            value={tempComuna}
                            onChange={(e) => {
                            const value = getSingleSelectValue(e);
                            setTempComuna(value);
                            }}
                            clearable
                            disabled={!tempRegion}
                        >
                            <span slot='label'><wa-icon name="map-location"></wa-icon> Comuna</span>
                            {filteredComunaOptions.map((comuna) => (
                            <wa-option key={comuna} value={comuna}>
                                <wa-icon name="map-location" slot="start"></wa-icon>{comuna}
                            </wa-option>
                            ))}
                        </wa-select>

                        <wa-input
                            type="number"
                            placeholder="Desde UF"
                            value={tempPrecioMin}
                            max='9999'
                            size="small"
                            onChange={(e) => {
                                const value = e.target.value || '';
                                setTempPrecioMin(value);
                            }}
                        >
                            <span slot='label'><wa-icon name="dollar-sign"></wa-icon> Precio Mínimo</span>
                            <wa-icon slot="start" name="dollar-sign"></wa-icon>
                        </wa-input>

                        <wa-input
                            type="number"
                            placeholder="Hasta UF"
                            max='9999'
                            size="small"
                            value={tempPrecioMax}
                            onChange={(e) => {
                                const value = e.target.value || '';
                                setTempPrecioMax(value);
                            }}
                        >
                            <span slot='label'><wa-icon name="dollar-sign"></wa-icon> Precio Máximo</span>
                            <wa-icon slot="start" name="dollar-sign"></wa-icon>
                        </wa-input>
                    </div>

                    <div className="wa-cluster wa-gap-s filters-actions wa-mt-l">
                        <wa-button
                            variant="brand"
                            onClick={handleApplyFilters}
                        >
                            <wa-icon slot="start" name="filter"></wa-icon>
                            Aplicar Filtros
                        </wa-button>

                        {activeFilterCount > 0 && (
                            <wa-button
                            variant="neutral"
                            onClick={handleClearFilters}
                            >
                            <wa-icon slot="start" name="filter-circle-xmark"></wa-icon>
                            Limpiar Filtros
                            </wa-button>
                        )}
                    </div>
                </wa-card>
        </wa-details>

      {/* Plantas Grid */}
      <PlantsGrid
        plants={plants}
        loading={loading}
        checkoutLoading={checkoutLoading}
        onQuickCheckout={handleQuickCheckout}
        selectedPlant={selectedPlantDetail}
        onSelectPlant={handleSelectPlantDetail}
        onClosePlantDetail={handleClosePlantDetail}
        totalPlants={totalPlants}
        page={page}
        totalPages={totalPages}
        onPageChange={setPage}
      />

      {/* Diálogo - Selección de Pasarela de Pago */}
      <PaymentGatewayDialog
        open={gatewayDialogOpen}
        onClose={() => {
          setGatewayDialogOpen(false);
          setPlantForCheckout(null);
          setManualPayment(null);
        }}
        plant={plantForCheckout}
        gateways={gateways}
        loading={checkoutLoading}
        manualPayment={manualPayment}
        manualProofLoading={manualProofLoading}
        isAuthenticated={isAuthenticated}
        onConfirm={handleConfirmCheckout}
        onSubmitManualProof={handleManualProofSubmission}
      />

      {/* Notificación de errores de checkout */}
      <ErrorNotification
        error={checkoutError}
        onClose={() => setCheckoutError(null)}
        duration={6000}
      />
    </div>

    {routePlantLoading && (
      <wa-dialog open className="route-plant-loading-dialog">
        <span slot="label">
          <wa-icon name="spinner" animation="spin"></wa-icon> Cargando planta...
        </span>
        <div className="wa-stack wa-gap-s route-plant-loading-content">
          <span>Estamos obteniendo la información de la unidad seleccionada.</span>
        </div>
      </wa-dialog>
    )}

    <wa-button
      variant="neutral"
      appearance="filled"
      onClick={toggleColorMode}
      className="theme-floating-toggle box-shadow-2"
      id="theme-toggle-button"
      pill
    >
        <wa-icon name={colorMode === 'dark' ? 'sun' : 'cloud-moon'} label={colorMode === 'dark' ? 'Modo claro' : 'Modo oscuro'}></wa-icon>
    </wa-button>
    <wa-tooltip for="theme-toggle-button" placement="top">
      Cambiar a {colorMode === 'dark' ? 'modo claro' : 'modo oscuro'}
    </wa-tooltip>
    </>
  );
}

export default Home;
