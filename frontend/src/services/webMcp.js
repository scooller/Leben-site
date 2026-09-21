/**
 * WebMCP — Web Model Context Protocol Browser Integration
 * Spec: https://webmachinelearning.github.io/webmcp/
 * Exposes real-estate search, floor plan inspection, contact, and reservation tools to AI agents.
 */

export const webMcpTools = [
  {
    name: 'search_projects',
    description: 'Search available real-estate projects and floor plans in Chile by keyword, location, or stage',
    inputSchema: {
      type: 'object',
      properties: {
        query: {
          type: 'string',
          description: 'Search term, commune, or project name (e.g., "Concepción", "Santiago")',
        },
        etapa: {
          type: 'string',
          description: 'Development stage: "venta", "pre_venta", "en_blanco", "en_verde"',
          enum: ['venta', 'pre_venta', 'en_blanco', 'en_verde'],
        },
      },
    },
    execute: async ({ query = '', etapa = '' } = {}) => {
      try {
        const params = new URLSearchParams();
        if (query) params.append('search', query);
        if (etapa) params.append('etapa', etapa);
        const url = `/api/v1/proyectos${params.toString() ? '?' + params.toString() : ''}`;
        const res = await fetch(url);
        return await res.json();
      } catch (err) {
        return { error: String(err) };
      }
    },
  },
  {
    name: 'get_plant_details',
    description: 'Get details, layout, surface area, and pricing for a specific housing unit (planta)',
    inputSchema: {
      type: 'object',
      properties: {
        id: {
          type: 'string',
          description: 'Unit identifier or slug',
        },
      },
      required: ['id'],
    },
    execute: async ({ id } = {}) => {
      try {
        const res = await fetch(`/api/v1/plantas/${encodeURIComponent(id)}`);
        return await res.json();
      } catch (err) {
        return { error: String(err) };
      }
    },
  },
  {
    name: 'contact_sales_advisor',
    description: 'Submit an inquiry or contact request to sales advisors for a real estate project',
    inputSchema: {
      type: 'object',
      properties: {
        name: { type: 'string', description: 'Buyer or lead full name' },
        email: { type: 'string', description: 'Contact email address' },
        phone: { type: 'string', description: 'Phone number' },
        message: { type: 'string', description: 'Inquiry message or question' },
        proyecto_id: { type: 'string', description: 'Optional ID or slug of the project' },
      },
      required: ['name', 'email'],
    },
    execute: async (params = {}) => {
      try {
        const res = await fetch('/api/v1/contact-submissions', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(params),
        });
        return await res.json();
      } catch (err) {
        return { error: String(err) };
      }
    },
  },
  {
    name: 'reserve_unit',
    description: 'Initiate a reservation checkout session for an active housing unit',
    inputSchema: {
      type: 'object',
      properties: {
        planta_id: { type: 'string', description: 'ID of the unit to reserve' },
        customer_name: { type: 'string', description: 'Customer full name' },
        customer_email: { type: 'string', description: 'Customer email' },
        customer_phone: { type: 'string', description: 'Customer phone number' },
      },
      required: ['planta_id', 'customer_name', 'customer_email'],
    },
    execute: async (params = {}) => {
      try {
        const res = await fetch('/api/v1/checkout', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(params),
        });
        return await res.json();
      } catch (err) {
        return { error: String(err) };
      }
    },
  },
];

let isInitialized = false;

export function initWebMcp() {
  if (typeof window === 'undefined' || isInitialized) return;
  isInitialized = true;

  const controller = new AbortController();

  const registerOnModelContext = (mc) => {
    if (!mc) return;

    // provideContext() call (per W3C WebMCP draft and agent scan requirements)
    if (typeof mc.provideContext === 'function') {
      try {
        mc.provideContext({
          tools: webMcpTools,
          signal: controller.signal,
        });
      } catch (e) {
        console.debug('WebMCP provideContext error:', e);
      }
    }

    // registerTool() call (per Chrome EPP and standard tool registration)
    if (typeof mc.registerTool === 'function') {
      webMcpTools.forEach((tool) => {
        try {
          mc.registerTool(tool, { signal: controller.signal });
        } catch (e) {
          console.debug(`WebMCP registerTool(${tool.name}) error:`, e);
        }
      });
    }
  };

  // If navigator.modelContext already present
  if (navigator.modelContext) {
    registerOnModelContext(navigator.modelContext);
  } else {
    // Provide a standardized WebMCP container so headless scanners and agents immediately detect tools on page load
    const fallbackContext = {
      tools: [...webMcpTools],
      registerTool(tool) {
        this.tools.push(tool);
      },
      provideContext(ctx) {
        if (ctx?.tools && Array.isArray(ctx.tools)) {
          this.tools.push(...ctx.tools);
        }
      },
      getTools() {
        return this.tools;
      },
    };

    try {
      Object.defineProperty(navigator, 'modelContext', {
        configurable: true,
        enumerable: true,
        get: () => fallbackContext,
        set: (customContext) => {
          registerOnModelContext(customContext);
        },
      });
    } catch {
      try {
        navigator.modelContext = fallbackContext;
      } catch {
        // Read-only navigator fallback
      }
    }
  }
}

// Auto-run immediately when script is imported on page load
if (typeof window !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWebMcp, { once: true });
  } else {
    initWebMcp();
  }
}
