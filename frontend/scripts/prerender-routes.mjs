import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');
const distDir = path.join(projectRoot, 'dist');
const baseTemplatePath = path.join(distDir, 'index.html');
const siteUrl = 'https://sale.ileben.cl';

const routes = [
  {
    path: '/',
    title: 'iLeben | Inicio',
    description: 'Descubre departamentos inmobiliarios disponibles para compra en Chile.',
    canonical: `${siteUrl}/`,
    robots: 'index,follow',
  },
  {
    path: '/plantas',
    title: 'iLeben | Departamentos en venta',
    description: 'Explora unidades disponibles en venta filtrando por proyecto y comuna.',
    canonical: `${siteUrl}/f`,
    robots: 'index,follow',
  },
  {
    path: '/f',
    title: 'iLeben | Filtros',
    description: 'Explora unidades disponibles en venta filtrando por proyecto y comuna.',
    canonical: `${siteUrl}/f`,
    robots: 'index,follow',
  },
  {
    path: '/contacto',
    title: 'iLeben | Contacto',
    description: 'Comunícate con nuestro equipo para recibir asesoría sobre proyectos inmobiliarios en venta.',
    canonical: `${siteUrl}/contacto`,
    robots: 'index,follow',
  },
];

const defaultOgImage = 'https://admin.ileben.cl/curator/58e48313-5f8e-4bb2-b1da-5b33f5c0fd5f.png';
const defaultOgLogo = 'https://admin.ileben.cl/curator/5f9a1b19-a918-42a9-bd79-b80ced66f523.png';

const upsertTag = (html, pattern, replacement, fallbackInsert) => {
  if (pattern.test(html)) {
    return html.replace(pattern, replacement);
  }

  return html.replace('</head>', `${fallbackInsert}\n  </head>`);
};

const applyHeadTags = (html, route, activeOgImage = defaultOgImage, activeOgLogo = defaultOgLogo) => {
  let updated = html;

  updated = upsertTag(
    updated,
    /<title>.*?<\/title>/is,
    `<title>${route.title}</title>`,
    `<title>${route.title}</title>`
  );

  updated = upsertTag(
    updated,
    /<meta\s+name=["']description["']\s+content=["'][^"']*["']\s*\/?\s*>/i,
    `<meta name="description" content="${route.description}" />`,
    `<meta name="description" content="${route.description}" />`
  );

  updated = upsertTag(
    updated,
    /<meta\s+name=["']robots["']\s+content=["'][^"']*["']\s*\/?\s*>/i,
    `<meta name="robots" content="${route.robots}" />`,
    `<meta name="robots" content="${route.robots}" />`
  );

  updated = upsertTag(
    updated,
    /<link\s+rel=["']canonical["']\s+href=["'][^"']*["']\s*\/?\s*>/i,
    `<link rel="canonical" href="${route.canonical}" />`,
    `<link rel="canonical" href="${route.canonical}" />`
  );

  updated = upsertTag(
    updated,
    /<link\s+rel=["']alternate["']\s+hreflang=["']es-CL["'][^>]*>/i,
    `<link rel="alternate" hreflang="es-CL" href="${route.canonical}" />`,
    `<link rel="alternate" hreflang="es-CL" href="${route.canonical}" />`
  );

  updated = upsertTag(
    updated,
    /<link\s+rel=["']alternate["']\s+hreflang=["']x-default["'][^>]*>/i,
    `<link rel="alternate" hreflang="x-default" href="${route.canonical}" />`,
    `<link rel="alternate" hreflang="x-default" href="${route.canonical}" />`
  );

  updated = upsertTag(
    updated,
    /<meta\s+property=["']og:type["']\s+content=["'][^"']*["']\s*\/?\s*>/i,
    '<meta property="og:type" content="website" />',
    '<meta property="og:type" content="website" />'
  );

  updated = upsertTag(
    updated,
    /<meta\s+property=["']og:title["']\s+content=["'][^"']*["']\s*\/?\s*>/i,
    `<meta property="og:title" content="${route.title}" />`,
    `<meta property="og:title" content="${route.title}" />`
  );

  updated = upsertTag(
    updated,
    /<meta\s+property=["']og:description["']\s+content=["'][^"']*["']\s*\/?\s*>/i,
    `<meta property="og:description" content="${route.description}" />`,
    `<meta property="og:description" content="${route.description}" />`
  );

  const ogImage = route.image || activeOgImage;
  const ogLogo = route.logo || activeOgLogo;

  updated = upsertTag(
    updated,
    /<meta\s+property=["']og:image["']\s+content=["'][^"']*["']\s*\/?\s*>/i,
    `<meta property="og:image" content="${ogImage}" />`,
    `<meta property="og:image" content="${ogImage}" />`
  );

  updated = upsertTag(
    updated,
    /<meta\s+name=["']twitter:image["']\s+content=["'][^"']*["']\s*\/?\s*>/i,
    `<meta name="twitter:image" content="${ogImage}" />`,
    `<meta name="twitter:image" content="${ogImage}" />`
  );

  updated = upsertTag(
    updated,
    /<meta\s+property=["']og:logo["']\s+content=["'][^"']*["']\s*\/?\s*>/i,
    `<meta property="og:logo" content="${ogLogo}" />`,
    `<meta property="og:logo" content="${ogLogo}" />`
  );

  updated = upsertTag(
    updated,
    /<meta\s+property=["']og:url["']\s+content=["'][^"']*["']\s*\/?\s*>/i,
    `<meta property="og:url" content="${route.canonical}" />`,
    `<meta property="og:url" content="${route.canonical}" />`
  );

  updated = upsertTag(
    updated,
    /<meta\s+name=["']twitter:card["']\s+content=["'][^"']*["']\s*\/?\s*>/i,
    '<meta name="twitter:card" content="summary_large_image" />',
    '<meta name="twitter:card" content="summary_large_image" />'
  );

  updated = upsertTag(
    updated,
    /<meta\s+name=["']twitter:title["']\s+content=["'][^"']*["']\s*\/?\s*>/i,
    `<meta name="twitter:title" content="${route.title}" />`,
    `<meta name="twitter:title" content="${route.title}" />`
  );

  updated = upsertTag(
    updated,
    /<meta\s+name=["']twitter:description["']\s+content=["'][^"']*["']\s*\/?\s*>/i,
    `<meta name="twitter:description" content="${route.description}" />`,
    `<meta name="twitter:description" content="${route.description}" />`
  );

  return updated;
};

const resolveOutputPath = (routePath) => {
  if (routePath === '/') {
    return path.join(distDir, 'index.html');
  }

  const sanitized = routePath.replace(/^\/+/, '');

  return path.join(distDir, sanitized, 'index.html');
};

const run = async () => {
  const template = await readFile(baseTemplatePath, 'utf8');

  let activeOgImage = defaultOgImage;
  let activeOgLogo = defaultOgLogo;

  try {
    const apiUrl = process.env.VITE_API_URL || 'https://admin.ileben.cl';
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 3500);
    const res = await fetch(`${apiUrl.replace(/\/+$/, '')}/api/v1/site-config`, { signal: controller.signal });
    clearTimeout(timeout);
    if (res.ok) {
      const data = await res.json();
      const resolved = data?.seo?.sale_event?.og_image || data?.seo?.og_image;
      if (resolved) {
        activeOgImage = resolved;
      }
      if (data?.logo || data?.logo_sale) {
        activeOgLogo = data.logo || data.logo_sale;
      }
    }
  } catch {
    // fallback gracefully to defaultOgImage
  }

  await Promise.all(routes.map(async (route) => {
    const targetPath = resolveOutputPath(route.path);
    const targetDir = path.dirname(targetPath);

    await mkdir(targetDir, { recursive: true });
    await writeFile(targetPath, applyHeadTags(template, route, activeOgImage, activeOgLogo), 'utf8');
  }));

  process.stdout.write(`Prerendered ${routes.length} route templates.\n`);
};

run().catch((error) => {
  process.stderr.write(`[prerender-routes] ${error.message}\n`);
  process.exitCode = 1;
});
