/**
 * Conversion Tracker & Pixel Dispatcher
 *
 * Inspirado en la arquitectura multiformato de mow-plugin (WordPress):
 * - Soporta bloques <script> ejecutados dinámicamente en el DOM.
 * - Extrae <img> de bloques <noscript> y los dispara como beacons 1x1.
 * - Soporta etiquetas <img> directas y URLs limpias.
 * - Reemplaza tokens dinámicos en formato {token} con los datos del evento.
 * - Dispara CustomEvent 'pixel_tracker_dispatched' para observabilidad.
 */

/**
 * Disparar la petición GET de una imagen de píxel invisible (1x1).
 *
 * @param {string} src URL de la imagen/píxel.
 * @param {Element|null} originalElement Elemento DOM original si existe.
 */
export function triggerImagePixel(src, originalElement = null) {
  if (typeof document === 'undefined' || !src) return;

  const pixel = new Image();
  pixel.src = src;
  pixel.width = originalElement && originalElement.getAttribute('width')
    ? parseInt(originalElement.getAttribute('width'), 10)
    : 1;
  pixel.height = originalElement && originalElement.getAttribute('height')
    ? parseInt(originalElement.getAttribute('height'), 10)
    : 1;
  pixel.style.position = 'absolute';
  pixel.style.width = '1px';
  pixel.style.height = '1px';
  pixel.style.opacity = '0';
  pixel.style.pointerEvents = 'none';

  if (document.body) {
    document.body.appendChild(pixel);
    pixel.onload = pixel.onerror = () => {
      if (pixel.parentNode) {
        pixel.parentNode.removeChild(pixel);
      }
    };
  }
}

/**
 * Procesa y ejecuta un snippet de seguimiento.
 *
 * @param {string} rawSnippet Código HTML/JS/URL configurado.
 * @param {Object} data Datos para reemplazar tokens ({name}, {email}, etc.).
 * @param {boolean} debug Si es true, imprime trazas en consola.
 * @param {string} type Tipo de conversión ('contact' | 'payment').
 */
export function executeTrackingSnippet(rawSnippet, data = {}, debug = false, type = 'conversion') {
  if (typeof document === 'undefined' || !rawSnippet || typeof rawSnippet !== 'string') {
    return;
  }

  // Reemplazar tokens dinámicos {key} case-insensitive
  let processedSnippet = rawSnippet;
  if (data && typeof data === 'object') {
    Object.entries(data).forEach(([key, val]) => {
      const regex = new RegExp(`\\{${key}\\}`, 'gi');
      processedSnippet = processedSnippet.replace(regex, val !== null && val !== undefined ? String(val) : '');
    });
  }

  if (debug) {
    console.info(`[Conversion Tracker] Disparando snippet (${type}):`, {
      type,
      data,
      snippet: processedSnippet,
    });
  }

  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = processedSnippet;

  // 1. Procesar etiquetas <noscript> (extraer imágenes interiores)
  const noscriptTags = tempDiv.getElementsByTagName('noscript');
  let noscriptHtml = '';
  for (let i = 0; i < noscriptTags.length; i += 1) {
    noscriptHtml += `${noscriptTags[i].textContent || noscriptTags[i].innerHTML || ''}\n`;
  }

  if (noscriptHtml) {
    const noscriptContainer = document.createElement('div');
    noscriptContainer.innerHTML = noscriptHtml;
    const innerImages = noscriptContainer.getElementsByTagName('img');
    for (let j = 0; j < innerImages.length; j += 1) {
      triggerImagePixel(innerImages[j].getAttribute('src'), innerImages[j]);
    }
  }

  // 2. Procesar etiquetas directas <img> fuera de <noscript>
  const directImages = tempDiv.getElementsByTagName('img');
  for (let k = 0; k < directImages.length; k += 1) {
    const img = directImages[k];
    if (img.parentNode && img.parentNode.tagName.toLowerCase() !== 'noscript') {
      triggerImagePixel(img.getAttribute('src'), img);
    }
  }

  // 3. Procesar etiquetas <script>
  // Como innerHTML no ejecuta scripts por seguridad, creamos elementos DOM reales.
  const scriptTags = tempDiv.getElementsByTagName('script');
  for (let s = 0; s < scriptTags.length; s += 1) {
    const oldScript = scriptTags[s];
    const newScript = document.createElement('script');

    for (let a = 0; a < oldScript.attributes.length; a += 1) {
      const attr = oldScript.attributes[a];
      newScript.setAttribute(attr.name, attr.value);
    }

    if (oldScript.textContent) {
      newScript.textContent = oldScript.textContent;
    }

    (document.head || document.body).appendChild(newScript);
  }

  // 4. URL limpia directa (fallback como beacon 1x1)
  const trimmed = processedSnippet.trim();
  if (trimmed.indexOf('<') === -1 && (trimmed.startsWith('http://') || trimmed.startsWith('https://'))) {
    triggerImagePixel(trimmed);
  }

  // 5. Emitir evento CustomEvent para observabilidad
  const eventDetailData = {
    type,
    data,
    snippet: processedSnippet,
    timestamp: Date.now(),
  };

  try {
    const customEvt = new CustomEvent('pixel_tracker_dispatched', {
      detail: eventDetailData,
    });
    window.dispatchEvent(customEvt);
  } catch {
    // Ignorar en navegadores sin soporte CustomEvent
  }

  if (debug) {
    console.log('✅ [Conversion Tracker] Píxel ejecutado con éxito:', eventDetailData);
  }
}

/**
 * Disparar script tras envío exitoso de formulario de contacto.
 *
 * @param {Object} conversionConfig Configuración conversion_scripts del backend.
 * @param {Object} contactData Datos del contacto ({ form_id, name, email, phone, rut, channel, project_id }).
 */
export function triggerContactConversion(conversionConfig, contactData = {}) {
  if (!conversionConfig?.enabled) return;

  const script = conversionConfig.post_contact_script;
  if (!script) return;

  executeTrackingSnippet(script, contactData, Boolean(conversionConfig.debug), 'contact');
}

/**
 * Disparar script tras proceso de pago / checkout / reserva.
 *
 * @param {Object} conversionConfig Configuración conversion_scripts del backend.
 * @param {Object} paymentData Datos del pago ({ payment_id, order_id, amount, gateway, unit_id, ... }).
 */
export function triggerPaymentConversion(conversionConfig, paymentData = {}) {
  if (!conversionConfig?.enabled) return;

  const script = conversionConfig.post_payment_script;
  if (!script) return;

  executeTrackingSnippet(script, paymentData, Boolean(conversionConfig.debug), 'payment');
}
