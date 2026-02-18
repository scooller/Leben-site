import api from '../lib/api';
import { logError, parseError } from '../utils/errorHandler';

class PlantsService {
  /**
   * Obtener lista de plantas
   */
  async getAll(params = {}) {
    try {
      const response = await api.get('/plants', { params });
      return response.data;
    } catch (error) {
      logError('PlantsService.getAll', error);
      const parsed = parseError(error);
      throw {
        ...parsed,
        context: 'getAll',
        userMessage: parsed.message || 'No se pudieron cargar las plantas.',
      };
    }
  }

  /**
   * Obtener una planta por ID
   */
  async getById(id) {
    try {
      const response = await api.get(`/plants/${id}`);
      return response.data;
    } catch (error) {
      logError('PlantsService.getById', error);
      const parsed = parseError(error);
      throw {
        ...parsed,
        context: 'getById',
        userMessage: parsed.type === 'not_found' 
          ? 'La planta solicitada no existe.' 
          : 'No se pudo cargar la información de la planta.',
      };
    }
  }

  /**
   * Crear una planta
   */
  async create(data) {
    try {
      const response = await api.post('/plants', data);
      return response.data;
    } catch (error) {
      logError('PlantsService.create', error);
      const parsed = parseError(error);
      throw {
        ...parsed,
        context: 'create',
        userMessage: parsed.type === 'validation' 
          ? 'Error en los datos proporcionados.' 
          : 'No se pudo crear la planta.',
      };
    }
  }

  /**
   * Actualizar una planta
   */
  async update(id, data) {
    try {
      const response = await api.put(`/plants/${id}`, data);
      return response.data;
    } catch (error) {
      logError('PlantsService.update', error);
      const parsed = parseError(error);
      throw {
        ...parsed,
        context: 'update',
        userMessage: parsed.type === 'validation' 
          ? 'Error en los datos proporcionados.' 
          : 'No se pudo actualizar la planta.',
      };
    }
  }

  /**
   * Eliminar una planta
   */
  async delete(id) {
    try {
      const response = await api.delete(`/plants/${id}`);
      return response.data;
    } catch (error) {
      logError('PlantsService.delete', error);
      const parsed = parseError(error);
      throw {
        ...parsed,
        context: 'delete',
        userMessage: parsed.type === 'not_found' 
          ? 'La planta no existe.' 
          : 'No se pudo eliminar la planta.',
      };
    }
  }
}

export default new PlantsService();
