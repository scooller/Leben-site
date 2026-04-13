import api from '../lib/api';
import { logError, parseError, ErrorTypes } from '../utils/errorHandler';

const isValidEmail = (value) => /\S+@\S+\.\S+/.test(value);

const isValidPhone = (value) => {
  const digits = value.replace(/\D/g, '');
  return digits.length >= 8 && digits.length <= 15;
};

const formatRut = (value) => {
  const cleaned = String(value ?? '')
    .replace(/[^0-9kK]/g, '')
    .toUpperCase()
    .slice(0, 9);

  if (cleaned.length <= 1) {
    return cleaned;
  }

  const body = cleaned.slice(0, -1);
  const dv = cleaned.slice(-1);

  return `${body}-${dv}`;
};

const isValidRut = (value) => {
  const formatted = formatRut(value);

  if (!/^\d{7,8}-[0-9K]$/.test(formatted)) {
    return false;
  }

  const cleaned = formatted.replace('-', '').toLowerCase();
  if (cleaned.length < 8) {
    return false;
  }

  const body = cleaned.slice(0, -1);
  const dv = cleaned.slice(-1);

  if (body.length < 7 || body.length > 8) {
    return false;
  }

  let sum = 0;
  let multiplier = 2;

  for (let i = body.length - 1; i >= 0; i -= 1) {
    sum += Number(body[i]) * multiplier;
    multiplier = multiplier === 7 ? 2 : multiplier + 1;
  }

  const remainder = 11 - (sum % 11);
  const expectedDv = remainder === 11 ? '0' : remainder === 10 ? 'k' : `${remainder}`;

  return dv === expectedDv;
};

class CheckoutService {
  /**
   * Obtener pasarelas de pago disponibles
   */
  static async getAvailableGateways(plantId = null) {
    try {
      const response = await api.get('/payment-gateways', {
        params: plantId ? { plant_id: plantId } : {},
      });
      return response.data.gateways || [];
    } catch (error) {
      logError('CheckoutService.getAvailableGateways', error);
      const parsed = parseError(error);
      throw {
        ...parsed,
        context: 'getAvailableGateways',
        userMessage: 'No se pudieron cargar las pasarelas de pago disponibles.',
      };
    }
  }

  /**
   * Iniciar checkout para una planta
   * @param {number} plantId - ID de la planta
   * @param {number} quantity - Cantidad
   * @param {string} gateway - Pasarela de pago ('transbank' o 'mercadopago')
   * @param {{name: string, email: string, phone: string, rut: string}} userData - Datos del usuario
   */
  static async initiate(
    plantId,
    quantity = 1,
    gateway = 'transbank',
    userData = {},
    sessionToken = null,
    turnstileToken = null,
  ) {
    try {
      // Validaciones básicas antes de hacer la petición
      if (!plantId || plantId <= 0) {
        throw {
          type: ErrorTypes.VALIDATION,
          message: 'ID de planta inválido',
          userMessage: 'Error: Planta no válida',
        };
      }

      if (!gateway) {
        throw {
          type: ErrorTypes.VALIDATION,
          message: 'Pasarela de pago no seleccionada',
          userMessage: 'Por favor selecciona una pasarela de pago',
        };
      }

      if (!userData.name || !userData.email || !userData.phone || !userData.rut) {
        throw {
          type: ErrorTypes.VALIDATION,
          message: 'Datos de usuario incompletos',
          userMessage: 'Completa tu nombre, email, telefono y RUT antes de pagar',
        };
      }

      if (!isValidEmail(userData.email)) {
        throw {
          type: ErrorTypes.VALIDATION,
          message: 'Correo electronico invalido',
          userMessage: 'Ingresa un correo electronico valido.',
        };
      }

      if (!isValidPhone(userData.phone)) {
        throw {
          type: ErrorTypes.VALIDATION,
          message: 'Telefono invalido',
          userMessage: 'Ingresa un telefono valido con al menos 8 digitos.',
        };
      }

      if (!isValidRut(userData.rut)) {
        throw {
          type: ErrorTypes.VALIDATION,
          message: 'RUT invalido',
          userMessage: 'Ingresa un RUT valido sin puntos y con guion (ej: 12345678-9).',
        };
      }

      const normalizedRut = formatRut(userData.rut);

      const response = await api.post('/checkout', {
        plant_id: plantId,
        quantity,
        gateway,
        name: userData.name,
        email: userData.email,
        phone: userData.phone,
        rut: normalizedRut,
        ...(sessionToken ? { session_token: sessionToken } : {}),
        ...(turnstileToken ? { turnstile_token: turnstileToken } : {}),
      });

      const responseData = response.data;

      if (!responseData) {
        throw {
          type: ErrorTypes.GATEWAY,
          message: 'Respuesta inválida del servidor',
          userMessage: 'Error al procesar el pago. Respuesta inválida del servidor.',
        };
      }

      if (responseData.flow === 'manual') {
        if (!responseData.payment_id || !responseData.reference) {
          throw {
            type: ErrorTypes.GATEWAY,
            message: 'Respuesta inválida para pago manual',
            userMessage: 'No se pudo generar la referencia del pago manual.',
          };
        }

        return responseData;
      }

      if (!responseData.redirect_url) {
        throw {
          type: ErrorTypes.GATEWAY,
          message: 'Respuesta inválida del servidor',
          userMessage: 'Error al procesar el pago. Respuesta inválida del servidor.',
        };
      }

      return responseData;
    } catch (error) {
      // Si el error ya fue formateado (validaciones locales), lanzarlo directamente
      if (error.type && error.userMessage) {
        logError('CheckoutService.initiate', error);
        throw error;
      }

      // Si es un error de axios, parsearlo
      logError('CheckoutService.initiate', error);
      const parsed = parseError(error);

      // Agregar contexto específico para errores de checkout
      let userMessage = parsed.message;
      if (parsed.type === ErrorTypes.VALIDATION && parsed.details) {
        userMessage = 'Error de validacion: '
          + (parsed.details.name?.[0]
            || parsed.details.email?.[0]
            || parsed.details.phone?.[0]
            || parsed.details.rut?.[0]
            || parsed.details.plant_id?.[0]
            || parsed.details.gateway?.[0]
            || parsed.message);
      } else if (parsed.type === ErrorTypes.AUTHENTICATION) {
        userMessage = 'Debes iniciar sesion antes de pagar.';
      } else if (parsed.type === ErrorTypes.NETWORK) {
        userMessage = 'No se pudo conectar con el servidor de pagos. Verifica tu conexión.';
      } else if (parsed.type === ErrorTypes.SERVER) {
        userMessage = 'Error en el servidor al procesar el pago. Intenta de nuevo.';
      }

      throw {
        ...parsed,
        context: 'initiate',
        userMessage,
      };
    }
  }

  /**
   * Subir comprobante para un pago manual.
   */
  static async submitManualProof(paymentId, proofFile, notes = '') {
    try {
      if (!paymentId || paymentId <= 0) {
        throw {
          type: ErrorTypes.VALIDATION,
          message: 'Pago inválido',
          userMessage: 'No se encontro el pago manual asociado.',
        };
      }

      if (!(proofFile instanceof File)) {
        throw {
          type: ErrorTypes.VALIDATION,
          message: 'Comprobante inválido',
          userMessage: 'Selecciona un comprobante antes de continuar.',
        };
      }

      const formData = new FormData();
      formData.append('proof', proofFile);

      if (notes) {
        formData.append('notes', notes);
      }

      const response = await api.post(`/payments/${paymentId}/manual-proof`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      return response.data;
    } catch (error) {
      if (error.type && error.userMessage) {
        logError('CheckoutService.submitManualProof', error);
        throw error;
      }

      logError('CheckoutService.submitManualProof', error);
      const parsed = parseError(error);

      throw {
        ...parsed,
        context: 'submitManualProof',
        userMessage: parsed.type === ErrorTypes.VALIDATION
          ? 'No se pudo subir el comprobante. Revisa el archivo seleccionado.'
          : 'No se pudo enviar el comprobante. Intenta nuevamente.',
      };
    }
  }

  /**
   * Redirigir a la pasarela de pago
   */
  static redirect(redirectData) {
    const redirectUrl = typeof redirectData === 'string' ? redirectData : redirectData?.redirect_url;

    if (!redirectUrl) {
      const error = {
        type: ErrorTypes.VALIDATION,
        message: 'No redirect URL provided',
        userMessage: 'Error: No se recibió la URL de pago',
      };
      logError('CheckoutService.redirect', error);
      throw error;
    }

    try {
      const isTransbankRedirect = typeof redirectData === 'object'
        && redirectData?.gateway === 'transbank'
        && redirectData?.token;

      if (isTransbankRedirect) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = redirectUrl;

        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = 'token_ws';
        tokenInput.value = redirectData.token;

        form.appendChild(tokenInput);
        document.body.appendChild(form);
        form.submit();

        return;
      }

      window.location.href = redirectUrl;
    } catch (error) {
      logError('CheckoutService.redirect', error);
      throw {
        type: ErrorTypes.UNKNOWN,
        message: 'Error al redirigir a la pasarela',
        userMessage: 'No se pudo abrir la página de pago. Por favor, intenta de nuevo.',
      };
    }
  }
}

export default CheckoutService;
