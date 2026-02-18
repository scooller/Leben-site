import axios from 'axios';
import { logError, parseError, ErrorTypes } from '../utils/errorHandler';

const API_URL = import.meta.env.VITE_API_URL;

class CheckoutService {
  /**
   * Obtener pasarelas de pago disponibles
   */
  static async getAvailableGateways() {
    try {
      const response = await axios.get(`${API_URL}/payment-gateways`);
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
   */
  static async initiate(plantId, quantity = 1, gateway = 'transbank') {
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

      const response = await axios.post(`${API_URL}/checkout`, {
        plant_id: plantId,
        quantity,
        gateway,
      });

      // Validar que la respuesta contenga redirect_url
      if (!response.data || !response.data.redirect_url) {
        throw {
          type: ErrorTypes.GATEWAY,
          message: 'Respuesta inválida del servidor',
          userMessage: 'Error al procesar el pago. Respuesta inválida del servidor.',
        };
      }

      return response.data;
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
        userMessage = 'Error de validación: ' + (parsed.details.plant_id?.[0] || parsed.details.gateway?.[0] || parsed.message);
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
   * Redirigir a la pasarela de pago
   */
  static redirect(redirectUrl) {
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
