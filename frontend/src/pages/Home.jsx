import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useSiteConfig } from '../contexts/SiteConfigContext';
import PlantsService from '../services/plants';
import { proyectosService } from '../services/proyectos';
import CheckoutService from '../services/checkout';
import { authService } from '../services/auth';
import ErrorNotification from '../components/ErrorNotification';
import PlantsGrid from '../components/PlantsGrid';
import PaymentGatewayDialog from '../components/PaymentGatewayDialog';
import SiteHeader from '../components/SiteHeader';
import SiteFooter from '../components/SiteFooter';
import { isRetryableError } from '../utils/errorHandler';
import { trackEvent, trackPageView } from '../utils/tagManager';
import gsap from 'gsap';
import '../styles/home.scss' with { type: 'css' };

const PLANT_DETAIL_BASE_PATH = '/p';
const FILTER_BASE_PATH = '/f';

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

const parseFilterPath = (pathname) => {
  const normalizedPath = `${pathname || '/'}`.replace(/\/+$/, '') || '/';

  if (normalizedPath !== FILTER_BASE_PATH && !normalizedPath.startsWith(`${FILTER_BASE_PATH}/`)) {
    return null;
  }

  const segments = normalizedPath.slice(FILTER_BASE_PATH.length).split('/').filter(Boolean);
  const filters = {
    projectSlugs: [],
    comunaSlugs: [],
  };

  for (let index = 0; index < segments.length; index += 2) {
    const key = decodeURIComponent(segments[index] || '');
    const rawValues = decodeURIComponent(segments[index + 1] || '');

    if (!key || !rawValues) {
      continue;
    }

    const values = rawValues
      .split(',')
      .map((value) => slugifySegment(value))
      .filter(Boolean);

    if (key === 'proyectos') {
      filters.projectSlugs = values;
    }

    if (key === 'comunas') {
      filters.comunaSlugs = values;
    }
  }

  return filters;
};

const parseLegacyCatalogPath = (pathname) => {
  if (parsePlantDetailPath(pathname)) {
    return null;
  }

  const matcher = new RegExp(`^${PLANT_DETAIL_BASE_PATH}/([^/]+)/?$`);
  const matches = pathname.match(matcher);

  if (!matches) {
    return null;
  }

  return {
    slug: decodeURIComponent(matches[1]),
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
function Home({ onNavigate, currentPath }) {
  const { config, loading: configLoading, colorMode, toggleColorMode } = useSiteConfig();
  const isSaleEventActive = Boolean(config?.evento_sale);
  const showPlants = Boolean(config?.mostrar_plantas ?? true);
  const routeFilters = useMemo(() => {
    const explicitFilters = parseFilterPath(currentPath || '/');

    if (explicitFilters) {
      return {
        ...explicitFilters,
        legacySlug: null,
      };
    }

    return {
      projectSlugs: [],
      comunaSlugs: [],
      legacySlug: parseLegacyCatalogPath(currentPath || '/')?.slug || null,
    };
  }, [currentPath]);
  const [plants, setPlants] = useState([]);
  const [proyectos, setProyectos] = useState([]);
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
  const [pisoOptions, setPisoOptions] = useState([]);
  const [orientacionOptions, setOrientacionOptions] = useState([]);
  const [entregaOptions, setEntregaOptions] = useState([]);
  const [regionOptions, setRegionOptions] = useState([]);
  const [comunaOptions, setComunaOptions] = useState([]);
  const [comunasByRegion, setComunasByRegion] = useState({});
  const [selectedProyecto, setSelectedProyecto] = useState([]);
  const [selectedDormitorios, setSelectedDormitorios] = useState([]);
  const [selectedBanos, setSelectedBanos] = useState([]);
  const [selectedPiso, setSelectedPiso] = useState('');
  const [selectedOrientacion, setSelectedOrientacion] = useState('');
  const [selectedTipoProducto, setSelectedTipoProducto] = useState('');
  const [selectedEntrega, setSelectedEntrega] = useState('');
  const [selectedComuna, setSelectedComuna] = useState([]);
  const [selectedRegion, setSelectedRegion] = useState('');
  const [selectedPrecioMin, setSelectedPrecioMin] = useState('');
  const [selectedPrecioMax, setSelectedPrecioMax] = useState('');

  // Estados temporales para filtros (antes de aplicar)
  const [tempProyecto, setTempProyecto] = useState([]);
  const [tempDormitorios, setTempDormitorios] = useState([]);
  const [tempBanos, setTempBanos] = useState([]);
  const [tempPiso, setTempPiso] = useState('');
  const [tempOrientacion, setTempOrientacion] = useState('');
  const [tempTipoProducto, setTempTipoProducto] = useState('');
  const [tempEntrega, setTempEntrega] = useState('');
  const [tempComuna, setTempComuna] = useState([]);
  const [tempRegion, setTempRegion] = useState('');
  const [tempPrecioMin, setTempPrecioMin] = useState('');
  const [tempPrecioMax, setTempPrecioMax] = useState('');

  const heroRef = useRef(null);
  const latestPlantsRequestRef = useRef(0);

  const handleMenuNavigation = useCallback(() => {
    const menuSection = document.getElementById('menu-section');

    if (!menuSection) {
      return;
    }

    menuSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, []);

  useEffect(() => {
    if (currentPath !== '/plantas' && !currentPath.startsWith('/p/') && currentPath !== '/f' && !currentPath.startsWith('/f/')) {
      return;
    }

    const frame = window.requestAnimationFrame(() => {
      handleMenuNavigation();
    });

    return () => window.cancelAnimationFrame(frame);
  }, [currentPath, handleMenuNavigation]);

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

  const filteredComunaOptions = useMemo(() => comunaOptions, [comunaOptions]);

  const routeProjectSlugs = useMemo(() => {
    if (routeFilters.projectSlugs.length > 0) {
      return routeFilters.projectSlugs;
    }

    if (!routeFilters.legacySlug) {
      return [];
    }

    const projectExists = proyectos.some((proyecto) => {
      const projectSlug = slugifySegment(proyecto.slug || proyecto.name || '');

      return projectSlug === routeFilters.legacySlug;
    });

    return projectExists ? [routeFilters.legacySlug] : [];
  }, [proyectos, routeFilters]);

  const routeProjectValues = useMemo(() => routeProjectSlugs
    .map((slug) => {
      const matchedProject = proyectos.find((proyecto) => slugifySegment(proyecto.slug || proyecto.name || '') === slug);

      return matchedProject?.salesforce_id ? `${matchedProject.salesforce_id}` : null;
    })
    .filter(Boolean), [proyectos, routeProjectSlugs]);

  const routeComunaSlugs = useMemo(() => {
    if (routeFilters.comunaSlugs.length > 0) {
      return routeFilters.comunaSlugs;
    }

    if (!routeFilters.legacySlug || routeProjectSlugs.length > 0) {
      return [];
    }

    return [routeFilters.legacySlug];
  }, [routeFilters, routeProjectSlugs]);

  const routeComunaValues = useMemo(() => routeComunaSlugs
    .map((slug) => filteredComunaOptions.find((comuna) => slugifySegment(comuna) === slug) || null)
    .filter(Boolean), [filteredComunaOptions, routeComunaSlugs]);

  const activeFilterCount = selectedProyecto.length
    + selectedDormitorios.length
    + selectedBanos.length
    + (selectedPiso ? 1 : 0)
    + (selectedOrientacion ? 1 : 0)
    + (selectedTipoProducto ? 1 : 0)
    + (selectedEntrega ? 1 : 0)
    + selectedComuna.length
    + (selectedRegion ? 1 : 0)
    + (selectedPrecioMin ? 1 : 0)
    + (selectedPrecioMax ? 1 : 0);

  const mapPlant = useCallback((plant) => {
    const precioBase = Number(plant.precio_base) || 0;
    const precioLista = Number(plant.precio_lista) || 0;
    const porcentajeMaximoUnidad = Number(plant.porcentaje_maximo_unidad) || 0;
    const precioCalculadoPorPorcentaje = porcentajeMaximoUnidad > 0 && precioLista > 0
      ? Math.max(0, precioLista - ((precioLista * porcentajeMaximoUnidad) / 100))
      : 0;
    const precioFinal = precioCalculadoPorPorcentaje > 0 ? precioCalculadoPorPorcentaje : precioBase;
    const discountPercentage = precioLista > 0 && precioFinal > 0 && precioFinal < precioLista
      ? Math.max(0, Math.round(Math.abs(((precioLista - precioFinal) / precioLista) * 100)))
      : 0;

    const legacyDiscountPercentage = precioLista > 0 && precioBase > 0 && precioBase < precioLista
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
      precioFinal,
      porcentajeMaximoUnidad,
      discountPercentage: discountPercentage || legacyDiscountPercentage,
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
      tipoProducto: `${plant.tipo_producto ?? ''}`.trim().toUpperCase(),
    };
  }, []);

  useEffect(() => {
    if (!showPlants) {
      return;
    }

    const fetchLocationFilters = async () => {
      try {
        const data = await PlantsService.getLocationFilters();
        setOrientacionOptions(data.orientaciones || []);
        setEntregaOptions(data.entregas || []);
        setRegionOptions(data.regions || []);
        setComunaOptions(data.comunas || []);
        setComunasByRegion(data.comunas_by_region || {});
      } catch {
        return;
      }
    };

    fetchLocationFilters();
  }, [showPlants]);

  useEffect(() => {
    if (!showPlants) {
      return;
    }

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
  }, [showPlants]);

  useEffect(() => {
    if (!showPlants) {
      return;
    }

    const fetchProjects = async () => {
      try {
        const data = await proyectosService.getProyectos({
          perPage: 100,
          fields: 'id,salesforce_id,name,slug,comuna',
        });

        setProyectos(data.data || []);
      } catch {
        setProyectos([]);
      }
    };

    fetchProjects();
  }, [showPlants]);

  useEffect(() => {
    const validComunas = tempComuna.filter((comuna) => filteredComunaOptions.includes(comuna));

    if (validComunas.length !== tempComuna.length) {
      setTempComuna(validComunas);
    }
  }, [tempComuna, filteredComunaOptions]);

  useEffect(() => {
    if (!routeFilters.legacySlug && routeProjectSlugs.length === 0 && routeComunaSlugs.length === 0) {
      return;
    }

    setTempProyecto(routeProjectValues);
    setSelectedProyecto(routeProjectValues);
    setTempComuna(routeComunaValues);
    setSelectedComuna(routeComunaValues);
    setPage(1);
  }, [routeComunaSlugs.length, routeComunaValues, routeFilters.legacySlug, routeProjectSlugs.length, routeProjectValues]);

  const loadPlants = useCallback(async () => {
    if (!showPlants) {
      setPlants([]);
      setTotalPages(1);
      setTotalPlants(0);
      setError(null);
      setLoading(false);

      return;
    }

    const requestId = ++latestPlantsRequestRef.current;

    try {
      setLoading(true);
      setError(null);

      const filters = {
        page,
        perPage: 12,
        // available: true,
      };

      if (routeFilters.legacySlug) {
        filters.catalog_slug = routeFilters.legacySlug;
      }

      if (routeProjectSlugs.length > 0) {
        filters.project_slug = routeProjectSlugs;
      } else if (selectedProyecto.length > 0) {
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

      if (selectedOrientacion) {
        filters.orientacion = selectedOrientacion;
      }

      if (selectedTipoProducto) {
        filters.tipo_producto = selectedTipoProducto;
      }

      if (selectedEntrega) {
        filters.entrega = selectedEntrega;
      }

      if (routeComunaSlugs.length > 0) {
        filters.comuna_slug = routeComunaSlugs;
      }

      if (routeComunaValues.length > 0 || selectedComuna.length > 0) {
        filters.comuna = routeComunaValues.length > 0 ? routeComunaValues : selectedComuna;
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

      if (isSaleEventActive) {
        filters.evento_sale = 1;
      }

      const data = await PlantsService.getAll(filters);

      if (requestId !== latestPlantsRequestRef.current) {
        return;
      }

      const totalCount = data.total ?? data.data?.length ?? 0;

      const mappedPlants = (data.data || []).map((plant) => mapPlant(plant));
      const visiblePlants = isSaleEventActive
        ? mappedPlants.filter((plant) => Number(plant.porcentajeMaximoUnidad) > 0)
        : mappedPlants;

      setPlants(visiblePlants);
      setTotalPages(data.last_page || 1);
      setTotalPlants(isSaleEventActive ? (data.total ?? visiblePlants.length) : totalCount);
    } catch (err) {
      if (requestId !== latestPlantsRequestRef.current) {
        return;
      }

      const errorInfo = {
        type: err.type || 'unknown',
        message: err.message || 'Error al cargar las plantas',
        userMessage: err.userMessage || 'No se pudieron cargar las plantas. Por favor, intenta de nuevo.',
        title: 'Error al cargar plantas',
        canRetry: isRetryableError(err),
      };
      setError(errorInfo);
    } finally {
      if (requestId === latestPlantsRequestRef.current) {
        setLoading(false);
      }
    }
  }, [
    page,
    routeComunaSlugs,
    routeComunaValues,
    routeFilters,
    routeProjectSlugs,
    selectedProyecto,
    selectedDormitorios,
    selectedBanos,
    selectedPiso,
    selectedOrientacion,
    selectedTipoProducto,
    selectedEntrega,
    selectedComuna,
    selectedRegion,
    selectedPrecioMin,
    selectedPrecioMax,
    isSaleEventActive,
    showPlants,
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

    if (plant.isPaid || plant.isReserved || plant.isAvailable === false) {
      setCheckoutError({
        userMessage: 'Esta planta ya no esta disponible para abrir su detalle porque fue reservada o pagada.',
      });

      return;
    }

    trackEvent('plant_click', {
      plant_id: plant.id,
      plant_name: plant.nombre || plant.name || null,
      project_name: plant.proyectoNombre || plant.proyecto?.name || null,
      product_type: plant.tipoProducto || null,
      price: plant.precioFinal || plant.precioBase || null,
    });

    const currentUrl = getCurrentBrowserUrl();
    const nextPath = buildPlantDetailPath(plant);

    const openPlantDetail = () => {
      setSelectedPlantDetail(plant);

      if (currentUrl !== nextPath) {
        const previousUrl = parsePlantDetailPath(window.location.pathname)
          ? normalizeBrowserUrl(window.history.state?.previousUrl || '/')
          : currentUrl;

        window.history.pushState({ plantDetail: true, previousUrl }, '', nextPath);

        trackPageView({
          path: nextPath,
          title: `${config?.site_name || 'iLeben'} | Planta ${plant.nombre || plant.name || ''}`,
        });
      }
    };

    if (selectedPlantDetail?.id === plant.id) {
      setSelectedPlantDetail(null);
      queueMicrotask(openPlantDetail);

      return;
    }

    openPlantDetail();
  }, [buildPlantDetailPath, config?.site_name, selectedPlantDetail]);

  const handleClosePlantDetail = useCallback(() => {
    setSelectedPlantDetail(null);
    setRoutePlantLoading(false);

    const targetUrl = parsePlantDetailPath(window.location.pathname)
      ? normalizeBrowserUrl(window.history.state?.previousUrl || '/plantas')
      : normalizeBrowserUrl(window.location.pathname || '/');

    window.history.replaceState({}, '', targetUrl);
    window.dispatchEvent(new PopStateEvent('popstate'));

    setRoutePlantParams(null);
  }, [currentPath]);

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
      if (!showPlants) {
        setRoutePlantLoading(false);
        setSelectedPlantDetail(null);

        return;
      }

      if (!routePlantParams) {
        setRoutePlantLoading(false);
        return;
      }

      if (loading) {
        setRoutePlantLoading(true);

        return;
      }

      const normalizedRouteUnitName = routePlantParams.unitName.trim().toLowerCase();
      const normalizedRouteUnitSlug = slugifySegment(routePlantParams.unitName);

      const plantInList = plants.find((plant) => (
        (plant.proyectoSlug || slugifySegment(plant.proyectoNombre)) === routePlantParams.projectSlug
        && (
          `${plant.nombre}`.trim().toLowerCase() === normalizedRouteUnitName
          || slugifySegment(plant.nombre) === normalizedRouteUnitSlug
        )
      ));

      if (plantInList) {
        try {
          const latestPlant = await PlantsService.getById(plantInList.id);

          if (!isMounted) {
            return;
          }

          const mappedLatestPlant = mapPlant(latestPlant);

          if (mappedLatestPlant.isPaid || mappedLatestPlant.isReserved || mappedLatestPlant.isAvailable === false) {
            setCheckoutError({
              userMessage: 'Esta planta ya no esta disponible para abrir su detalle porque fue reservada o pagada.',
            });
            setSelectedPlantDetail(null);
            setRoutePlantLoading(false);

            return;
          }

          setRoutePlantLoading(false);
          setSelectedPlantDetail(mappedLatestPlant);

          return;
        } catch {
          if (!isMounted) {
            return;
          }

          setRoutePlantLoading(false);
          setSelectedPlantDetail(plantInList);

          return;
        }

      }

      setRoutePlantLoading(true);

      try {
        const plantFromApi = await PlantsService.getByProjectAndUnit(routePlantParams.projectSlug, routePlantParams.unitName);

        if (!isMounted) {
          return;
        }

        const mappedPlant = mapPlant(plantFromApi);

        if (mappedPlant.isPaid || mappedPlant.isReserved || mappedPlant.isAvailable === false) {
          setCheckoutError({
            userMessage: 'Esta planta ya no esta disponible para abrir su detalle porque fue reservada o pagada.',
          });
          setSelectedPlantDetail(null);

          return;
        }

        setSelectedPlantDetail(mappedPlant);
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
  }, [routePlantParams, plants, loading, mapPlant, showPlants]);

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
      const heroTextTargets = heroRef.current.querySelectorAll('.hero-section h1, .hero-section p');

      if (heroTextTargets.length > 0) {
        tl.fromTo(heroTextTargets, {
          y: -50,
          opacity: 0,
        }, {
          y: 0,
          opacity: 1,
          duration: 0.8,
          ease: 'power2.out',
          stagger: 0.1,
        }, 0.5);
      }

      // Header plantas - fadeIn (700ms)
      const plantsHeader = heroRef.current.querySelector('.plants-header');

      if (plantsHeader) {
        tl.fromTo(plantsHeader, {
          opacity: 0,
        }, {
          opacity: 1,
          duration: 1,
          ease: 'power1.out',
        }, 0.7);
      }

      // Filtros - fadeIn (1000ms)
      const filtersDetails = heroRef.current.querySelector('.filters-details');

      if (filtersDetails) {
        tl.fromTo(filtersDetails, {
          opacity: 0,
        }, {
          opacity: 1,
          duration: 1,
          ease: 'power1.out',
        }, 1);
      }
    }, heroRef);

    return () => ctx.revert();
  }, [configLoading]);

  const syncCatalogUrl = useCallback((projectValues, comunaValues) => {
    const projectSlugs = (Array.isArray(projectValues) ? projectValues : [projectValues])
      .map((value) => proyectos.find((proyecto) => `${proyecto.salesforce_id}` === `${value}`))
      .map((proyecto) => slugifySegment(proyecto?.slug || proyecto?.name || ''))
      .filter(Boolean);

    const comunaSlugs = (Array.isArray(comunaValues) ? comunaValues : [comunaValues])
      .map((value) => slugifySegment(value))
      .filter(Boolean);

    const segments = [];

    if (projectSlugs.length > 0) {
      segments.push('proyectos', projectSlugs.join(','));
    }

    if (comunaSlugs.length > 0) {
      segments.push('comunas', comunaSlugs.join(','));
    }

    if (segments.length === 0) {
      if (currentPath.startsWith('/p/') || currentPath === '/f' || currentPath.startsWith('/f/')) {
        onNavigate?.('/');
      }

      return;
    }

    onNavigate?.(`${FILTER_BASE_PATH}/${segments.join('/')}`);
  }, [currentPath, onNavigate, proyectos]);

  // Aplicar filtros
  const handleApplyFilters = () => {
    setSelectedProyecto(tempProyecto);
    setSelectedDormitorios(tempDormitorios);
    setSelectedBanos(tempBanos);
    setSelectedPiso(tempPiso);
    setSelectedOrientacion(tempOrientacion);
    setSelectedTipoProducto(tempTipoProducto);
    setSelectedEntrega(tempEntrega);
    setSelectedComuna(tempComuna);
    setSelectedRegion(tempRegion);
    setSelectedPrecioMin(tempPrecioMin);
    setSelectedPrecioMax(tempPrecioMax);
    setPage(1); // Volver a la primera página al aplicar filtros
    syncCatalogUrl(tempProyecto, tempComuna);
  };

  // Limpiar filtros
  const handleClearFilters = () => {
    setTempProyecto([]);
    setTempDormitorios([]);
    setTempBanos([]);
    setTempPiso('');
    setTempOrientacion('');
    setTempTipoProducto('');
    setTempEntrega('');
    setTempComuna([]);
    setTempRegion('');
    setTempPrecioMin('');
    setTempPrecioMax('');
    setSelectedProyecto([]);
    setSelectedDormitorios([]);
    setSelectedBanos([]);
    setSelectedPiso('');
    setSelectedOrientacion('');
    setSelectedTipoProducto('');
    setSelectedEntrega('');
    setSelectedComuna([]);
    setSelectedRegion('');
    setSelectedPrecioMin('');
    setSelectedPrecioMax('');
    setPage(1);

    if (currentPath.startsWith('/p/') || currentPath === '/f' || currentPath.startsWith('/f/')) {
      onNavigate?.('/');
    }
  };

  // Manejar compra directo desde la tarjeta
  const handleQuickCheckout = async (plant) => {
    try {
      trackEvent('reserve_click', {
        plant_id: plant?.id || null,
        plant_name: plant?.nombre || plant?.name || null,
        project_name: plant?.proyectoNombre || plant?.proyecto?.name || null,
        product_type: plant?.tipoProducto || null,
      });

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
  const handleConfirmCheckout = async ({ plantId, gateway, sessionToken, turnstileToken, userData }) => {
    if (!isAuthenticated) {
      trackEvent('checkout_error', {
        plant_id: plantId,
        gateway,
        reason: 'unauthenticated_user',
      });

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
      const response = await CheckoutService.initiate(
        plantId,
        1,
        gateway,
        userData,
        sessionToken,
        turnstileToken,
      );

      const currentUser = authService.getCurrentUser();
      if (currentUser) {
        localStorage.setItem('user', JSON.stringify({
          ...currentUser,
          ...userData,
        }));
      }

      trackEvent('checkout_start', {
        plant_id: plantId,
        gateway,
        flow: response.flow === 'manual' ? 'manual' : 'redirect',
      });

      if (response.flow === 'manual') {
        setManualPayment(response);
        setCheckoutLoading(false);
        return;
      }

      // En flujo redireccionado no cerramos el dialogo manualmente.
      // Si se cierra dispara release() de la reserva justo antes de salir a Transbank.
      CheckoutService.redirect(response);
    } catch (err) {
      trackEvent('checkout_error', {
        plant_id: plantId,
        gateway,
        reason: err?.type || 'unknown',
        error_message: err?.userMessage || err?.message || 'Error al iniciar checkout',
      });

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
      <>
        <SiteHeader
          config={config}
          currentPath={currentPath}
          onNavigate={onNavigate}
          onMenuClick={handleMenuNavigation}
        />
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
      </>
    );
  }

  if (error) {
    return (
      <>
        <SiteHeader
          config={config}
          currentPath={currentPath}
          onNavigate={onNavigate}
          onMenuClick={handleMenuNavigation}
        />
      <div className="home-container">
        <wa-card>
            <div slot="header">
                <h2>{error.title || 'Error'}</h2>
            </div>
            <wa-callout variant="danger">
                <wa-icon slot="icon" name="circle-exclamation"></wa-icon>
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
      </>
    );
  }

  const homeHero = config?.hero?.home || {};
  const homeHeroDesktopImage = homeHero?.image_desktop || homeHero?.image || null;
  const homeHeroMobileImage = homeHero?.image_mobile || homeHeroDesktopImage;
  const homeHeroType = homeHero?.type === 'image' && homeHeroDesktopImage ? 'image' : 'video';
  const homeHeroDesktopVideo = homeHero?.video_desktop_url || 'https://viveelsur.ileben.cl/wp-content/uploads/2025/12/Banner-Hero-Desktop.mp4';
  const homeHeroMobileVideo = homeHero?.video_mobile_url || 'https://viveelsur.ileben.cl/wp-content/uploads/2025/12/Banner-Hero-MobileV2.mp4';

  return (
    <>
    <SiteHeader
      config={config}
      currentPath={currentPath}
      onNavigate={onNavigate}
      onMenuClick={handleMenuNavigation}
    />

    {/* Hero Section */}
    <div className='video-home wa-position-relative wa-overflow-hidden wa-justify-content-center box-shadow-1'>
        {/* <div className="hero-section wa-position-absolute wa-z-index-1">
            <h1>{config?.site_name}</h1>
            <p>{config?.site_description}</p>
        </div> */}
        {homeHeroType === 'image' ? (
          <picture>
            <source media="(max-width: 768px)" srcSet={homeHeroMobileImage || homeHeroDesktopImage} />
            <img src={homeHeroDesktopImage} alt={config?.site_name || 'Hero'} className="hero-video" />
          </picture>
        ) : (
          <video autoPlay muted loop playsInline className="hero-video">
              <source src={homeHeroMobileVideo} type="video/mp4" media="(max-width: 768px)" />
              <source src={homeHeroDesktopVideo} type="video/mp4" media="(min-width: 769px)" />
              Tu navegador no soporta el video.
          </video>
        )}
    </div>
    <div className="home-container" ref={heroRef} id="menu-section">
        {/* Header de Plantas */}
        <div className="plants-header">
            <div className="wa-cluster wa-gap-s wa-align-items-center plants-header-main">
                <h2>{config?.site_name}</h2>
                {activeFilterCount > 0 && (
                <wa-badge variant="brand" pill>
                    {activeFilterCount} {activeFilterCount === 1 ? 'filtro' : 'filtros'} activo{activeFilterCount === 1 ? '' : 's'}
                </wa-badge>
                )}
            </div>
            <p>{config?.site_description}</p>
        </div>

        {/* Filtros */}
        {showPlants ? (
        <>
        <wa-details className="filters-details wa-mb-m">
            <span slot="summary">
                <wa-icon name="filter-circle-dollar"></wa-icon> Filtros
            </span>
                <wa-card
                appearance="filled"
                style={{ '--spacing': 'var(--wa-space-xs)', backgroundColor: 'var(--wa-color-surface-lowered)' }}
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

                        {/* <wa-select
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
                        </wa-select> */}

                        {/* <wa-select
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
                        </wa-select> */}

                        <wa-select
                            with-clear
                            placeholder="Todos"
                            size="small"
                            value={tempTipoProducto}
                            onChange={(e) => {
                            const value = getSingleSelectValue(e);
                            setTempTipoProducto(value);
                            }}
                            clearable
                          >
                            <span slot='label'><wa-icon name="city"></wa-icon> Tipo de planta</span>
                            <wa-option value="DEPARTAMENTO"><wa-icon name="building" slot="start"></wa-icon>Departamento</wa-option>
                            <wa-option value="ESTACIONAMIENTO"><wa-icon name="square-parking" slot="start"></wa-icon>Estacionamiento</wa-option>
                            <wa-option value="BODEGA"><wa-icon name="box-archive" slot="start"></wa-icon>Bodega</wa-option>
                            <wa-option value="LOCAL"><wa-icon name="store" slot="start"></wa-icon>Local</wa-option>
                        </wa-select>

                          <wa-select
                            with-clear
                            placeholder="Todas"
                            size="small"
                            value={tempOrientacion}
                            onChange={(e) => {
                            const value = getSingleSelectValue(e);
                            setTempOrientacion(value);
                            }}
                            clearable
                          >
                            <span slot='label'><wa-icon name="compass"></wa-icon> Orientación</span>
                            {orientacionOptions.map((orientacion) => (
                            <wa-option key={orientacion} value={orientacion}>
                              <wa-icon name="compass" slot="start"></wa-icon>{orientacion}
                            </wa-option>
                            ))}
                          </wa-select>

                          <wa-select
                            with-clear
                            placeholder="Todas"
                            size="small"
                            value={tempEntrega}
                            onChange={(e) => {
                            const value = getSingleSelectValue(e);
                            setTempEntrega(value);
                            }}
                            clearable
                          >
                            <span slot='label'><wa-icon name="key"></wa-icon> Entrega</span>
                            {entregaOptions.map((entrega) => (
                            <wa-option key={entrega} value={entrega}>
                              <wa-icon name="key" slot="start"></wa-icon>{entrega}
                            </wa-option>
                            ))}
                          </wa-select>

                        <wa-select
                            with-clear
                            size="small"
                            placeholder="Todas"
                            value={tempComuna}
                            onChange={(e) => {
                            const value = getMultiSelectValue(e);
                            setTempComuna(value);
                            }}
                            multiple
                            clearable
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
        isSaleEventActive={isSaleEventActive}
        saleLogoUrl={config?.logo_sale || null}
        loading={loading}
        checkoutLoading={checkoutLoading}
        onQuickCheckout={handleQuickCheckout}
        onDetailBlocked={(message) => {
          setCheckoutError({
            type: 'validation',
            title: 'Planta no disponible',
            userMessage: message,
          });
        }}
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
        checkoutError={checkoutError}
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
        </>
        ) : (
        <wa-card className="wa-mb-l">
          <wa-callout variant="brand">
            <wa-icon slot="icon" name="clock"></wa-icon>
            <strong>Próximamente</strong>
            <div style={{ marginTop: '8px' }}>
              El catálogo de plantas no está disponible por el momento.
            </div>
          </wa-callout>
        </wa-card>
        )}
    </div>

    <SiteFooter config={config} onNavigate={onNavigate} />

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
