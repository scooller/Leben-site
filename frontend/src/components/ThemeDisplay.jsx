import { useContext } from 'react';
import { SiteConfigContext } from '../contexts/SiteConfigContext';
import { useWebAwesomeTheme } from '../hooks/useWebAwesomeTheme';
import '../styles/theme-display.scss' with { type: 'css' };

/**
 * Componente que muestra el tema actual y permite previewear colores
 * Este es un componente de demostración
 */
function ThemeDisplay() {
  const { config, loading } = useContext(SiteConfigContext) || {};
  const { applyTheme } = useWebAwesomeTheme();

  const presetThemes = [
    {
      name: 'iLeben Original',
      colors: {
        primary_color: '#667eea',
        secondary_color: '#764ba2',
        accent_color: '#f59e0b',
        background_color: '#ffffff',
        text_color: '#1f2937',
      },
    },
    {
      name: 'Purple Dream',
      colors: {
        primary_color: '#8b5cf6',
        secondary_color: '#ec4899',
        accent_color: '#06b6d4',
        background_color: '#ffffff',
        text_color: '#000000',
      },
    },
    {
      name: 'Forest Green',
      colors: {
        primary_color: '#059669',
        secondary_color: '#10b981',
        accent_color: '#fbbf24',
        background_color: '#ffffff',
        text_color: '#065f46',
      },
    },
    {
      name: 'Ocean Blue',
      colors: {
        primary_color: '#0369a1',
        secondary_color: '#0284c7',
        accent_color: '#06b6d4',
        background_color: '#f8fafc',
        text_color: '#0c2d48',
      },
    },
  ];

  if (loading) {
    return (
      <div className="theme-display">
        <wa-skeleton></wa-skeleton>
      </div>
    );
  }

  return (
    <div className="theme-display">
      <wa-card>
        <div slot="header">
          <h2>Tema actual de Web Awesome</h2>
        </div>

        {config && (
          <div className="current-theme">
            <div className="color-grid">
              <div className="color-item">
                <div
                  className="color-box"
                  style={{ backgroundColor: config.primary_color }}
                ></div>
                <span className="color-label">Primario</span>
                <code>{config.primary_color}</code>
              </div>

              <div className="color-item">
                <div
                  className="color-box"
                  style={{ backgroundColor: config.secondary_color }}
                ></div>
                <span className="color-label">Secundario</span>
                <code>{config.secondary_color}</code>
              </div>

              <div className="color-item">
                <div
                  className="color-box"
                  style={{ backgroundColor: config.accent_color }}
                ></div>
                <span className="color-label">Acento</span>
                <code>{config.accent_color}</code>
              </div>

              <div className="color-item">
                <div
                  className="color-box"
                  style={{ backgroundColor: config.text_color }}
                ></div>
                <span className="color-label">Texto</span>
                <code>{config.text_color}</code>
              </div>
            </div>
          </div>
        )}

        <div className="preset-themes">
          <h3>Temas preestablecidos</h3>
          <div className="themes-list">
            {presetThemes.map((theme) => (
              <wa-card key={theme.name} className="preset-card">
                <h4>{theme.name}</h4>
                <div className="color-preview">
                  {Object.entries(theme.colors)
                    .filter(([key]) => key !== 'background_color')
                    .map(([key, color]) => (
                      <div
                        key={key}
                        className="preview-color"
                        style={{ backgroundColor: color }}
                        title={key}
                      ></div>
                    ))}
                </div>
                <wa-button
                  onClick={() => applyTheme(theme.colors)}
                  variant="primary"
                  size="sm"
                >
                  Aplicar
                </wa-button>
              </wa-card>
            ))}
          </div>
        </div>

        <div className="web-awesome-preview">
          <h3>Vista previa de componentes</h3>
          <div className="components-demo">
            <wa-button variant="primary">Botón Primario</wa-button>
            <wa-button variant="secondary">Botón Secundario</wa-button>
            <wa-button variant="success">Botón Éxito</wa-button>
            <wa-button variant="warning">Botón Advertencia</wa-button>
            <wa-button variant="danger">Botón Peligro</wa-button>
          </div>
        </div>
      </wa-card>
    </div>
  );
}

export default ThemeDisplay;
