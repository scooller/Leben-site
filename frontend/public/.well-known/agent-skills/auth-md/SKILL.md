# Skill: Auth.md Agent Registration

Register and authenticate autonomous AI agents with the iLeben API.

## Usage

- **Registration Endpoint**: `POST /agent/register`
- **Supported Identity Types**: `anonymous`, `identity_assertion`
- **Supported Credential Types**: `bearer_token`, `api_key`
- **Resource Server Metadata**: `/.well-known/oauth-protected-resource`
- **Authorization Server**: `/.well-known/oauth-authorization-server`

## Flow

1. Discover authorization parameters from `/.well-known/oauth-authorization-server`.
2. Post agent identity claims to `/agent/register`.
3. Receive Bearer token and supply in `Authorization: Bearer <token>` header for subsequent requests.
