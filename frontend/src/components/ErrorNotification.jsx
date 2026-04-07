import { useEffect, useRef } from 'react';

/**
 * Componente de notificación usando wa-toast nativo de Web Awesome.
 *
 * @param {Object} error - Objeto con información del error (type, message, userMessage, title, details)
 * @param {Function} onClose - Callback al cerrar la notificación
 * @param {number} duration - Tiempo en ms antes de auto-cerrar (default: 5000)
 * @param {boolean} persistent - Si true, no se cierra automáticamente
 */
function ErrorNotification({ error, onClose, duration = 5000, persistent = false }) {
  const toastRef = useRef(null);
  const closeTimerRef = useRef(null);

  const getVariant = (currentError) => {
    if (currentError.type === 'validation') return 'warning';
    if (currentError.type === 'network') return 'danger';
    if (currentError.type === 'server') return 'danger';
    if (currentError.type === 'not_found') return 'warning';
    return 'danger';
  };

  const getIcon = (currentError) => {
    if (currentError.type === 'validation') return 'triangle-exclamation';
    if (currentError.type === 'network') return 'wifi-slash';
    if (currentError.type === 'not_found') return 'magnifying-glass';
    return 'circle-exclamation';
  };

  useEffect(() => {
    if (!error || !toastRef.current || typeof toastRef.current.create !== 'function') {
      return undefined;
    }

    const variant = getVariant(error);
    const icon = getIcon(error);
    const title = error.title || 'Notificacion';
    const message = error.userMessage || error.message || 'Ha ocurrido un error.';

    toastRef.current.create(`${title}: ${message}`, {
      variant,
      duration: persistent ? 0 : duration,
      icon,
    });

    if (closeTimerRef.current) {
      clearTimeout(closeTimerRef.current);
    }

    if (!persistent && duration > 0 && typeof onClose === 'function') {
      closeTimerRef.current = setTimeout(() => {
        onClose();
      }, duration + 100);
    }

    return () => {
      if (closeTimerRef.current) {
        clearTimeout(closeTimerRef.current);
      }
    };
  }, [error, duration, persistent, onClose]);

  if (!error) {
    return <wa-toast ref={toastRef} placement="top-end"></wa-toast>;
  }

  return <wa-toast ref={toastRef} placement="top-end"></wa-toast>;
}

export default ErrorNotification;
