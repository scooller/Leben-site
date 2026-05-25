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
    description: 'Contacto comercial para asesoría inmobiliaria.',
    canonical: `${siteUrl}/contacto`,
    robots: 'noindex,follow',
  },
  {
    path: '/pago',
    title: 'iLeben | Pago',
    description: 'Estado y resumen de pago de reserva inmobiliaria.',
    canonical: `${siteUrl}/pago`,
    robots: 'noindex,follow',
  },
];

const upsertTag = (html, pattern, replacement, fallbackInsert) => {
  if (pattern.test(html)) {
    return html.replace(pattern, replacement);
  }

  return html.replace('</head>', `${fallbackInsert}\n  </head>`);
};

const applyHeadTags = (html, route) => {
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

  await Promise.all(routes.map(async (route) => {
    const targetPath = resolveOutputPath(route.path);
    const targetDir = path.dirname(targetPath);

    await mkdir(targetDir, { recursive: true });
    await writeFile(targetPath, applyHeadTags(template, route), 'utf8');
  }));

  process.stdout.write(`Prerendered ${routes.length} route templates.\n`);
};

run().catch((error) => {
  process.stderr.write(`[prerender-routes] ${error.message}\n`);
  process.exitCode = 1;
});
