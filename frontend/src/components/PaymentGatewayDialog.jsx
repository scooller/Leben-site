import { useEffect, useRef, useState } from 'react';
import { authService } from '../services/auth';
import ReservationService from '../services/reservation';
import { trackEvent } from '../utils/tagManager';

/**
 * Diálogo para seleccionar pasarela de pago y completar datos del comprador
 * Reserva la planta al abrir, libera al cerrar sin compra
 */
function PaymentGatewayDialog({
  open,
  onClose,
  plant,
  gateways,
  loading,
  checkoutError,
  isAuthenticated,
  onConfirm,
  manualPayment,
  manualProofLoading,
  onSubmitManualProof
}) {
  const dialogRef = useRef(null);
  const validationToastRef = useRef(null);
  const turnstileContainerRef = useRef(null);
  const turnstileWidgetIdRef = useRef(null);
  const [selectedGateway, setSelectedGateway] = useState('');
  const [checkoutName, setCheckoutName] = useState('');
  const [checkoutEmail, setCheckoutEmail] = useState('');
  const [checkoutPhone, setCheckoutPhone] = useState('');
  const [checkoutRut, setCheckoutRut] = useState('');
  const [touched, setTouched] = useState({
    name: false,
    email: false,
    phone: false,
    rut: false,
  });

  // Reservation state
  const [reservationToken, setReservationToken] = useState(null);
  const [reservationLoading, setReservationLoading] = useState(false);
  const [reservationError, setReservationError] = useState(null);
  const [remainingSeconds, setRemainingSeconds] = useState(0);
  const [validationToast, setValidationToast] = useState(null);
  const [manualProofFile, setManualProofFile] = useState(null);
  const [manualProofError, setManualProofError] = useState(null);
  const [manualProofSuccess, setManualProofSuccess] = useState(null);
  const [turnstileReady, setTurnstileReady] = useState(Boolean(window.turnstile));
  const [turnstileToken, setTurnstileToken] = useState('');
  const [turnstileError, setTurnstileError] = useState(null);

  const manualProofMaxBytes = 5 * 1024 * 1024;
  const manualProofAllowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'heic', 'heif'];

  const turnstileSiteKey = import.meta.env.VITE_TURNSTILE_SITE_KEY;
  const isTurnstileEnabled = Boolean(turnstileSiteKey);

  const formatRut = (value) => {
    const cleaned = String(value ?? '')
      .replace(/[^0-9kK]/g, '')
      .toUpperCase()
      .slice(0, 9);

    if (cleaned.length <= 1) {
      return cleaned;
    }

    const body = cleaned.slice(0, -1);
    const dv = cleaned.slice(-1);

    return `${body}-${dv}`;
  };

  // Validación de email robusta
  const isValidEmail = (value) => {
    const trimmed = `${value || ''}`.trim();
    if (!trimmed || trimmed.length > 100) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed);
  };

  // Validación de teléfono (solo 9 dígitos para Chile)
  const isValidPhone = (value) => {
    const digits = value.replace(/\D/g, '');
    return digits.length === 9;
  };

  // Sanitizar teléfono (solo dígitos)
  const sanitizePhone = (value) => {
    return value.replace(/\D/g, '').slice(0, 9);
  };

  // Validación de nombre
  const isValidName = (value) => {
    const trimmed = `${value || ''}`.trim();
    return trimmed.length >= 3 && trimmed.length <= 100;
  };

  // Validación de RUT chileno
  const isValidRut = (value) => {
    const formatted = formatRut(value);

    if (!/^\d{7,8}-[0-9K]$/.test(formatted)) {
      return false;
    }

    const cleaned = formatted.replace('-', '').toLowerCase();
    if (cleaned.length < 8) {
      return false;
    }

    const body = cleaned.slice(0, -1);
    const dv = cleaned.slice(-1);

    if (body.length < 7 || body.length > 8) {
      return false;
    }

    let sum = 0;
    let multiplier = 2;

    for (let i = body.length - 1; i >= 0; i -= 1) {
      sum += Number(body[i]) * multiplier;
      multiplier = multiplier === 7 ? 2 : multiplier + 1;
    }

    const remainder = 11 - (sum % 11);
    const expectedDv = remainder === 11 ? '0' : remainder === 10 ? 'k' : `${remainder}`;

    return dv === expectedDv;
  };

  const isEmailValid = checkoutEmail ? isValidEmail(checkoutEmail) : false;
  const isPhoneValid = checkoutPhone ? isValidPhone(checkoutPhone) : false;
  const isRutValid = checkoutRut ? isValidRut(checkoutRut) : false;
  const isNameValid = isValidName(checkoutName);

  const setFieldTouched = (field) => {
    setTouched((previous) => ({ ...previous, [field]: true }));
  };

  const handleRutChange = (event) => {
    setFieldTouched('rut');
    setCheckoutRut(formatRut(event.target.value));
  };

  const validationMessages = [];

  if (!isNameValid) {
    validationMessages.push('Ingresa tu nombre completo (minimo 3 caracteres).');
  }

  if (!isEmailValid) {
    validationMessages.push('Correo electronico invalido.');
  }

  if (!isPhoneValid) {
    validationMessages.push('Telefono invalido (8 a 15 digitos).');
  }

  if (!isRutValid) {
    validationMessages.push('RUT invalido. Usa formato 12345678-9 (sin puntos).');
  }

  if (!selectedGateway) {
    validationMessages.push('Selecciona una pasarela de pago.');
  }

  if (isTurnstileEnabled && !turnstileToken) {
    validationMessages.push('Completa la verificacion de seguridad antes de continuar.');
  }

  const isCheckoutReady = Boolean(
    isAuthenticated
    && selectedGateway
    && checkoutName
    && checkoutEmail
    && isEmailValid
    && checkoutPhone
    && isPhoneValid
    && checkoutRut
    && isRutValid
    && reservationToken
    && !reservationError
    && (!isTurnstileEnabled || turnstileToken)
  );

  const reservaExigidaPeso = plant?.proyecto?.valor_reserva_exigido_defecto_peso ?? null;
  const reservaAsNumber = reservaExigidaPeso !== null && reservaExigidaPeso !== undefined
    ? Number(reservaExigidaPeso)
    : null;
  const formattedReserva = Number.isFinite(reservaAsNumber)
    ? `$ ${reservaAsNumber.toLocaleString('es-CL', { maximumFractionDigits: 0 })}`
    : 'Por confirmar';
  const hasActiveCountdown = remainingSeconds > 0;

  const formatCountdown = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${String(secs).padStart(2, '0')}`;
  };

  const formatDateTime = (value) => {
    if (!value) {
      return 'Sin limite definido';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
      return value;
    }

    return new Intl.DateTimeFormat('es-CL', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(date);
  };

  // Sincronizar estado abierto con el diálogo
  useEffect(() => {
    if (dialogRef.current) {
      dialogRef.current.open = open;
    }
  }, [open]);

  useEffect(() => {
    if (!isTurnstileEnabled || window.turnstile) {
      return;
    }

    const existingScript = document.querySelector('script[data-turnstile-script="true"]');

    if (existingScript) {
      const handleScriptLoad = () => setTurnstileReady(true);
      const handleScriptError = () => setTurnstileError('No se pudo cargar Turnstile. Intenta nuevamente.');

      existingScript.addEventListener('load', handleScriptLoad);
      existingScript.addEventListener('error', handleScriptError);

      return () => {
        existingScript.removeEventListener('load', handleScriptLoad);
        existingScript.removeEventListener('error', handleScriptError);
      };
    }

    const script = document.createElement('script');
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
    script.async = true;
    script.defer = true;
    script.setAttribute('data-turnstile-script', 'true');

    const handleScriptLoad = () => {
      setTurnstileReady(true);
      setTurnstileError(null);
    };

    const handleScriptError = () => {
      setTurnstileError('No se pudo cargar Turnstile. Intenta nuevamente.');
    };

    script.addEventListener('load', handleScriptLoad);
    script.addEventListener('error', handleScriptError);
    document.head.appendChild(script);

    return () => {
      script.removeEventListener('load', handleScriptLoad);
      script.removeEventListener('error', handleScriptError);
    };
  }, [isTurnstileEnabled]);

  useEffect(() => {
    if (!open || manualPayment || !isTurnstileEnabled || !turnstileReady || !turnstileContainerRef.current || !window.turnstile) {
      return;
    }

    setTurnstileToken('');
    setTurnstileError(null);

    if (turnstileWidgetIdRef.current) {
      window.turnstile.reset(turnstileWidgetIdRef.current);
      return;
    }

    turnstileWidgetIdRef.current = window.turnstile.render(turnstileContainerRef.current, {
      sitekey: turnstileSiteKey,
      callback: (token) => {
        setTurnstileToken(token);
        setTurnstileError(null);
      },
      'expired-callback': () => {
        setTurnstileToken('');
        setTurnstileError('La verificacion expiro. Completa Turnstile nuevamente.');
      },
      'error-callback': () => {
        setTurnstileToken('');
        setTurnstileError('No se pudo validar Turnstile. Intenta nuevamente.');
      },
    });
  }, [open, manualPayment, isTurnstileEnabled, turnstileReady, turnstileSiteKey]);

  useEffect(() => {
    if (open || !isTurnstileEnabled) {
      return;
    }

    setTurnstileToken('');
    setTurnstileError(null);

    if (window.turnstile && turnstileWidgetIdRef.current) {
      window.turnstile.reset(turnstileWidgetIdRef.current);
    }
  }, [open, isTurnstileEnabled]);

  useEffect(() => {
    if (!validationToast || !validationToastRef.current || typeof validationToastRef.current.create !== 'function') {
      return;
    }

    validationToastRef.current.create(validationToast.message, {
      variant: validationToast.variant || 'warning',
      icon: 'triangle-exclamation',
      duration: 3200,
    });

    setValidationToast(null);
  }, [validationToast]);

  // Reserve plant when dialog opens
  useEffect(() => {
    if (!open || !plant || !isAuthenticated) {
      return;
    }

    let cancelled = false;
    const doReserve = async () => {
      setReservationLoading(true);
      setReservationError(null);

      trackEvent('reservation_attempt', {
        plant_id: plant.id,
        plant_name: plant.nombre || plant.name || null,
        project_name: plant.proyectoNombre || plant.proyecto?.name || null,
      });

      try {
        const reservation = await ReservationService.reserve(plant.id);
        if (!cancelled) {
          setReservationToken(reservation.session_token);
          setRemainingSeconds(reservation.remaining_seconds);

          trackEvent('reservation_success', {
            plant_id: plant.id,
            plant_name: plant.nombre || plant.name || null,
            project_name: plant.proyectoNombre || plant.proyecto?.name || null,
            remaining_seconds: reservation.remaining_seconds || 0,
          });
        }
      } catch (err) {
        if (!cancelled) {
          setReservationError(err.userMessage || 'No se pudo reservar esta planta.');

          trackEvent('reservation_error', {
            plant_id: plant.id,
            plant_name: plant.nombre || plant.name || null,
            project_name: plant.proyectoNombre || plant.proyecto?.name || null,
            error_message: err?.userMessage || err?.message || 'No se pudo reservar esta planta.',
          });
        }
      } finally {
        if (!cancelled) {
          setReservationLoading(false);
        }
      }
    };

    doReserve();
    return () => { cancelled = true; };
  }, [open, plant, isAuthenticated]);

  // Countdown timer
  useEffect(() => {
    if (!hasActiveCountdown || !reservationToken) {
      return;
    }

    const interval = setInterval(() => {
      setRemainingSeconds((prev) => {
        if (prev <= 1) {
          clearInterval(interval);
          setReservationError('Tu reserva ha expirado. Cierra este dialogo e intenta nuevamente.');
          setReservationToken(null);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(interval);
  }, [hasActiveCountdown, remainingSeconds, reservationToken]);

  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) {
      return;
    }

    const handleHide = () => {
      // Release reservation only when user closes the dialog without being in checkout processing.
      if (reservationToken && !manualPayment && !loading) {
        ReservationService.release(reservationToken);
        setReservationToken(null);
      }
      setReservationError(null);
      setRemainingSeconds(0);
      onClose();
    };

    dialog.addEventListener('wa-hide', handleHide);

    return () => {
      dialog.removeEventListener('wa-hide', handleHide);
    };
  }, [manualPayment, onClose, reservationToken]);

  // Cargar datos del usuario cuando se abre el diálogo
  useEffect(() => {
    if (open && plant) {
      const currentUser = authService.getCurrentUser();
      setSelectedGateway(gateways.length > 0 ? gateways[0].id : '');
      setCheckoutName(currentUser?.name || '');
      setCheckoutEmail(currentUser?.email || '');
      setCheckoutPhone(currentUser?.phone || '');
      setCheckoutRut(formatRut(currentUser?.rut || ''));
      setTouched({
        name: false,
        email: false,
        phone: false,
        rut: false,
      });
      setValidationToast(null);
      setManualProofFile(null);
      setManualProofError(null);
      setManualProofSuccess(null);
      setTurnstileToken('');
      setTurnstileError(null);
    }
  }, [open, plant, gateways]);

  const handleManualProofUpload = async () => {
    if (!manualPayment?.payment_id) {
      setManualProofError('No se encontro la referencia del pago manual.');
      return;
    }

    if (!manualProofFile) {
      setManualProofError('Selecciona un archivo antes de enviar el comprobante.');
      return;
    }

    try {
      setManualProofError(null);
      const response = await onSubmitManualProof({
        paymentId: manualPayment.payment_id,
        proofFile: manualProofFile,
      });

      setManualProofSuccess(response?.message || 'Comprobante enviado correctamente.');
    } catch (error) {
      setManualProofError(error.userMessage || 'No se pudo enviar el comprobante.');
    }
  };

  const handleConfirm = () => {
    if (!isCheckoutReady) {
      setTouched({
        name: true,
        email: true,
        phone: true,
        rut: true,
      });

      setValidationToast({
        variant: 'warning',
        message: validationMessages[0] || 'Completa los campos requeridos antes de continuar.',
      });

      return;
    }

    onConfirm({
      plantId: plant?.id,
      gateway: selectedGateway,
      sessionToken: reservationToken,
      turnstileToken,
      userData: {
        name: checkoutName,
        email: checkoutEmail,
        phone: checkoutPhone,
        rut: formatRut(checkoutRut),
      },
    });
  };

  const manualInstructions = typeof manualPayment?.instructions === 'string'
    ? manualPayment.instructions.trim()
    : '';
  const manualPaymentLink = typeof manualPayment?.payment_link === 'string'
    ? manualPayment.payment_link.trim()
    : '';
  const manualBankAccounts = Array.isArray(manualPayment?.bank_accounts)
    ? manualPayment.bank_accounts.filter((account) => {
      if (!account || typeof account !== 'object') {
        return false;
      }

      return ['bank', 'account_type', 'account_number', 'account_holder', 'rut']
        .some((key) => {
          const value = account[key];

          return typeof value === 'string' ? value.trim().length > 0 : Boolean(value);
        });
    })
    : [];

  return (
    <wa-dialog
      ref={dialogRef}
      className="payment-gateway-dialog"
      label={manualPayment ? 'Pago Manual' : 'Seleccionar Pasarela de Pago'}
      style={{ '--width': '500px' }}
      light-dismiss
    >
      <wa-toast ref={validationToastRef} placement="top-end"></wa-toast>

      <div className="gateway-selection">
        {checkoutError && (
          <wa-callout variant="danger" style={{ marginBottom: '0.75rem' }}>
            <wa-icon name="triangle-exclamation" slot="icon"></wa-icon>
            <strong>{checkoutError.title || 'Error en el pago'}</strong>
            <div>{checkoutError.userMessage || checkoutError.message || 'No se pudo iniciar el checkout.'}</div>
          </wa-callout>
        )}

        {manualPayment ? (
          <div className="wa-stack wa-gap-m">
            <wa-callout variant="warning">
              <wa-icon name="building-columns" slot="icon"></wa-icon>
              Tu planta permanece reservada hasta <strong>{formatDateTime(manualPayment.expires_at)}</strong>.
            </wa-callout>

            <div className="wa-split wa-align-items-center">
              <strong>Referencia unica</strong>
              <span>{manualPayment.reference}</span>
            </div>

            <div className="wa-split wa-align-items-center">
              <strong>Monto</strong>
              <span>
                {new Intl.NumberFormat('es-CL', {
                  style: 'currency',
                  currency: manualPayment.currency || 'CLP',
                  maximumFractionDigits: 0,
                }).format(Number(manualPayment.amount || 0))}
              </span>
            </div>

            {manualInstructions && (
              <div className="wa-stack wa-gap-2xs">
                <strong>Instrucciones</strong>
                <p>{manualInstructions}</p>
              </div>
            )}

            {manualPaymentLink && (
              <div className="wa-stack wa-gap-2xs">
                <strong>Link de pago</strong>
                <a href={manualPaymentLink} target="_blank" rel="noreferrer noopener">
                  {manualPaymentLink}
                </a>
              </div>
            )}

            {manualBankAccounts.length > 0 && (
              <div className="wa-stack wa-gap-s">
                <strong>Datos bancarios</strong>
                {manualBankAccounts.map((account, index) => (
                  <wa-card key={`${account.bank || 'bank'}-${index}`} appearance="outlined">
                    <div className="wa-stack wa-gap-2xs" style={{ padding: '1rem' }}>
                      {account.bank && <span><strong>Banco:</strong> {account.bank}</span>}
                      {account.account_type && <span><strong>Tipo:</strong> {account.account_type}</span>}
                      {account.account_number && <span><strong>Numero:</strong> {account.account_number}</span>}
                      {account.account_holder && <span><strong>Titular:</strong> {account.account_holder}</span>}
                      {account.rut && <span><strong>RUT:</strong> {account.rut}</span>}
                    </div>
                  </wa-card>
                ))}
              </div>
            )}

            {manualPayment.requires_proof && (
              <div className="wa-stack wa-gap-s">
                <strong>Enviar comprobante</strong>
                <input
                  type="file"
                  accept=".jpg,.jpeg,.png,.pdf,.heic,.heif"
                  onChange={(event) => {
                    const selectedFile = event.target.files?.[0] || null;

                    if (!selectedFile) {
                      setManualProofFile(null);
                      setManualProofError(null);

                      return;
                    }

                    const extension = selectedFile.name.split('.').pop()?.toLowerCase() || '';

                    if (!manualProofAllowedExtensions.includes(extension)) {
                      setManualProofFile(null);
                      setManualProofError('Formato no permitido. Usa JPG, PNG, HEIC, HEIF o PDF.');
                      event.target.value = '';

                      return;
                    }

                    if (selectedFile.size > manualProofMaxBytes) {
                      setManualProofFile(null);
                      setManualProofError('El archivo supera el máximo permitido de 5 MB.');
                      event.target.value = '';

                      return;
                    }

                    setManualProofFile(selectedFile);
                    setManualProofError(null);
                  }}
                />
                <small className="wa-caption-s">Formatos permitidos: JPG, PNG, HEIC, HEIF o PDF. Máximo 5 MB.</small>
              </div>
            )}

            {manualProofError && (
              <wa-callout variant="danger">
                <wa-icon name="circle-exclamation" slot="icon"></wa-icon>
                {manualProofError}
              </wa-callout>
            )}

            {manualProofSuccess && (
              <wa-callout variant="success">
                <wa-icon name="circle-check" slot="icon"></wa-icon>
                {manualProofSuccess}
              </wa-callout>
            )}
          </div>
        ) : (
          <>
            <div className="checkout-user-fields wa-stack wa-gap-m">
              {reservationLoading && (
                <wa-callout variant="info">
                  <wa-spinner slot="icon"></wa-spinner>
                  Reservando planta...
                </wa-callout>
              )}

              {reservationError && (
                <wa-callout variant="danger">
                  <wa-icon name="circle-exclamation" slot="icon"></wa-icon>
                  {reservationError}
                </wa-callout>
              )}

              {reservationToken && remainingSeconds > 0 && (
                <wa-callout variant="warning">
                  <wa-icon name="clock" slot="icon"></wa-icon>
                  Planta reservada por <strong>{formatCountdown(remainingSeconds)}</strong>
                </wa-callout>
              )}

              {!isAuthenticated && (
                <wa-callout variant="info">
                  <wa-icon name="address-card" slot="icon"></wa-icon>
                  Rellena todos los campos para continuar al pago.
                </wa-callout>
              )}

              <wa-card appearance="outlined" className="checkout-summary-card">
                <div className="wa-stack wa-gap-s checkout-summary-content">
                  <div className="checkout-section-title">
                    <wa-icon name="file-invoice-dollar"></wa-icon>
                    <strong>Resumen de la reserva</strong>
                  </div>

                  <div className="wa-split wa-align-items-center">
                    <strong>Proyecto</strong>
                    <span>{plant?.proyecto?.name || plant?.proyectoNombre || 'Sin proyecto'}</span>
                  </div>
                  <div className="wa-split wa-align-items-center">
                    <strong>Planta</strong>
                    <span>{plant?.nombre || plant?.name || 'Sin nombre'}</span>
                  </div>
                  <div className="wa-split wa-align-items-center">
                    <strong>Precio pie</strong>
                    <wa-tag variant="success">{formattedReserva}</wa-tag>
                  </div>
                </div>
              </wa-card>

              <wa-card appearance="outlined" className="checkout-buyer-card">
                <div className="wa-stack wa-gap-s checkout-buyer-content">
                  <div className="checkout-section-title">
                    <wa-icon name="id-card"></wa-icon>
                    <strong>Datos del comprador</strong>
                  </div>
                    <div className="wa-stack wa-gap-3xs">
                      <wa-input
                          label="Nombre completo"
                          value={checkoutName}
                          onChange={(e) => {
                          setFieldTouched('name');
                          setCheckoutName(e.target.value.slice(0, 100));
                          }}
                          onBlur={() => setFieldTouched('name')}
                          minlength="3"
                          maxlength="100"
                          required
                          placeholder="Juan Pérez"
                      ></wa-input>
                      {checkoutName && (
                        <small className="wa-caption-s validation-hint info">{checkoutName.length}/100 caracteres</small>
                      )}
                      {touched.name && !isNameValid && (
                        <small className="wa-caption-s validation-hint error">Ingresa tu nombre (mínimo 3 caracteres, máximo 100).</small>
                      )}
                    </div>

                    <div className="wa-stack wa-gap-3xs">
                      <wa-input
                          type="email"
                          label="Correo electronico"
                          value={checkoutEmail}
                          onChange={(e) => {
                          setFieldTouched('email');
                          setCheckoutEmail(e.target.value.slice(0, 100));
                          }}
                          onBlur={() => setFieldTouched('email')}
                          maxlength="100"
                          autocomplete="email"
                          required
                          placeholder="ejemplo@correo.com"
                      ></wa-input>
                      {checkoutEmail && (
                        <small className="wa-caption-s validation-hint info">{checkoutEmail.length}/100 caracteres</small>
                      )}
                      {touched.email && !isEmailValid && (
                        <small className="wa-caption-s validation-hint error">Correo electronico invalido (ej: ejemplo@correo.com).</small>
                      )}
                    </div>

                    <div className="wa-stack wa-gap-3xs">
                      <wa-input
                          type="tel"
                          label="Telefono"
                          placeholder="912345678"
                          value={checkoutPhone}
                          onChange={(e) => {
                          setFieldTouched('phone');
                          setCheckoutPhone(sanitizePhone(e.target.value));
                          }}
                          onBlur={() => setFieldTouched('phone')}
                          maxlength="9"
                          autocomplete="tel"
                          required
                      >
                          <div slot="start" className="wa-input-prefix">+56</div>
                      </wa-input>
                      {checkoutPhone && (
                        <small className="wa-caption-s validation-hint info">{checkoutPhone.length}/9 digitos</small>
                      )}
                      {touched.phone && !isPhoneValid && (
                        <small className="wa-caption-s validation-hint error">Ingresa 9 digitos validos (ej: 912345678).</small>
                      )}
                    </div>

                    <div className="wa-stack wa-gap-3xs">
                      <wa-input
                          label="RUT"
                          placeholder="12345678-9"
                          value={checkoutRut}
                          onChange={handleRutChange}
                          onBlur={() => setFieldTouched('rut')}
                          pattern="^[0-9]{7,8}-[0-9K]$"
                          maxlength="10"
                          required
                      ></wa-input>
                      <small className={`wa-caption-s validation-hint ${!touched.rut || isRutValid || !checkoutRut ? 'info' : 'error'}`}>
                        {!touched.rut || !checkoutRut ? 'Formato: 12345678-9 (sin puntos)' : `${checkoutRut.length}/10 caracteres`}
                      </small>
                      {touched.rut && !isRutValid && checkoutRut && (
                        <small className="wa-caption-s validation-hint error">RUT invalido. Revisa el digito verificador.</small>
                      )}
                    </div>

                    {isTurnstileEnabled && (
                      <div className="turnstile-wrapper wa-stack wa-gap-2xs">
                        <strong>Verificacion de seguridad</strong>
                        <div ref={turnstileContainerRef}></div>
                        {!turnstileReady && (
                          <small className="wa-caption-s validation-hint info">Cargando verificacion Turnstile...</small>
                        )}
                        {turnstileError && (
                          <small className="wa-caption-s validation-hint error">{turnstileError}</small>
                        )}
                      </div>
                    )}
                </div>
              </wa-card>
            </div>

            <wa-divider></wa-divider>

            <div className="checkout-section-title wa-mb-xs">
              <wa-icon name="credit-card"></wa-icon>
              <strong>Selecciona como deseas realizar el pago</strong>
            </div>

            {gateways.length > 0 ? (
              <wa-radio-group
                className="gateway-radio-group"
                value={selectedGateway}
                onChange={(e) => setSelectedGateway(e.target.value)}
              >
                {gateways.map((gateway) => (
                  <wa-radio key={gateway.id} value={gateway.id}>
                    <div className="gateway-option-content">
                      <strong>{gateway.name}</strong>
                      <br />
                      <small>{gateway.description}</small>
                    </div>
                  </wa-radio>
                ))}
              </wa-radio-group>
            ) : (
              <wa-callout variant="warning">
                No hay forma de pago por el momento.
              </wa-callout>
            )}
          </>
        )}
      </div>

      <wa-button
        slot="footer"
        variant="neutral"
        data-dialog="close"
        disabled={loading}
      >
        Cancelar
      </wa-button>
      <wa-button
        slot="footer"
        variant="brand"
        onClick={manualPayment ? handleManualProofUpload : handleConfirm}
        disabled={manualPayment
          ? manualProofLoading || (manualPayment.requires_proof && !manualProofFile)
          : (loading || !isCheckoutReady || reservationLoading)}
        {...((manualPayment ? manualProofLoading : (loading || reservationLoading)) && { loading: true })}
      >
        {manualPayment
          ? (manualProofLoading ? 'Enviando comprobante...' : <><wa-icon name="upload"></wa-icon> Enviar Comprobante</>)
          : (loading ? 'Procesando...' : <><wa-icon name="money-bill-wave"></wa-icon> {selectedGateway === 'manual' ? 'Generar Referencia de Pago' : 'Continuar al Pago'}</>)}
      </wa-button>
    </wa-dialog>
  );
}

export default PaymentGatewayDialog;
