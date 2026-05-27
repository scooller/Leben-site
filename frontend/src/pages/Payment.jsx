import { useEffect, useMemo, useState } from 'react';
import { useSiteConfig } from '../contexts/SiteConfigContext';
import SiteHeader from '../components/SiteHeader';
import SiteFooter from '../components/SiteFooter';
import { paymentsService } from '../services/payments';
import '../styles/payment.scss' with { type: 'css' };

const RESULT_TEXT = {
  ok: {
    title: 'Pago completado',
    variant: 'success',
    icon: 'circle-check',
    description: 'La transaccion fue confirmada correctamente.',
  },
  pending: {
    title: 'Pago pendiente',
    variant: 'warning',
    icon: 'clock',
    description: 'Estamos esperando confirmacion de la pasarela.',
  },
  cancelled: {
    title: 'Pago cancelado',
    variant: 'neutral',
    icon: 'ban',
    description: 'La transaccion fue cancelada en la pasarela.',
  },
  failed: {
    title: 'Pago rechazado',
    variant: 'danger',
    icon: 'triangle-exclamation',
    description: 'No fue posible completar el pago.',
  },
};

const EMPTY_TOKEN_MESSAGE = 'Token de estado vacío, revise el link e intente nuevamente.';

const formatMoney = (amount, currency = 'CLP') => {
  const value = Number(amount || 0);

  if (!Number.isFinite(value)) {
    return '-';
  }

  try {
    return new Intl.NumberFormat('es-CL', {
      style: 'currency',
      currency,
      maximumFractionDigits: 0,
    }).format(value);
  } catch {
    return `${value} ${currency}`;
  }
};

const formatDate = (isoDate) => {
  if (!isoDate) {
    return '-';
  }

  const date = new Date(isoDate);
  if (Number.isNaN(date.getTime())) {
    return '-';
  }

  return new Intl.DateTimeFormat('es-CL', {
    dateStyle: 'long',
    timeStyle: 'short',
  }).format(date);
};

function Payment({ onNavigate, currentPath }) {
  const { config } = useSiteConfig();
  const [loading, setLoading] = useState(false);
  const [payment, setPayment] = useState(null);
  const [errorMessage, setErrorMessage] = useState('');

  const queryParams = useMemo(() => {
    const params = new URLSearchParams(window.location.search || '');

    return {
      result: `${params.get('result') || 'pending'}`.trim().toLowerCase(),
      paymentId: `${params.get('payment_id') || ''}`.trim(),
      statusToken: `${params.get('status_token') || ''}`.trim(),
      status: `${params.get('status') || ''}`.trim(),
      gateway: `${params.get('gateway') || ''}`.trim(),
      message: `${params.get('message') || params.get('error') || ''}`.trim(),
    };
  }, []);

  const resultInfo = RESULT_TEXT[queryParams.result] || RESULT_TEXT.pending;

  useEffect(() => {
    let cancelled = false;

    const loadPaymentStatus = async () => {
      if (!queryParams.paymentId || !queryParams.statusToken) {
        setErrorMessage(queryParams.message || EMPTY_TOKEN_MESSAGE);

        return;
      }

      setLoading(true);
      setErrorMessage('');

      try {
        const response = await paymentsService.getPublicStatus(queryParams.paymentId, queryParams.statusToken);

        if (cancelled) {
          return;
        }

        setPayment(response);
      } catch (error) {
        if (cancelled) {
          return;
        }

        const fallbackMessage = queryParams.message || 'No se pudo consultar el estado actualizado del pago.';
        setErrorMessage(error?.message || fallbackMessage);
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    };

    loadPaymentStatus();

    return () => {
      cancelled = true;
    };
  }, [queryParams.message, queryParams.paymentId, queryParams.statusToken]);

  const resolvedStatus = payment?.status_label || queryParams.status || resultInfo.title;
  const resolvedGateway = payment?.gateway || queryParams.gateway || '-';
  const resolvedReference = payment?.gateway_tx_id || '-';

  return (
    <div className="payment-page">
      <SiteHeader config={config} currentPath={currentPath} onNavigate={onNavigate} />

      <section className="home-container payment-container">
        <div className="wa-stack wa-gap-m">
          <h1 className="payment-title">Resumen de tu pago</h1>

          <wa-callout variant={resultInfo.variant} class="payment-result-callout">
            <wa-icon slot="icon" name={resultInfo.icon}></wa-icon>
            <strong>{resultInfo.title}</strong>
            <div>{queryParams.message || resultInfo.description}</div>
          </wa-callout>

          <wa-card class="payment-detail-card">
            <div className="wa-stack wa-gap-m">
              <div className="wa-split payment-headline">
                <div className="wa-stack wa-gap-2xs">
                  <h2 className="wa-heading-m">Detalle de transaccion</h2>
                  <span className="wa-caption-s">
                    Actualizado: {formatDate(payment?.updated_at)}
                  </span>
                </div>
                <wa-button appearance="outlined" size="s" pill onClick={() => onNavigate?.('/contacto')}>
                  Necesito ayuda
                </wa-button>
              </div>

              {loading ? (
                <div className="wa-stack wa-gap-s">
                  <wa-skeleton effect="pulse" style={{ height: '3rem', width: '100%' }}></wa-skeleton>
                  <wa-skeleton effect="pulse" style={{ height: '3rem', width: '100%' }}></wa-skeleton>
                  <wa-skeleton effect="pulse" style={{ height: '3rem', width: '100%' }}></wa-skeleton>
                </div>
              ) : (
                <div className="wa-grid payment-grid">
                  <div className="wa-stack wa-gap-2xs payment-box">
                    <span className="wa-caption-s">Estado</span>
                    <strong>{resolvedStatus}</strong>
                  </div>

                  <div className="wa-stack wa-gap-2xs payment-box">
                    <span className="wa-caption-s">Pasarela</span>
                    <strong className="wa-text-uppercase">{resolvedGateway}</strong>
                  </div>

                  <div className="wa-stack wa-gap-2xs payment-box">
                    <span className="wa-caption-s">Referencia</span>
                    <strong>{resolvedReference}</strong>
                  </div>

                  <div className="wa-stack wa-gap-2xs payment-box payment-amount-box">
                    <span className="wa-caption-s">Monto</span>
                    <strong>{formatMoney(payment?.amount, payment?.currency)}</strong>
                  </div>
                </div>
              )}

              {errorMessage && (
                <wa-callout variant="warning">
                  <wa-icon slot="icon" name="triangle-exclamation"></wa-icon>
                  {errorMessage}
                </wa-callout>
              )}

              <wa-divider></wa-divider>

              <div className="wa-cluster wa-gap-xs payment-actions">
                <wa-button variant="brand" pill onClick={() => onNavigate?.('/plantas')}>
                  <wa-icon slot="start" name="city"></wa-icon>
                  Volver al catalogo
                </wa-button>

                <wa-button appearance="outlined" pill onClick={() => window.location.reload()}>
                  <wa-icon slot="start" name="rotate"></wa-icon>
                  Actualizar estado
                </wa-button>
              </div>
            </div>
          </wa-card>
        </div>
      </section>

      <SiteFooter config={config} onNavigate={onNavigate} />
    </div>
  );
}

export default Payment;
