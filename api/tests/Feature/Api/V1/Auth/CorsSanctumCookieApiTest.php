<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class CorsSanctumCookieApiTest extends TestCase
{
    use RefreshDatabase;

    private string $trustedOrigin = 'http://localhost:5173';

    private string $untrustedOrigin = 'https://evil.example';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cors.allowed_origins' => [$this->trustedOrigin, 'http://127.0.0.1:5173'],
            'cors.supports_credentials' => true,
            'sanctum.stateful' => ['localhost', 'localhost:5173', '127.0.0.1', '127.0.0.1:5173'],
            'session.driver' => 'file',
            'session.domain' => null,
            'session.secure' => false,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.partitioned' => false,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_sanctum_csrf_cookie_allows_trusted_origin_with_credentials_and_expected_cookie_attributes(): void
    {
        $response = $this->withHeader('Origin', $this->trustedOrigin)
            ->get('/sanctum/csrf-cookie');

        $response->assertNoContent();
        $this->assertCredentialedCorsAllowed($response, $this->trustedOrigin);

        $xsrfCookie = $this->requiredCookie($response, 'XSRF-TOKEN');
        $sessionCookie = $this->requiredCookie($response, config('session.cookie'));

        $this->assertSame('/', $xsrfCookie->getPath());
        $this->assertNull($xsrfCookie->getDomain());
        $this->assertFalse($xsrfCookie->isSecure());
        $this->assertFalse($xsrfCookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $xsrfCookie->getSameSite()));

        $this->assertSame('/', $sessionCookie->getPath());
        $this->assertNull($sessionCookie->getDomain());
        $this->assertFalse($sessionCookie->isSecure());
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $sessionCookie->getSameSite()));
    }

    public function test_trusted_origin_preflight_returns_specific_origin_credentials_and_allowed_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => $this->trustedOrigin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, X-XSRF-TOKEN',
        ])->options('/api/v1/auth/login');

        $response->assertNoContent();
        $this->assertCredentialedCorsAllowed($response, $this->trustedOrigin);
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('POST', (string) $response->headers->get('Access-Control-Allow-Methods'));
        $allowedHeaders = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('content-type', $allowedHeaders);
        $this->assertStringContainsString('x-xsrf-token', $allowedHeaders);
    }

    public function test_untrusted_origin_does_not_receive_credentialed_cors_headers(): void
    {
        $response = $this->withHeader('Origin', $this->untrustedOrigin)
            ->get('/sanctum/csrf-cookie');

        $response->assertNoContent();
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_csrf_login_current_user_and_logout_cookie_flow_uses_trusted_cors_headers(): void
    {
        Permission::query()->create(['name' => 'system.user.view', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->givePermissionTo('system.user.view');

        $csrfResponse = $this->withCredentials()
            ->withHeader('Origin', $this->trustedOrigin)
            ->get('/sanctum/csrf-cookie');
        $csrfResponse->assertNoContent();
        $this->assertCredentialedCorsAllowed($csrfResponse, $this->trustedOrigin);

        $xsrfCookie = $csrfResponse->getCookie('XSRF-TOKEN');
        $rawXsrfCookie = $csrfResponse->getCookie('XSRF-TOKEN', false);
        $rawSessionCookie = $csrfResponse->getCookie(config('session.cookie'), false);
        $this->assertNotNull($xsrfCookie);
        $this->assertNotNull($rawXsrfCookie);
        $this->assertNotNull($rawSessionCookie);

        $loginResponse = $this->withCredentials()
            ->withUnencryptedCookies([
                'XSRF-TOKEN' => $rawXsrfCookie->getValue(),
                config('session.cookie') => $rawSessionCookie->getValue(),
            ])
            ->withHeaders([
                'Origin' => $this->trustedOrigin,
                'X-XSRF-TOKEN' => $rawXsrfCookie->getValue(),
            ])->postJson('/api/v1/auth/login', [
                'email' => 'admin@example.com',
                'password' => 'password',
            ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonPath('data.permissions.0', 'system.user.view');
        $this->assertCredentialedCorsAllowed($loginResponse, $this->trustedOrigin);

        $rawLoggedInSessionCookie = $loginResponse->getCookie(config('session.cookie'), false);
        $this->assertNotNull($rawLoggedInSessionCookie);

        $meResponse = $this->withCredentials()
            ->withUnencryptedCookies([config('session.cookie') => $rawLoggedInSessionCookie->getValue()])
            ->withHeader('Origin', $this->trustedOrigin)
            ->getJson('/api/v1/me');

        $meResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin@example.com');
        $this->assertCredentialedCorsAllowed($meResponse, $this->trustedOrigin);

        $logoutResponse = $this->withCredentials()
            ->withUnencryptedCookies([
                'XSRF-TOKEN' => $rawXsrfCookie->getValue(),
                config('session.cookie') => $rawLoggedInSessionCookie->getValue(),
            ])
            ->withHeaders([
                'Origin' => $this->trustedOrigin,
                'X-XSRF-TOKEN' => $rawXsrfCookie->getValue(),
            ])->postJson('/api/v1/auth/logout');

        $logoutResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged out.');
        $this->assertCredentialedCorsAllowed($logoutResponse, $this->trustedOrigin);
    }

    public function test_auth_validation_and_authorization_error_responses_keep_trusted_cors_headers(): void
    {
        $this->withHeader('Origin', $this->trustedOrigin)
            ->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertHeader('Access-Control-Allow-Origin', $this->trustedOrigin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertJsonPath('code', 'unauthenticated');

        $csrfResponse = $this->withCredentials()
            ->withHeader('Origin', $this->trustedOrigin)
            ->get('/sanctum/csrf-cookie');
        $rawXsrfCookie = $csrfResponse->getCookie('XSRF-TOKEN', false);
        $rawSessionCookie = $csrfResponse->getCookie(config('session.cookie'), false);
        $this->assertNotNull($rawXsrfCookie);
        $this->assertNotNull($rawSessionCookie);

        $this->withCredentials()
            ->withUnencryptedCookies([
                'XSRF-TOKEN' => $rawXsrfCookie->getValue(),
                config('session.cookie') => $rawSessionCookie->getValue(),
            ])
            ->withHeaders([
                'Origin' => $this->trustedOrigin,
                'X-XSRF-TOKEN' => $rawXsrfCookie->getValue(),
            ])->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertHeader('Access-Control-Allow-Origin', $this->trustedOrigin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertJsonPath('code', 'validation_error');

        Sanctum::actingAs(User::factory()->create());

        $this->withHeader('Origin', $this->trustedOrigin)
            ->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertHeader('Access-Control-Allow-Origin', $this->trustedOrigin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertJsonPath('code', 'forbidden');
    }

    private function assertCredentialedCorsAllowed(mixed $response, string $origin): void
    {
        $response->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    private function requiredCookie(mixed $response, string $name): Cookie
    {
        $cookie = $response->getCookie($name, false);

        $this->assertNotNull($cookie, "Cookie [{$name}] not present on response.");

        return $cookie;
    }
}
