/**
 * Utilidad para manejo centralizado de errores
 */

/**
 * Tipos de errores comunes
 */
export const ErrorTypes = {
  NETWORK: 'network',
  VALIDATION: 'validation',
  SERVER: 'server',
  AUTHENTICATION: 'authentication',
  NOT_FOUND: 'not_found',
  GATEWAY: 'gateway',
  UNKNOWN: 'unknown',
};

/**
 * Mensajes de error amigables por tipo
 */
const ERROR_MESSAGES = {
  [ErrorTypes.NETWORK]: 'No se pudo conectar con el servidor. Verifica tu conexión a internet.',
  [ErrorTypes.VALIDATION]: 'Los datos proporcionados no son válidos.',
  [ErrorTypes.SERVER]: 'Error en el servidor. Por favor, intenta más tarde.',
  [ErrorTypes.AUTHENTICATION]: 'No tienes autorización. Por favor, inicia sesión.',
  [ErrorTypes.NOT_FOUND]: 'El recurso solicitado no fue encontrado.',
  [ErrorTypes.GATEWAY]: 'Error al conectar con la pasarela de pago.',
  [ErrorTypes.UNKNOWN]: 'Ocurrió un error inesperado. Por favor, intenta de nuevo.',
};

/**
 * Extraer información útil de un error
 * @param {Error} error - Error capturado
 * @returns {Object} - Información del error procesada
 */
export function parseError(error) {
  // Error de red (sin respuesta del servidor)
  if (!error.response) {
    if (error.request) {
      return {
        type: ErrorTypes.NETWORK,
        message: ERROR_MESSAGES[ErrorTypes.NETWORK],
        details: 'No se recibió respuesta del servidor',
        code: 'NETWORK_ERROR',
      };
    }
    return {
      type: ErrorTypes.UNKNOWN,
      message: error.message || ERROR_MESSAGES[ErrorTypes.UNKNOWN],
      details: null,
      code: 'UNKNOWN_ERROR',
    };
  }

  const { status, data } = error.response;

  // Errores por código HTTP
  switch (status) {
    case 400:
      return {
        type: ErrorTypes.VALIDATION,
        message: data.message || ERROR_MESSAGES[ErrorTypes.VALIDATION],
        details: data.errors || null,
        code: 'VALIDATION_ERROR',
        status,
      };

    case 401:
    case 403:
      return {
        type: ErrorTypes.AUTHENTICATION,
        message: data.message || ERROR_MESSAGES[ErrorTypes.AUTHENTICATION],
        details: null,
        code: 'AUTH_ERROR',
        status,
      };

    case 404:
      return {
        type: ErrorTypes.NOT_FOUND,
        message: data.message || ERROR_MESSAGES[ErrorTypes.NOT_FOUND],
        details: null,
        code: 'NOT_FOUND',
        status,
      };

    case 422:
      return {
        type: ErrorTypes.VALIDATION,
        message: data.message || 'Error de validación',
        details: data.errors || null,
        code: 'UNPROCESSABLE_ENTITY',
        status,
      };

    case 500:
    case 502:
    case 503:
    case 504:
      return {
        type: ErrorTypes.SERVER,
        message: data.message || ERROR_MESSAGES[ErrorTypes.SERVER],
        details: data.error || null,
        code: 'SERVER_ERROR',
        status,
      };

    default:
      return {
        type: ErrorTypes.UNKNOWN,
        message: data.message || ERROR_MESSAGES[ErrorTypes.UNKNOWN],
        details: data.error || null,
        code: 'HTTP_ERROR',
        status,
      };
  }
}

/**
 * Formatear errores de validación para mostrar al usuario
 * @param {Object} errors - Objeto con errores de validación
 * @returns {string} - Mensaje formateado
 */
export function formatValidationErrors(errors) {
  if (!errors || typeof errors !== 'object') {
    return null;
  }

  const messages = [];
  for (const [field, fieldErrors] of Object.entries(errors)) {
    if (Array.isArray(fieldErrors)) {
      messages.push(...fieldErrors);
    } else if (typeof fieldErrors === 'string') {
      messages.push(fieldErrors);
    }
  }

  return messages.join('\n');
}

/**
 * Obtener mensaje de error amigable
 * @param {Error} error - Error capturado
 * @returns {string} - Mensaje para mostrar al usuario
 */
export function getErrorMessage(error) {
  const parsed = parseError(error);
  
  if (parsed.type === ErrorTypes.VALIDATION && parsed.details) {
    const validationMsg = formatValidationErrors(parsed.details);
    if (validationMsg) {
      return `${parsed.message}\n${validationMsg}`;
    }
  }

  return parsed.message;
}

/**
 * Log de error para debugging
 * @param {string} context - Contexto donde ocurrió el error
 * @param {Error} error - Error capturado
 */
export function logError(context, error) {
  const parsed = parseError(error);
  console.error(`[${context}]`, {
    type: parsed.type,
    message: parsed.message,
    code: parsed.code,
    status: parsed.status,
    details: parsed.details,
    originalError: error,
  });
}

/**
 * Verificar si el error es recuperable (puede reintentar)
 * @param {Error} error - Error capturado
 * @returns {boolean}
 */
export function isRetryableError(error) {
  const parsed = parseError(error);
  return [
    ErrorTypes.NETWORK,
    ErrorTypes.SERVER,
  ].includes(parsed.type);
}

export default {
  ErrorTypes,
  parseError,
  formatValidationErrors,
  getErrorMessage,
  logError,
  isRetryableError,
};
