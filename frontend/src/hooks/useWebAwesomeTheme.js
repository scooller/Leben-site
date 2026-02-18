import { useCallback } from 'react';
import WebAwesomeService from '../services/webAwesome';

/**
 * Hook para cambiar el tema de Web Awesome dinámicamente
 * 
 * Uso:
 * const { applyTheme, applyPrebuiltTheme } = useWebAwesomeTheme();
 * 
 * // Aplicar colores personalizados
 * applyTheme({
 *   primary_color: '#667eea',
 *   secondary_color: '#764ba2',
 *   accent_color: '#f59e0b',
 *   background_color: '#ffffff',
 *   text_color: '#1f2937',
 * });
 * 
 * // O usar un tema predefinido
 * applyPrebuiltTheme('awesome');
 */
export function useWebAwesomeTheme() {
  const applyTheme = useCallback((colors) => {
    WebAwesomeService.applyTheme(colors);
  }, []);

  const applyPrebuiltTheme = useCallback((themeName) => {
    WebAwesomeService.applyPrebuiltTheme(themeName);
  }, []);

  const getDefaultTheme = useCallback(() => {
    return WebAwesomeService.getDefaultTheme();
  }, []);

  return {
    applyTheme,
    applyPrebuiltTheme,
    getDefaultTheme,
  };
}

export default useWebAwesomeTheme;
