import { useEffect, useContext } from 'react';
import { SiteConfigProvider, SiteConfigContext } from './contexts/SiteConfigContext';
import WebAwesomeService from './services/webAwesome';
import Home from './pages/Home';
import './App.scss';

function AppContent() {
  const { config } = useContext(SiteConfigContext) || {};

  useEffect(() => {
    // Cargar stylesheet de Web Awesome (solo una vez)
    WebAwesomeService.loadStylesheet();

    // Inicializar Web Awesome (solo una vez)
    WebAwesomeService.init().catch(err => {
      console.error('Error inicializando Web Awesome:', err);
    });
  }, []); // Aplicar tema es responsabilidad del SiteConfigContext

  return (
    <div className="app">
      <main>
        <Home />
      </main>
    </div>
  );
}

function App() {
  return (
    <SiteConfigProvider>
      <AppContent />
    </SiteConfigProvider>
  );
}

export default App;
