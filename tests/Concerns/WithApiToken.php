<?php

namespace Tests\Concerns;

use App\Models\User;

/**
 * Crea un PersonalAccessToken real y lo inyecta como header Bearer en todos
 * los requests del test. Esto satisface el middleware EnsureTokenOriginIsAuthorized
 * (que exige un bearer token real) y también auth:sanctum (que autentica al usuario
 * propietario del token).
 *
 * Uso en setUp():
 *   $this->setUpApiToken();                    // crea un user nuevo
 *   $this->setUpApiToken($existingUser);       // usa un user ya creado
 *
 * @mixin \Illuminate\Foundation\Testing\TestCase
 */
trait WithApiToken
{
    /**
     * Crea un token de acceso para el user dado (o uno nuevo) e inyecta
     * el header "Authorization: Bearer {token}" en todos los requests del test.
     *
     * @return string El token en texto plano (en caso de necesitarse en el test)
     */
    protected function setUpApiToken(?User $user = null): string
    {
        $user ??= User::factory()->create();

        $newToken = $user->createToken('test-token');

        $this->withToken($newToken->plainTextToken);

        return $newToken->plainTextToken;
    }
}
