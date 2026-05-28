# React + Vite

This template provides a minimal setup to get React working in Vite with HMR and some ESLint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react) uses [Babel](https://babeljs.io/) (or [oxc](https://oxc.rs) when used in [rolldown-vite](https://vite.dev/guide/rolldown)) for Fast Refresh
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react-swc) uses [SWC](https://swc.rs/) for Fast Refresh

## React Compiler

The React Compiler is enabled on this template. See [this documentation](https://react.dev/learn/react-compiler) for more information.

Note: This will impact Vite dev & build performances.

## Expanding the ESLint configuration

If you are developing a production application, we recommend using TypeScript with type-aware lint rules enabled. Check out the [TS template](https://github.com/vitejs/vite/tree/main/packages/create-vite/template-react-ts) for information on how to integrate TypeScript and [`typescript-eslint`](https://typescript-eslint.io) in your project.

## 📚 Documentación de Librerías

### Animaciones
- **GSAP v3**: https://gsap.com/docs/v3/
- **GSAP para LLMs**: https://gsap.com/llms.txt
- **ScrollTrigger Plugin**: https://gsap.com/docs/v3/Plugins/ScrollTrigger
- **React + GSAP**: https://gsap.com/resources/React

### UI Components
- **Web Awesome Pro**: https://webawesome.com/docs/
- **Web Awesome Themes**: https://webawesome.com/docs/themes/

## Notas del proyecto

- El frontend usa `@web.awesome.me/webawesome-pro`.
- La disponibilidad del catálogo debe consumir los flags `is_paid` e `is_available` entregados por `/api/v1/plantas`.
- Una planta no disponible puede deberse a reserva activa, reserva completada o pago completado/autorizado asociado en backend.
- Usar el comando npm run build:dev para generar el build de desarrollo, que incluye el flag `is_available` en el catálogo.

.npmrc file at the root of your project
@web.awesome.me:registry=https://npm.cloudsmith.io/fortawesome/webawesome-pro/
//npm.cloudsmith.io/fortawesome/webawesome-pro/:_authToken=${WEBAWESOME_NPM_TOKEN}

Install Your Project
Replace [token] with your npm token in the following snippet. Then, run the command in your CLI to grab Web Awesome Pro and add it to your project dependencies.

WEBAWESOME_NPM_TOKEN="[token]" npm install "@web.awesome.me/webawesome-pro@3.7.0"

WEBAWESOME_NPM_TOKEN is an environment variable that holds your npm token. This allows you to keep your token secure and not hard-code it into your project files. Make sure to replace [token] with your actual npm token before running the command.
