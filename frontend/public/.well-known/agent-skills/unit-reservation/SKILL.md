# Skill: Unit Reservation (iLeben Real Estate)

Initiate and track unit reservations for real estate developments.

## Usage

- **Reservation Endpoint**: `POST /api/v1/reservations`
- **Checkout Endpoint**: `POST /api/v1/checkout`
- **Payload**:
  - `planta_id`: Unit identifier
  - `customer_name`: Lead / buyer full name
  - `customer_email`: Lead / buyer email
  - `customer_phone`: Valid phone number
  - `payment_gateway`: `transbank` or `mercadopago`

## Confirmation

Returns reservation token, expiration window, and redirect URL for payment verification.
