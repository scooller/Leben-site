import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');
const distDir = path.join(projectRoot, 'dist');

const checks = [
  {
    file: path.join(distDir, 'index.html'),
    canonical: 'https://sale.ileben.cl/',
    robots: 'index,follow',
  },
  {
    file: path.join(distDir, 'plantas', 'index.html'),
    canonical: 'https://sale.ileben.cl/f',
    robots: 'index,follow',
  },
  {
    file: path.join(distDir, 'f', 'index.html'),
    canonical: 'https://sale.ileben.cl/f',
    robots: 'index,follow',
  },
  {
    file: path.join(distDir, 'contacto', 'index.html'),
    canonical: 'https://sale.ileben.cl/contacto',
    robots: 'noindex,follow',
  },
  {
    file: path.join(distDir, 'pago', 'index.html'),
    canonical: 'https://sale.ileben.cl/pago',
    robots: 'noindex,follow',
  },
];

const fail = (message) => {
  process.stderr.write(`${message}\n`);
  process.exitCode = 1;
};

const run = async () => {
  await Promise.all(checks.map(async (check) => {
    const content = await readFile(check.file, 'utf8');

    if (!content.includes(`<link rel="canonical" href="${check.canonical}" />`)) {
      fail(`[validate-seo] Canonical mismatch in ${check.file}`);
    }

    if (!content.includes(`<meta name="robots" content="${check.robots}" />`)) {
      fail(`[validate-seo] Robots mismatch in ${check.file}`);
    }
  }));

  const appSource = await readFile(path.join(projectRoot, 'src', 'App.jsx'), 'utf8');
  const homeSource = await readFile(path.join(projectRoot, 'src', 'pages', 'Home.jsx'), 'utf8');

  if (!appSource.includes("setStructuredData('organization'")) {
    fail('[validate-seo] Missing organization structured data hook in App.jsx');
  }

  if (!homeSource.includes("setStructuredData('product'")) {
    fail('[validate-seo] Missing product structured data hook in Home.jsx');
  }

  if (process.exitCode !== 1) {
    process.stdout.write('SEO validation checks passed.\n');
  }
};

run().catch((error) => {
  fail(`[validate-seo] ${error.message}`);
});
