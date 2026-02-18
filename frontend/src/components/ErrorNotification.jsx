import { useEffect, useRef } from 'react';

/**
 * Componente de notificación de error usando wa-callout de Web Awesome
 * Muestra mensajes de error de forma elegante con auto-cierre opcional
 * 
 * @param {Object} error - Objeto con información del error (type, message, userMessage, title, details)
 * @param {Function} onClose - Callback al cerrar la notificación
 * @param {number} duration - Tiempo en ms antes de auto-cerrar (default: 5000)
 * @param {boolean} persistent - Si true, no se cierra automáticamente
 */
function ErrorNotification({ error, onClose, duration = 5000, persistent = false }) {
  const timerRef = useRef(null);

  useEffect(() => {
    if (error && !persistent && duration > 0) {
      timerRef.current = setTimeout(() => {
        if (onClose) onClose();
      }, duration);
    }

    return () => {
      if (timerRef.current) {
        clearTimeout(timerRef.current);
      }
    };
  }, [error, duration, persistent, onClose]);

  if (!error) return null;

  // Determinar el variant según el tipo de error
  const getVariant = () => {
    if (error.type === 'validation') return 'warning';
    if (error.type === 'network') return 'danger';
    if (error.type === 'server') return 'danger';
    if (error.type === 'not_found') return 'warning';
    return 'danger';
  };

  // Determinar el icono según el tipo
  const getIcon = () => {
    if (error.type === 'validation') return 'triangle-exclamation';
    if (error.type === 'network') return 'wifi-slash';
    if (error.type === 'not_found') return 'magnifying-glass';
    return 'circle-exclamation';
  };

  return (
    <>
      <style>{`
        @keyframes slideInFromRight {
          from {
            transform: translateX(100%);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
        @media (max-width: 768px) {
          .error-notification-wrapper {
            left: 20px !important;
            right: 20px !important;
            min-width: auto !important;
            max-width: none !important;
          }
        }
      `}</style>
      <div className="error-notification-wrapper" style={{
        position: 'fixed',
        top: '20px',
        right: '20px',
        minWidth: '320px',
        maxWidth: '500px',
        zIndex: 9999,
        animation: 'slideInFromRight 0.3s ease-out',
        boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)'
      }}>
        <wa-callout variant={getVariant()} closable onWaClose={onClose}>
          <wa-icon slot="icon" name={getIcon()} variant="regular"></wa-icon>
          <strong>{error.title || 'Error'}</strong>
          <div style={{ marginTop: '8px', lineHeight: '1.5' }}>
            {error.userMessage || error.message}
          </div>
          {error.details && (
            <div style={{ 
              marginTop: '8px', 
              paddingTop: '8px', 
              borderTop: '1px solid var(--wa-color-neutral-200)',
              fontSize: '0.875rem',
              color: 'var(--wa-color-neutral-600)'
            }}>
              <small>{typeof error.details === 'string' ? error.details : JSON.stringify(error.details)}</small>
            </div>
          )}
        </wa-callout>
      </div>
    </>
  );
}

export default ErrorNotification;
