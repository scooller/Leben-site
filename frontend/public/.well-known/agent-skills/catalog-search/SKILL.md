# Skill: Catalog Search (iLeben Real Estate)

Search and inspect real estate projects, communes, and available housing units (plantas) in Chile.

## Usage

- **Endpoint**: `GET /api/v1/proyectos`
- **Unit Endpoint**: `GET /api/v1/plantas`
- **Parameters**:
  - `search`: Project name, commune, or location keyword
  - `etapa`: Project development stage (`venta`, `pre_venta`, `en_blanco`, `en_verde`)
  - `dormitorios`: Number of bedrooms
  - `banos`: Number of bathrooms
  - `precio_max`: Maximum price in UF or CLP

## Responses

Returns structured JSON collections with unit dimensions, floor plans, pricing, and advisor contact links.
