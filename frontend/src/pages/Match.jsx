import { Suspense, lazy, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { gsap } from 'gsap';
import { useSiteConfig } from '../contexts/SiteConfigContext';
import SiteHeader from '../components/SiteHeader';
import SiteFooter from '../components/SiteFooter';
import PlantsService from '../services/plants';
import { proyectosService } from '../services/proyectos';
import CheckoutService from '../services/checkout';
import { authService } from '../services/auth';
import ErrorNotification from '../components/ErrorNotification';
import { getConfiguredEntregaAliases, getProjectSlugsByAlias, getStageKeysByAlias } from '../utils/stageAlias';
import '../styles/match.scss' with { type: 'css' };
import '../styles/home.scss' with { type: 'css' };

const PlantDetailDialog = lazy(() => import('../components/PlantDetailDialog'));
const PaymentGatewayDialog = lazy(() => import('../components/PaymentGatewayDialog'));

const BEDROOM_OPTIONS = [
  { value: 'ST', label: 'Estudio' },
  { value: '1D', label: '1 dormitorio' },
  { value: '2D', label: '2 dormitorios' },
  { value: '3D', label: '3 dormitorios' },
  { value: '4D', label: '4 o mas dormitorios' },
];

const INITIAL_ANSWERS = {
  rango: '',
  comuna: '',
  dormitorios: '',
  orientacion: '',
  entrega: '',
};

const normalizeText = (value) => `${value ?? ''}`
  .trim()
  .toLowerCase()
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '');

const slugifySegment = (value) => normalizeText(value)
  .replace(/[^\w\s-]/g, '')
  .replace(/\s+/g, '-')
  .replace(/-+/g, '-');

const formatPrice = (amount) => {
  const value = Number(amount || 0);

  if (!Number.isFinite(value) || value <= 0) {
    return 'Precio por confirmar';
  }

  return `UF ${value.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
};

const buildPlantDetailPath = (plant) => {
  const projectSlug = plant?.proyectoSlug || slugifySegment(plant?.proyectoNombre || plant?.proyecto?.name);
  const unitName = `${plant?.nombre || plant?.name || ''}`.trim();

  if (!projectSlug || !unitName) {
    return '/plantas';
  }

  return `/p/${encodeURIComponent(projectSlug)}/${encodeURIComponent(unitName)}`;
};

const matchesBedroomPreference = (programa, selectedBedroom) => {
  const normalizedBedroom = `${selectedBedroom ?? ''}`.trim().toUpperCase();
  const normalizedPrograma = `${programa ?? ''}`.replace(/\s+/g, '').toUpperCase();

  if (!normalizedBedroom || !normalizedPrograma) {
    return false;
  }

  if (normalizedBedroom === 'ST') {
    return normalizedPrograma.startsWith('ST');
  }

  const bedroomCount = normalizedBedroom.replace(/\D+/g, '');

  if (bedroomCount === '') {
    return false;
  }

  if (normalizedBedroom === '4D') {
    const matchedCount = Number(normalizedPrograma.match(/(\d+)D/)?.[1] || 0);

    return matchedCount >= 4;
  }

  return normalizedPrograma.includes(`${bedroomCount}D`);
};

const mapPlant = (plant, isSaleEventActive, priceSource) => {
  const precioBase = Number(plant.precio_base) || 0;
  const precioLista = Number(plant.precio_lista) || 0;
  const porcentajeMaximoUnidad = Number(plant.porcentaje_maximo_unidad) || 0;
  const descuentoDefectoCotizacionWeb = Number(plant.proyecto?.descuento_defecto_cotizacion_web) || 0;
  const porcentajeAplicado = isSaleEventActive ? porcentajeMaximoUnidad : descuentoDefectoCotizacionWeb;
  const precioCalculadoPorPorcentaje = porcentajeAplicado > 0 && precioLista > 0
    ? Math.max(0, precioLista - ((precioLista * porcentajeAplicado) / 100))
    : 0;
  const precioFinalApi = Number(plant.precio_final) || 0;
  const precioFinal = precioFinalApi > 0
    ? precioFinalApi
    : (precioCalculadoPorPorcentaje > 0 ? precioCalculadoPorPorcentaje : precioBase);
  const precioSeleccionado = priceSource === 'base'
    ? precioBase
    : (precioFinal > 0 ? precioFinal : precioBase);
  const discountPercentage = precioLista > 0 && precioSeleccionado > 0 && precioSeleccionado < precioLista
    ? Math.max(0, Math.round(Math.abs(((precioLista - precioSeleccionado) / precioLista) * 100)))
    : 0;

  return {
    ...plant,
    nombre: plant.name,
    frontImage: plant.imageUrl || plant.cover_image_url || plant.cover_image_media?.url || '',
    coverImage: plant.cover_image_url || plant.cover_image_media?.url || '',
    interiorImage: plant.interior_image_url || plant.interior_image_media?.url || '',
    detailImageUrl: plant.detailImageUrl || plant.interior_image_url || plant.cover_image_url || '',
    precioBase,
    precioLista,
    precioFinal,
    precioSeleccionado,
    porcentajeMaximoUnidad,
    discountPercentage,
    proyectoNombre: plant.proyecto?.name,
    proyectoSlug: plant.proyecto?.slug || slugifySegment(plant.proyecto?.name),
    proyectoComuna: plant.proyecto?.comuna,
    isPaid: Boolean(plant.is_paid),
    isAvailable: Boolean(plant.is_available),
    isReserved: Boolean(plant.active_reservation),
  };
};

const buildRecommendationScore = (plant, answers, recommendedProjectSlugs) => {
  let score = 0;

  if (recommendedProjectSlugs.length > 0 && recommendedProjectSlugs.includes(plant.proyectoSlug)) {
    score += 8;
  }

  if (answers.comuna && normalizeText(plant.proyectoComuna) === normalizeText(answers.comuna)) {
    score += 4;
  }

  if (answers.dormitorios && matchesBedroomPreference(plant.programa, answers.dormitorios)) {
    score += 3;
  }

  if (answers.orientacion && normalizeText(plant.orientacion) === normalizeText(answers.orientacion)) {
    score += 2;
  }

  if (plant.detailImageUrl) {
    score += 1;
  }

  if (plant.isAvailable && !plant.isPaid && !plant.isReserved) {
    score += 1;
  }

  return score;
};

function MatchSwipeDeck({ currentPlant, nextPlant, onLike, onDislike, onCardClick, onViewMatches }) {
  const dragStartRef = useRef({ x: 0, y: 0 });
  const pointerTravelRef = useRef(0);
  const frontCardRef = useRef(null);
  const backCardRef = useRef(null);
  const deckActionsRef = useRef(null);
  const isAnimatingRef = useRef(false);
  const isDraggingRef = useRef(false);
  const promoteBackCardRef = useRef(false);
  const [dragState, setDragState] = useState({ x: 0, y: 0, dragging: false });
  const threshold = 60;
  const backBaseRotate = useMemo(() => (currentPlant?.id ?? 0) % 2 === 0 ? -6 : 6, [currentPlant?.id]);

  useEffect(() => {
    setDragState({ x: 0, y: 0, dragging: false });
  }, [currentPlant?.id]);

  useEffect(() => {
    if (!currentPlant || !frontCardRef.current) {
      return;
    }

    isAnimatingRef.current = false;
    isDraggingRef.current = false;

    gsap.killTweensOf([frontCardRef.current, backCardRef.current]);

    const context = gsap.context(() => {
      const shouldPromoteBackCard = promoteBackCardRef.current;

      promoteBackCardRef.current = false;

      gsap.set(frontCardRef.current, {
        x: 0,
        y: 0,
        rotate: 0,
        autoAlpha: 1,
        scale: 1,
        zIndex: 3,
      });

      if (backCardRef.current) {
        gsap.set(backCardRef.current, {
          x: 0,
          y: 16,
          scale: 0.97,
          rotate: backBaseRotate,
          autoAlpha: 0.84,
          zIndex: 2,
        });
      }

      if (shouldPromoteBackCard) {
        gsap.fromTo(frontCardRef.current, {
          autoAlpha: 0.9,
          scale: 0.985,
          y: 0,
          x: 0,
          rotate: 0,
        }, {
          autoAlpha: 1,
          scale: 1,
          y: 0,
          x: 0,
          rotate: 0,
          duration: 0.2,
          ease: 'power2.out',
        });
      } else {
        gsap.fromTo(frontCardRef.current, {
          x: 0,
          autoAlpha: 0,
          y: 40,
          scale: 0.95,
          rotate: 0,
        }, {
          x: 0,
          autoAlpha: 1,
          y: 0,
          scale: 1,
          rotate: 0,
          duration: 0.36,
          ease: 'power3.out',
        });

        gsap.fromTo(
          frontCardRef.current.querySelectorAll('.match-card__body > *'),
          { autoAlpha: 0, y: 16 },
          { autoAlpha: 1, y: 0, duration: 0.35, stagger: 0.06, ease: 'power2.out', delay: 0.1 }
        );
      }

      if (deckActionsRef.current) {
        gsap.fromTo(
          deckActionsRef.current.querySelectorAll('wa-button'),
          { autoAlpha: 0, y: 12, scale: 0.96 },
          { autoAlpha: 1, y: 0, scale: 1, duration: 0.28, stagger: 0.04, ease: 'power2.out', delay: 0.16 }
        );
      }
    });

    return () => {
      context.revert();
    };
  }, [currentPlant]);

  const commitDecision = useCallback((direction, fromButton = false) => {
    if (!currentPlant || isAnimatingRef.current || !frontCardRef.current) {
      return;
    }

    isAnimatingRef.current = true;
    promoteBackCardRef.current = Boolean(nextPlant);
    const directionMultiplier = direction === 'right' ? 1 : -1;
    const offscreenX = directionMultiplier * (window.innerWidth * 0.9);

    if (fromButton) {
      const seededX = directionMultiplier * 46;

      gsap.set(frontCardRef.current, {
        x: seededX,
        rotate: seededX / 8,
      });

      setDragState({ x: seededX, y: 0, dragging: false });
    }

    if (backCardRef.current) {
      gsap.to(backCardRef.current, {
        y: 0,
        scale: 1,
        rotate: 0,
        autoAlpha: 0.9,
        duration: 0.22,
        ease: 'power2.out',
      });
    }

    gsap.to(frontCardRef.current, {
      x: offscreenX,
      y: directionMultiplier * 16,
      rotation: directionMultiplier * 22,
      autoAlpha: 0,
      duration: 0.3,
      ease: 'power2.inOut',
      onComplete: () => {
        setDragState({ x: 0, y: 0, dragging: false });
        isAnimatingRef.current = false;
        isDraggingRef.current = false;

        if (direction === 'right') {
          onLike(currentPlant);
          return;
        }

        onDislike(currentPlant);
      },
    });
  }, [currentPlant, nextPlant, onDislike, onLike]);

  const handlePointerDown = (event) => {
    if (!currentPlant || isAnimatingRef.current) {
      return;
    }

    isDraggingRef.current = true;
    pointerTravelRef.current = 0;
    dragStartRef.current = { x: event.clientX, y: event.clientY };
    setDragState({ x: 0, y: 0, dragging: true });
    event.currentTarget.setPointerCapture?.(event.pointerId);
  };

  const handlePointerMove = (event) => {
    if (!isDraggingRef.current || isAnimatingRef.current || !frontCardRef.current) {
      return;
    }

    const x = event.clientX - dragStartRef.current.x;
    const y = event.clientY - dragStartRef.current.y;
    pointerTravelRef.current = Math.max(pointerTravelRef.current, Math.abs(x));
    const rotate = gsap.utils.clamp(-18, 18, x / 8);
    const opacity = gsap.utils.clamp(0.35, 1, 1 - (Math.abs(x) / 240));
    const backScale = gsap.utils.clamp(0.97, 1, 0.97 + (Math.abs(x) / 900));
    const backY = gsap.utils.clamp(0, 16, 16 - (Math.abs(x) / 14));
    const backRotate = backBaseRotate * (1 - Math.min(Math.abs(x) / 240, 1));

    gsap.set(frontCardRef.current, {
      x,
      y: y * 0.18,
      rotate,
      autoAlpha: opacity,
    });

    if (backCardRef.current) {
      gsap.set(backCardRef.current, {
        scale: backScale,
        y: backY,
        rotate: backRotate,
      });
    }

    setDragState({ x, y, dragging: true });
  };

  const handlePointerUp = useCallback(() => {
    if (!isDraggingRef.current || isAnimatingRef.current || !frontCardRef.current) {
      return;
    }

    isDraggingRef.current = false;

    const wasTap = pointerTravelRef.current < 8;

    if (wasTap) {
      setDragState({ x: 0, y: 0, dragging: false });
      onCardClick?.(currentPlant);
      return;
    }

    if (Math.abs(dragState.x) >= threshold) {
      commitDecision(dragState.x > 0 ? 'right' : 'left');
      return;
    }

    gsap.to(frontCardRef.current, {
      x: 0,
      y: 0,
      rotate: 0,
      autoAlpha: 1,
      duration: 0.22,
      ease: 'power3.out',
    });

    if (backCardRef.current) {
      gsap.to(backCardRef.current, {
        y: 16,
        scale: 0.97,
        rotate: backBaseRotate,
        duration: 0.22,
        ease: 'power3.out',
      });
    }

    setDragState({ x: 0, y: 0, dragging: false });
  }, [backBaseRotate, commitDecision, currentPlant, dragState.x, onCardClick]);

  const likeOpacity = dragState.x > 0 ? Math.min(Math.abs(dragState.x) / threshold, 1) : 0;
  const dislikeOpacity = dragState.x < 0 ? Math.min(Math.abs(dragState.x) / threshold, 1) : 0;

  if (!currentPlant) {
    return null;
  }

  return (
    <div className="match-deck-shell wa-stack wa-gap-l">
      <div className="match-deck">
        {nextPlant && (
          <article
            key={`back-${nextPlant.id}`}
            className="match-card match-card--back"
            aria-hidden="true"
            ref={backCardRef}
          >
            <div className="match-card__media">
              <img src={nextPlant.frontImage || nextPlant.coverImage || nextPlant.detailImageUrl} alt="" />
            </div>
          </article>
        )}

        <article
          key={`front-${currentPlant.id}`}
          className="match-card match-card--front"
          ref={frontCardRef}
          onPointerDown={handlePointerDown}
          onPointerMove={handlePointerMove}
          onPointerUp={handlePointerUp}
          onPointerCancel={handlePointerUp}
        >
          <div className="match-card__choice match-card__choice--like" style={{ opacity: likeOpacity }}>
            Me gusta
          </div>
          <div className="match-card__choice match-card__choice--dislike" style={{ opacity: dislikeOpacity }}>
            Paso
          </div>

          <div className="match-card__media">
            <img
              src={currentPlant.frontImage || currentPlant.coverImage || currentPlant.detailImageUrl}
              alt={`Lamina de ${currentPlant.nombre || 'planta'}`}
            />
          </div>

          <div className="match-card__body wa-stack wa-gap-s">
            <div className="wa-split wa-gap-s wa-align-items-start">
              <div className="wa-stack wa-gap-3xs">
                <span className="match-card__eyebrow">{currentPlant.proyectoNombre || 'Proyecto'}</span>
                <h2 className="match-card__title">Planta {currentPlant.nombre}</h2>
              </div>
              <wa-badge variant="brand" appearance="filled">{formatPrice(currentPlant.precioSeleccionado || currentPlant.precioFinal || currentPlant.precioBase)}</wa-badge>
            </div>

            <div className="match-card__meta wa-cluster wa-gap-xs">
              {currentPlant.proyectoComuna && <wa-badge variant="neutral">{currentPlant.proyectoComuna}</wa-badge>}
              {currentPlant.programa && <wa-badge variant="neutral">{currentPlant.programa}</wa-badge>}
              {currentPlant.orientacion && <wa-badge variant="neutral">Orient. {currentPlant.orientacion}</wa-badge>}
              {currentPlant.discountPercentage > 0 && <wa-badge variant="success">-{currentPlant.discountPercentage}%</wa-badge>}
            </div>
          </div>
        </article>
      </div>

      <div className="match-deck-actions wa-cluster wa-gap-s wa-justify-content-center" ref={deckActionsRef}>
        <wa-button appearance="outlined" size="large" pill onClick={() => commitDecision('left', true)}>
          <wa-icon name="xmark" slot="start"></wa-icon>
          No me gusta
        </wa-button>
        <wa-button variant="brand" size="large" pill onClick={() => commitDecision('right', true)}>
          <wa-icon name="heart" slot="start"></wa-icon>
          Me gusta
        </wa-button>
      </div>

      <div className="wa-cluster wa-justify-content-center">
        <wa-button appearance="outlined" size="medium" pill onClick={onViewMatches}>
          <wa-icon name="list-check" slot="start"></wa-icon>
          Ver matchs
        </wa-button>
      </div>
    </div>
  );
}

function Match({ onNavigate, currentPath }) {
  const { config } = useSiteConfig();
  const isSaleEventActive = Boolean(config?.evento_sale);
  const priceSource = config?.payment_gateways?.price_source === 'base' ? 'base' : 'final';

  const [answers, setAnswers] = useState(INITIAL_ANSWERS);
  const [projectCatalog, setProjectCatalog] = useState([]);
  const [orientationOptions, setOrientationOptions] = useState([]);
  const [entregaOptions, setEntregaOptions] = useState([]);
  const [stage, setStage] = useState('questions');
  const [loadingRecommendations, setLoadingRecommendations] = useState(false);
  const [loadError, setLoadError] = useState(null);
  const [candidatePlants, setCandidatePlants] = useState([]);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [likedPlants, setLikedPlants] = useState([]);
  const [dismissedPlantIds, setDismissedPlantIds] = useState([]);
  const [activePlantDetail, setActivePlantDetail] = useState(null);
  const [detailLoadingId, setDetailLoadingId] = useState(null);
  const [gateways, setGateways] = useState([]);
  const [checkoutLoading, setCheckoutLoading] = useState(false);
  const [checkoutError, setCheckoutError] = useState(null);
  const [manualProofLoading, setManualProofLoading] = useState(false);
  const [manualPayment, setManualPayment] = useState(null);
  const [plantForCheckout, setPlantForCheckout] = useState(null);
  const [gatewayDialogOpen, setGatewayDialogOpen] = useState(false);
  const detailDialogRef = useRef(null);
  const isAuthenticated = authService.isAuthenticated();

  useEffect(() => {
    let active = true;

    const loadMatchData = async () => {
      try {
        const [projectsResponse, locationsResponse] = await Promise.all([
          proyectosService.getProyectos({
            perPage: 100,
            fields: 'id,salesforce_id,name,slug,comuna',
          }),
          PlantsService.getLocationFilters(),
        ]);

        if (!active) {
          return;
        }

        setProjectCatalog(Array.isArray(projectsResponse?.data) ? projectsResponse.data : []);
        setOrientationOptions(Array.isArray(locationsResponse?.orientaciones) ? locationsResponse.orientaciones : []);
        setEntregaOptions(getConfiguredEntregaAliases(locationsResponse?.entregas || []));
      } catch {
        if (!active) {
          return;
        }

        setProjectCatalog([]);
        setOrientationOptions([]);
        setEntregaOptions([]);
      }
    };

    loadMatchData();

    return () => {
      active = false;
    };
  }, []);

  const rangeField = useMemo(() => {
    const formFields = Array.isArray(config?.contact_page?.form_fields)
      ? config.contact_page.form_fields
      : [];

    return formFields.find((field) => field?.key === 'rango' && field?.type === 'select') || null;
  }, [config?.contact_page?.form_fields]);

  const rangeOptions = useMemo(() => {
    if (!Array.isArray(rangeField?.options)) {
      return [];
    }

    return rangeField.options
      .map((option) => ({
        value: `${option?.value ?? option?.label ?? ''}`.trim(),
        label: `${option?.label ?? option?.value ?? ''}`.trim(),
        projectTypes: Array.isArray(option?.project_types) ? option.project_types : [],
        projects: Array.isArray(option?.projects) ? option.projects : [],
      }))
      .filter((option) => option.value !== '');
  }, [rangeField]);

  const selectedRangeOption = useMemo(
    () => rangeOptions.find((option) => option.value === answers.rango) || null,
    [answers.rango, rangeOptions]
  );

  const projectIndex = useMemo(() => {
    const index = new Map();

    projectCatalog.forEach((project) => {
      const keys = [project?.name, project?.slug]
        .map((value) => normalizeText(value))
        .filter(Boolean);

      keys.forEach((key) => {
        index.set(key, project);
      });
    });

    return index;
  }, [projectCatalog]);

  const recommendedProjects = useMemo(() => {
    if (!selectedRangeOption) {
      return [];
    }

    return selectedRangeOption.projects
      .map((projectName) => projectIndex.get(normalizeText(projectName)) || null)
      .filter(Boolean);
  }, [projectIndex, selectedRangeOption]);

  const recommendedProjectSlugs = useMemo(() => recommendedProjects
    .map((project) => slugifySegment(project.slug || project.name || ''))
    .filter(Boolean), [recommendedProjects]);

  const comunaOptions = useMemo(() => {
    const sourceProjects = recommendedProjects.length > 0 ? recommendedProjects : projectCatalog;

    return [...new Map(
      sourceProjects
        .map((project) => `${project?.comuna ?? ''}`.trim())
        .filter(Boolean)
        .map((comuna) => [normalizeText(comuna), comuna])
    ).values()].sort((left, right) => left.localeCompare(right, 'es', { sensitivity: 'base' }));
  }, [projectCatalog, recommendedProjects]);

  useEffect(() => {
    if (!answers.comuna) {
      return;
    }

    const stillExists = comunaOptions.some((option) => normalizeText(option) === normalizeText(answers.comuna));

    if (!stillExists) {
      setAnswers((currentAnswers) => ({
        ...currentAnswers,
        comuna: '',
      }));
    }
  }, [answers.comuna, comunaOptions]);

  const currentPlant = candidatePlants[currentIndex] || null;
  const nextPlant = candidatePlants[currentIndex + 1] || null;
  const canStart = answers.rango !== '';

  const startMatching = useCallback(async () => {
    if (!canStart || loadingRecommendations) {
      return;
    }

    setLoadingRecommendations(true);
    setLoadError(null);

    const baseFilters = {
      perPage: 100,
      disponible: 1,
    };

    if (isSaleEventActive) {
      baseFilters.evento_sale = 1;
    }

    if (recommendedProjectSlugs.length > 0) {
      baseFilters.project_slug = recommendedProjectSlugs;
    }

    if (answers.entrega) {
      const aliasProjectSlugs = getProjectSlugsByAlias(answers.entrega);

      if (aliasProjectSlugs.length > 0) {
        if (Array.isArray(baseFilters.project_slug) && baseFilters.project_slug.length > 0) {
          baseFilters.project_slug = baseFilters.project_slug.filter((slug) => aliasProjectSlugs.includes(slug));
        } else {
          baseFilters.project_slug = aliasProjectSlugs;
        }
      } else {
        const groupedStageKeys = getStageKeysByAlias(answers.entrega);
        baseFilters.entrega = groupedStageKeys.length > 0 ? groupedStageKeys : answers.entrega;
      }
    }

    try {
      let response = await PlantsService.getAll(baseFilters);
      let responseData = Array.isArray(response?.data) ? response.data : [];

      if (responseData.length === 0 && recommendedProjectSlugs.length > 0) {
        const fallbackFilters = {
          perPage: 100,
          disponible: 1,
        };

        if (isSaleEventActive) {
          fallbackFilters.evento_sale = 1;
        }

        response = await PlantsService.getAll(fallbackFilters);
        responseData = Array.isArray(response?.data) ? response.data : [];
      }

      const mappedPlants = responseData
        .map((plant) => mapPlant(plant, isSaleEventActive, priceSource))
        .filter((plant) => Boolean(plant.frontImage || plant.coverImage))
        .map((plant) => ({
          ...plant,
          recommendationScore: buildRecommendationScore(plant, answers, recommendedProjectSlugs),
        }))
        .sort((leftPlant, rightPlant) => {
          if (rightPlant.recommendationScore !== leftPlant.recommendationScore) {
            return rightPlant.recommendationScore - leftPlant.recommendationScore;
          }

          return (leftPlant.precioSeleccionado || leftPlant.precioFinal || leftPlant.precioBase || Number.MAX_SAFE_INTEGER)
            - (rightPlant.precioSeleccionado || rightPlant.precioFinal || rightPlant.precioBase || Number.MAX_SAFE_INTEGER);
        });

      setCandidatePlants(mappedPlants);
      setCurrentIndex(0);
      setLikedPlants([]);
      setDismissedPlantIds([]);
      setStage(mappedPlants.length > 0 ? 'swipe' : 'results');
    } catch (error) {
      setLoadError(error?.userMessage || 'No pudimos cargar recomendaciones en este momento.');
    } finally {
      setLoadingRecommendations(false);
    }
  }, [answers, canStart, isSaleEventActive, loadingRecommendations, priceSource, recommendedProjectSlugs]);

  const handleChoice = useCallback((selectedPlant, liked) => {
    if (liked) {
      setLikedPlants((currentPlants) => [...currentPlants, selectedPlant]);
    } else {
      setDismissedPlantIds((currentIds) => [...currentIds, selectedPlant.id]);
    }

    setCurrentIndex((index) => {
      const nextIndex = index + 1;

      if (nextIndex >= candidatePlants.length) {
        setStage('results');
      }

      return nextIndex;
    });
  }, [candidatePlants.length]);

  const closePlantDetail = useCallback(() => {
    setActivePlantDetail(null);
    setDetailLoadingId(null);
  }, []);

  const openPlantDetail = useCallback(async (plant) => {
    if (!plant || detailLoadingId === plant.id) {
      return;
    }

    const blockedLocally = plant.isPaid || plant.isReserved || plant.isAvailable === false;

    if (blockedLocally) {
      setLoadError('Esta planta ya no esta disponible para ver detalle porque fue reservada o pagada.');
      return;
    }

    setLoadError(null);
    setDetailLoadingId(plant.id);

    try {
      const latestPlant = await PlantsService.getById(plant.id);
      const mappedLatestPlant = mapPlant(latestPlant, isSaleEventActive, priceSource);
      const blockedLatest = mappedLatestPlant.isPaid || mappedLatestPlant.isReserved || mappedLatestPlant.isAvailable === false;

      if (blockedLatest) {
        setLoadError('Esta planta ya no esta disponible para ver detalle porque fue reservada o pagada.');
        return;
      }

      setActivePlantDetail(mappedLatestPlant);
    } catch {
      setActivePlantDetail(plant);
    } finally {
      setDetailLoadingId(null);
    }
  }, [detailLoadingId, isSaleEventActive, priceSource]);

  const handleQuickCheckout = useCallback(async (plant) => {
    try {
      setCheckoutLoading(true);
      setCheckoutError(null);
      setManualPayment(null);

      const availableGateways = await CheckoutService.getAvailableGateways(plant?.id);

      setGateways(availableGateways);
      setPlantForCheckout(plant);
      setGatewayDialogOpen(true);
    } catch (error) {
      setCheckoutError({
        type: error.type || 'gateway',
        message: error.message || 'Error al cargar pasarelas',
        userMessage: error.userMessage || 'No se pudieron cargar las pasarelas de pago para este proyecto.',
        title: 'Aviso',
      });
    } finally {
      setCheckoutLoading(false);
    }
  }, []);

  const handleConfirmCheckout = useCallback(async ({ plantId, gateway, sessionToken, turnstileToken, userData }) => {
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

      if (response.flow === 'manual') {
        setManualPayment(response);
        setCheckoutLoading(false);
        return;
      }

      CheckoutService.redirect(response);
    } catch (error) {
      setCheckoutError({
        type: error.type || 'unknown',
        message: error.message || 'Error en checkout',
        userMessage: error.userMessage || 'Error al iniciar el checkout. Por favor, intenta de nuevo.',
        title: 'Error en el pago',
        details: error.details,
      });
      setCheckoutLoading(false);
    }
  }, [isAuthenticated]);

  const handleManualProofSubmission = useCallback(async ({ paymentId, proofFile }) => {
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
  }, []);

  const handleCheckoutFromDetail = useCallback(() => {
    if (!activePlantDetail) {
      return;
    }

    if (detailDialogRef.current) {
      detailDialogRef.current.open = false;
    }

    handleQuickCheckout(activePlantDetail);
  }, [activePlantDetail, handleQuickCheckout]);

  const restartFlow = () => {
    setStage('questions');
    setCurrentIndex(0);
    setCandidatePlants([]);
    setLikedPlants([]);
    setDismissedPlantIds([]);
    setLoadError(null);
  };

  return (
    <div className="wa-stack wa-gap-2xl">
      <SiteHeader
        config={config}
        currentPath={currentPath}
        onNavigate={onNavigate}
      />

      <section className="match-page wa-stack wa-gap-xl wa-px-l wa-py-xl">
        <div className="match-page__hero wa-grid wa-gap-l">
          <wa-card appearance="filled">
            <div className="match-page__hero-card wa-stack wa-gap-s">
              <wa-badge variant="brand" appearance="filled" style={{ '--wa-color-on-normal': '#FFF' }}>
                <wa-icon name="wand-magic-sparkles" slot="start"></wa-icon>
                Nuevo recomendador
              </wa-badge>
              <h1 className="match-page__title">Haz match con tu proxima planta</h1>
              <p className="match-page__subtitle">
                Cuentanos que estas buscando, filtramos proyectos segun tu rango de renta y despues eliges con swipe las laminas que mas te gusten.
              </p>
            </div>
          </wa-card>

          <wa-card>
            <div className="match-page__summary wa-stack wa-gap-xs">
              <span className="match-page__summary-label">Como funciona</span>
              <p><wa-icon name="list-check" label="Paso 1"></wa-icon> Respondes preguntas basicas.</p>
              <p><wa-icon name="sliders" label="Paso 2"></wa-icon> Armamos recomendaciones con los proyectos disponibles para tu renta.</p>
              <p><wa-icon name="hand-pointer" label="Paso 3"></wa-icon> Deslizas a izquierda o derecha y al final ves tus favoritas.</p>
            </div>
          </wa-card>
        </div>

        {stage === 'questions' && (
          <div className="match-questionnaire wa-stack wa-gap-l">
            <wa-card>
              <div className="match-questionnaire__grid">
                <label className="match-field">
                  <span className="match-field__label match-field__label--required">
                    <wa-icon name="wallet" label="Rango"></wa-icon>
                    {rangeField?.label || 'Rango de renta'}
                    <wa-tag size="small" variant="danger">Obligatorio</wa-tag>
                  </span>
                  <wa-select
                    name="rango"
                    placeholder={rangeField?.placeholder || 'Selecciona tu rango de renta'}
                    value={answers.rango}
                    onChange={(event) => setAnswers((currentAnswers) => ({
                      ...currentAnswers,
                      rango: `${event.target.value ?? ''}`,
                    }))}
                  >
                    {rangeOptions.map((option) => (
                      <wa-option key={option.value} value={option.value}>{option.label}</wa-option>
                    ))}
                  </wa-select>
                </label>

                <label className="match-field">
                  <span className="match-field__label">
                    <wa-icon name="location-dot" label="Comuna"></wa-icon>
                    Comuna donde te gustaria vivir
                    <wa-tag size="small" variant="neutral">Opcional</wa-tag>
                  </span>
                  <wa-select
                    name="comuna"
                    placeholder="Selecciona una comuna"
                    value={answers.comuna}
                    onChange={(event) => setAnswers((currentAnswers) => ({
                      ...currentAnswers,
                      comuna: `${event.target.value ?? ''}`,
                    }))}
                  >
                    {comunaOptions.map((option) => (
                      <wa-option key={option} value={option}>{option}</wa-option>
                    ))}
                  </wa-select>
                </label>

                <label className="match-field">
                  <span className="match-field__label">
                    <wa-icon name="bed" label="Dormitorios"></wa-icon>
                    Cuantos dormitorios te gustaria tener
                    <wa-tag size="small" variant="neutral">Opcional</wa-tag>
                  </span>
                  <wa-select
                    name="dormitorios"
                    placeholder="Selecciona dormitorios"
                    value={answers.dormitorios}
                    onChange={(event) => setAnswers((currentAnswers) => ({
                      ...currentAnswers,
                      dormitorios: `${event.target.value ?? ''}`,
                    }))}
                  >
                    {BEDROOM_OPTIONS.map((option) => (
                      <wa-option key={option.value} value={option.value}>{option.label}</wa-option>
                    ))}
                  </wa-select>
                </label>

                <label className="match-field">
                  <span className="match-field__label">
                    <wa-icon name="compass" label="Orientacion"></wa-icon>
                    Orientacion ideal
                    <wa-tag size="small" variant="neutral">Opcional</wa-tag>
                  </span>
                  <wa-select
                    name="orientacion"
                    placeholder="Selecciona orientacion"
                    value={answers.orientacion}
                    onChange={(event) => setAnswers((currentAnswers) => ({
                      ...currentAnswers,
                      orientacion: `${event.target.value ?? ''}`,
                    }))}
                  >
                    {orientationOptions.map((option) => (
                      <wa-option key={option} value={option}>{option}</wa-option>
                    ))}
                  </wa-select>
                </label>

                <label className="match-field">
                  <span className="match-field__label">
                    <wa-icon name="key" label="Entrega"></wa-icon>
                    Tipo de entrega
                    <wa-tag size="small" variant="neutral">Opcional</wa-tag>
                  </span>
                  <wa-select
                    name="entrega"
                    placeholder="Selecciona tipo de entrega"
                    value={answers.entrega}
                    onChange={(event) => setAnswers((currentAnswers) => ({
                      ...currentAnswers,
                      entrega: `${event.target.value ?? ''}`,
                    }))}
                  >
                    {entregaOptions.map((option) => (
                      <wa-option key={option} value={option}>{option}</wa-option>
                    ))}
                  </wa-select>
                </label>
              </div>
            </wa-card>

            <wa-card>
              <div className="match-range-preview wa-stack wa-gap-s">
                <div className="wa-split wa-gap-s wa-align-items-center">
                  <span className="match-page__summary-label">Proyectos sugeridos por tu renta</span>
                  {selectedRangeOption?.projectTypes?.[0] ? (
                    <wa-badge variant="neutral">{selectedRangeOption.projectTypes[0]}</wa-badge>
                  ) : null}
                </div>

                {recommendedProjects.length > 0 ? (
                  <div className="wa-cluster wa-gap-xs">
                    {recommendedProjects.map((project) => (
                      <wa-badge key={project.id} variant="brand" style={{ '--wa-color-on-normal': '#FFF' }} appearance="filled-outlined">{project.name}</wa-badge>
                    ))}
                  </div>
                ) : (
                  <p className="match-page__hint">
                    {answers.rango
                      ? 'Aun no encontramos coincidencias exactas entre este rango y el catalogo de proyectos. Igual podremos sugerirte alternativas del catalogo activo.'
                      : 'Selecciona un rango de renta para activar la recomendacion por proyectos.'}
                  </p>
                )}

                <div className="wa-cluster wa-gap-s">
					<wa-button variant="brand" size="large" pill disabled={!canStart || loadingRecommendations} onClick={startMatching}>
						<wa-icon name={loadingRecommendations ? 'spinner' : 'champagne-glasses'} slot="start" animation={loadingRecommendations ? 'spin' : undefined}></wa-icon>
						{loadingRecommendations ? 'Buscando opciones...' : 'Comenzar match'}
					</wa-button>
					<wa-button appearance="outlined" pill onClick={() => setAnswers(INITIAL_ANSWERS)}>
						<wa-icon name="heart-crack"></wa-icon>
                    	Limpiar respuestas
                  	</wa-button>
                </div>

                {loadError ? <p className="match-page__error">{loadError}</p> : null}
              </div>
            </wa-card>
          </div>
        )}

        {stage === 'swipe' && currentPlant && (
          <div className="wa-stack wa-gap-l">
            <div className="wa-split wa-gap-s wa-align-items-center" style={{ flexWrap: 'wrap' }}>
              <div className="wa-stack wa-gap-3xs">
                <span className="match-page__summary-label">Deck de recomendaciones</span>
                <p className="match-page__hint">
                  Desliza a la derecha si te gusta y a la izquierda si prefieres seguir viendo otras opciones.
                </p>
              </div>
              <wa-badge variant="neutral">{Math.min(currentIndex + 1, candidatePlants.length)} / {candidatePlants.length}</wa-badge>
            </div>

            <MatchSwipeDeck
              currentPlant={currentPlant}
              nextPlant={nextPlant}
              onLike={(plant) => handleChoice(plant, true)}
              onDislike={(plant) => handleChoice(plant, false)}
              onCardClick={openPlantDetail}
              onViewMatches={() => setStage('results')}
            />
          </div>
        )}

        {stage === 'results' && (
          <div className="wa-stack wa-gap-l">
            <wa-card appearance="filled">
              <div className="match-results__hero wa-stack wa-gap-s">
                <span className="match-page__summary-label">Resultado final</span>
                <h2 className="match-results__title">{likedPlants.length > 0 ? 'Estas son tus plantas favoritas' : 'No guardaste favoritas todavia'}</h2>
                <p className="match-page__hint">
                  {likedPlants.length > 0
                    ? `Marcaste ${likedPlants.length} planta${likedPlants.length === 1 ? '' : 's'} con like y descartaste ${dismissedPlantIds.length}.`
                    : 'Puedes reiniciar el flujo y volver a revisar las recomendaciones con otros criterios.'}
                </p>
                <div className="wa-cluster wa-gap-s">
                  <wa-button variant="brand" pill onClick={restartFlow}>Volver a empezar</wa-button>
                  <wa-button appearance="outlined" pill onClick={() => onNavigate?.('/plantas')}>Ir al catalogo</wa-button>
                </div>
              </div>
            </wa-card>

            {likedPlants.length > 0 ? (
              <div className="match-results-grid">
                {likedPlants.map((plant) => (
                  <wa-card key={plant.id} className="match-result-card" onClick={() => openPlantDetail(plant)}>
                    <div className="match-result-card__media">
                      <img src={plant.frontImage || plant.coverImage || plant.detailImageUrl} alt={`Lamina de planta ${plant.nombre}`} />
                    </div>
                    <div className="match-result-card__body wa-stack wa-gap-s">
                      <div className="wa-stack wa-gap-3xs">
                        <span className="match-card__eyebrow">{plant.proyectoNombre}</span>
                        <h3 className="match-result-card__title">Planta {plant.nombre}</h3>
                      </div>
                      <div className="match-card__meta wa-cluster wa-gap-xs">
                        {plant.proyectoComuna && <wa-badge variant="neutral">{plant.proyectoComuna}</wa-badge>}
                        {plant.programa && <wa-badge variant="neutral">{plant.programa}</wa-badge>}
                        {plant.orientacion && <wa-badge variant="neutral">Orient. {plant.orientacion}</wa-badge>}
                      </div>
                      <div className="wa-split wa-gap-s wa-align-items-center">
                        <strong>{formatPrice(plant.precioSeleccionado || plant.precioFinal || plant.precioBase)}</strong>
                        <wa-button
                          size="small"
                          variant="brand"
                          pill
                          onClick={(event) => {
                            event.stopPropagation();
                            onNavigate?.(buildPlantDetailPath(plant));
                          }}
                        >
                          Ver detalle
                        </wa-button>
                      </div>
                    </div>
                  </wa-card>
                ))}
              </div>
            ) : null}
          </div>
        )}
      </section>

      {activePlantDetail ? (
        <Suspense fallback={null}>
          <PlantDetailDialog
            plant={activePlantDetail}
            isSaleEventActive={isSaleEventActive}
            saleLogoUrl={config?.evento_sale_logo || null}
            dialogRef={detailDialogRef}
            checkoutLoading={checkoutLoading}
            onCheckout={handleCheckoutFromDetail}
            onClose={closePlantDetail}
          />
        </Suspense>
      ) : null}

      {gatewayDialogOpen ? (
        <Suspense fallback={null}>
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
        </Suspense>
      ) : null}

      <ErrorNotification
        error={checkoutError}
        onClose={() => setCheckoutError(null)}
        duration={6000}
      />

      <SiteFooter config={config} onNavigate={onNavigate} />
    </div>
  );
}

export default Match;
