import { useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { SiteConfigProvider, SiteConfigContext } from './contexts/SiteConfigContext';
import MaintenanceMode from './components/MaintenanceMode';
import ErrorNotification from './components/ErrorNotification';
import Home from './pages/Home';
import Contact from './pages/Contact';
import Payment from './pages/Payment';
import { APP_HTTP_ERROR_EVENT } from './utils/errorHandler';
import { trackPageView } from './utils/tagManager';
import { captureUtmParamsFromUrl } from './utils/utmSession';
import siteConfigService from './services/siteConfig';
import './App.scss';
import './styles/maintenance.scss';

const normalizePathname = (value) => {
  const sanitizedPath = `${value || '/'}`.split('?')[0];
  const normalized = sanitizedPath.replace(/\/+$/, '');

  return normalized === '' ? '/' : normalized;
};

const getInitialPathname = () => {
  const browserPath = normalizePathname(window.location.pathname || '/');
  const hashPath = normalizePathname(window.location.hash.replace(/^#/, '') || '/');

  if (window.location.hash && hashPath !== '/' && hashPath !== browserPath) {
    window.history.replaceState({}, '', `${hashPath}${window.location.search}`);

    return hashPath;
  }

  return browserPath;
};

const resolvePageTitle = (pathname, siteName = 'iLeben') => {
  if (pathname === '/contacto') {
    return `${siteName} | Contacto`;
  }

  if (pathname === '/pago') {
    return `${siteName} | Pago`;
  }

  if (pathname.startsWith('/p/')) {
    return `${siteName} | Planta`;
  }

  if (pathname === '/f' || pathname.startsWith('/f/')) {
    return `${siteName} | Filtros`;
  }

  return `${siteName} | Inicio`;
};

const resolvePageDescription = (pathname, siteDescription = '') => {
  if (pathname === '/contacto') {
    return siteDescription || 'Contacto comercial para asesorarte en la compra de departamentos y unidades inmobiliarias.';
  }

  if (pathname === '/pago') {
    return 'Estado y resumen de pago de tu reserva inmobiliaria.';
  }

  if (pathname === '/f' || pathname.startsWith('/f/') || pathname === '/plantas') {
    return siteDescription || 'Explora departamentos disponibles filtrando por proyecto y comuna.';
  }

  return siteDescription || 'Descubre departamentos inmobiliarios disponibles para compra en Chile.';
};

function AppContent() {
  const { config } = useContext(SiteConfigContext) || {};
  const [pathname, setPathname] = useState(getInitialPathname);
  const [globalError, setGlobalError] = useState(null);
  const lastGlobalErrorRef = useRef({ key: '', at: 0 });

  useEffect(() => {
    const handlePopState = () => {
      setPathname(normalizePathname(window.location.pathname || '/'));
    };

    window.addEventListener('popstate', handlePopState);

    return () => {
      window.removeEventListener('popstate', handlePopState);
    };
  }, []);

  useEffect(() => {
    const handleGlobalHttpError = (event) => {
      const incomingError = event?.detail;

      if (!incomingError) {
        return;
      }

      const fingerprint = `${incomingError.code || 'HTTP_ERROR'}|${incomingError.status || 0}|${incomingError.path || ''}`;
      const now = Date.now();

      if (lastGlobalErrorRef.current.key === fingerprint && now - lastGlobalErrorRef.current.at < 3000) {
        return;
      }

      lastGlobalErrorRef.current = { key: fingerprint, at: now };

      let title = 'Error';
      if (incomingError.type === 'network') {
        title = 'Sin conexion';
      } else if (incomingError.status === 401) {
        title = 'Sesion expirada';
      } else if (incomingError.status === 422) {
        title = 'Datos invalidos';
      } else if ((incomingError.status ?? 0) >= 500) {
        title = 'Error del servidor';
      }

      setGlobalError({
        ...incomingError,
        title,
      });
    };

    window.addEventListener(APP_HTTP_ERROR_EVENT, handleGlobalHttpError);

    return () => {
      window.removeEventListener(APP_HTTP_ERROR_EVENT, handleGlobalHttpError);
    };
  }, []);

  const navigate = useCallback((nextPath) => {
    const targetPath = normalizePathname(nextPath);

    if (targetPath !== pathname) {
      window.history.pushState({}, '', targetPath);
      setPathname(targetPath);
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, [pathname]);

  const currentPath = useMemo(() => normalizePathname(pathname), [pathname]);

  useEffect(() => {
    captureUtmParamsFromUrl(window.location.search || '');
  }, [pathname]);

  useEffect(() => {
    const pageTitle = resolvePageTitle(currentPath, config?.site_name || 'iLeben');
    const description = resolvePageDescription(currentPath, config?.site_description || '');
    const seoConfig = config?.seo || {};

    siteConfigService.applySeo({
      title: pageTitle,
      description,
      keywords: seoConfig.meta_keywords,
      author: seoConfig.meta_author,
      canonical: `${window.location.origin}${currentPath}`,
      robots: seoConfig.robots_default || 'index,follow',
      ogImage: seoConfig.og_image,
      ogType: currentPath === '/pago' ? 'website' : 'website',
      ogSiteName: config?.site_name || 'iLeben',
      ogLocale: seoConfig.site_locale || 'es-CL',
      twitterCard: 'summary_large_image',
      twitterSite: seoConfig.twitter_site,
    });

    if (!config?.seo?.tag_manager_id) {
      return;
    }

    trackPageView({
      path: currentPath,
      title: pageTitle,
    });
  }, [
    config?.seo,
    config?.seo?.tag_manager_id,
    config?.site_description,
    config?.site_name,
    currentPath,
  ]);

  return (
    <div className="app">
      <MaintenanceMode
        maintenanceMode={config?.maintenance_mode}
        maintenanceMessage={config?.maintenance_message}
      />
      <ErrorNotification
        error={globalError}
        onClose={() => setGlobalError(null)}
        duration={5500}
      />
      <main>
        {currentPath === '/contacto' ? (
          <Contact onNavigate={navigate} currentPath={currentPath} />
        ) : currentPath === '/pago' ? (
          <Payment onNavigate={navigate} currentPath={currentPath} />
        ) : (
          <Home onNavigate={navigate} currentPath={currentPath} />
        )}
      </main>
    </div>
  );
}

function App() {
  return (
    <SiteConfigProvider>
      <AppContent />
    </SiteConfigProvider>
  );
}

export default App;
