import api from '../lib/api';

export const proyectosService = {
  /**
   * Obtener lista de proyectos
   */
  async getProyectos(params = {}) {
    const response = await api.get('/proyectos', { params });
    return response.data;
  },

  /**
   * Obtener un proyecto por ID
   */
  async getProyecto(id) {
    const response = await api.get(`/proyectos/${id}`);
    return response.data;
  },
};
