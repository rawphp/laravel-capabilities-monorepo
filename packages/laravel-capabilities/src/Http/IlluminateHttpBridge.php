<?php

namespace Rawphp\Capabilities\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Edge adapter: Illuminate Request ↔ package HTTP DTOs (L-001 / REQ-071).
 *
 * Maps server-derived auth (middleware user, token abilities) into
 * {@see HttpRequestContext}. Never treats body/header caller or tenant as
 * credential identity (D-022).
 *
 * Unit-testable via {@see fromArray()} fixtures without a Feature suite.
 */
final class IlluminateHttpBridge
{
    /**
     * Accept Illuminate Request or array fixture stand-in.
     *
     * @param  Request|array<string, mixed>  $source
     */
    public static function toRequestContext(Request|array $source): HttpRequestContext
    {
        if ($source instanceof Request) {
            return self::fromIlluminate($source);
        }

        return self::fromArray($source);
    }

    /**
     * Map a real Illuminate request (user from middleware resolver).
     */
    public static function fromIlluminate(Request $request): HttpRequestContext
    {
        $user = $request->user();
        $authenticated = $user !== null;

        $headers = self::normalizeHeaders(self::headersFromIlluminate($request));
        $jsonBody = null;
        $rawBody = $request->getContent();
        $malformedJson = false;

        if (self::looksLikeJson($request)) {
            try {
                $decoded = json_decode($rawBody === '' ? 'null' : $rawBody, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $jsonBody = $decoded;
                } elseif ($rawBody === '' || $rawBody === 'null') {
                    $jsonBody = null;
                } else {
                    // Scalar JSON is not an object body for capabilities.
                    $jsonBody = null;
                }
            } catch (\JsonException) {
                $malformedJson = true;
                $jsonBody = null;
            }
        } else {
            $all = $request->request->all();
            if ($all !== []) {
                $jsonBody = $all;
            }
        }

        $tokenAbilities = self::tokenAbilitiesFromUser($user);
        $credential = self::buildCredential(
            authenticated: $authenticated,
            tokenAbilities: $tokenAbilities,
            oauthClientId: null,
            oauthClientType: null,
            explicitCredential: [],
        );

        return new HttpRequestContext(
            authenticated: $authenticated,
            user: is_object($user) ? $user : null,
            headers: $headers,
            jsonBody: $jsonBody,
            rawBody: $rawBody !== '' ? $rawBody : null,
            malformedJson: $malformedJson,
            query: self::stringKeyed($request->query()),
            credential: $credential,
            method: strtoupper($request->getMethod()),
            path: '/'.ltrim($request->path(), '/'),
            authKind: self::resolveAuthKind($authenticated, $tokenAbilities),
        );
    }

    /**
     * Array fixture stand-in for unit tests (no kernel).
     *
     * Supported keys: method, path, headers, query, json|jsonBody, raw_body|rawBody,
     * malformed_json|malformedJson, user, authenticated, token_abilities,
     * oauth_client_id, oauth_client_type, auth_kind|authKind, credential
     * (credential only for explicit server-side test injection — never from client body).
     *
     * Client-claimed caller/tenant in json or headers are NOT copied into credential.
     *
     * @param  array<string, mixed>  $fixture
     */
    public static function fromArray(array $fixture): HttpRequestContext
    {
        $user = $fixture['user'] ?? null;
        $user = is_object($user) ? $user : null;

        $authenticated = array_key_exists('authenticated', $fixture)
            ? (bool) $fixture['authenticated']
            : $user !== null;

        $headers = self::normalizeHeaders(
            is_array($fixture['headers'] ?? null) ? $fixture['headers'] : [],
        );

        $jsonBody = $fixture['jsonBody'] ?? $fixture['json'] ?? null;
        if ($jsonBody !== null && ! is_array($jsonBody)) {
            $jsonBody = null;
        }

        $rawBody = $fixture['rawBody'] ?? $fixture['raw_body'] ?? null;
        $rawBody = is_string($rawBody) ? $rawBody : null;

        $malformedJson = (bool) ($fixture['malformedJson'] ?? $fixture['malformed_json'] ?? false);

        $tokenAbilities = [];
        if (isset($fixture['token_abilities']) && is_array($fixture['token_abilities'])) {
            $tokenAbilities = array_values(array_filter(
                $fixture['token_abilities'],
                static fn ($a) => is_string($a),
            ));
        } elseif ($user !== null) {
            $tokenAbilities = self::tokenAbilitiesFromUser($user);
        }

        $explicitCredential = [];
        if (isset($fixture['credential']) && is_array($fixture['credential'])) {
            // Server-side test injection only — strip client identity keys if present.
            $explicitCredential = $fixture['credential'];
            unset($explicitCredential['caller'], $explicitCredential['tenant'], $explicitCredential['tenant_id']);
        }

        $credential = self::buildCredential(
            authenticated: $authenticated,
            tokenAbilities: $tokenAbilities,
            oauthClientId: is_string($fixture['oauth_client_id'] ?? null) ? $fixture['oauth_client_id'] : null,
            oauthClientType: is_string($fixture['oauth_client_type'] ?? null) ? $fixture['oauth_client_type'] : null,
            explicitCredential: $explicitCredential,
        );

        $authKind = $fixture['authKind'] ?? $fixture['auth_kind'] ?? null;
        if (! is_string($authKind) || $authKind === '') {
            $authKind = self::resolveAuthKind($authenticated, $tokenAbilities);
        }

        $query = is_array($fixture['query'] ?? null) ? self::stringKeyed($fixture['query']) : [];
        $method = strtoupper((string) ($fixture['method'] ?? 'GET'));
        $path = (string) ($fixture['path'] ?? '');

        return new HttpRequestContext(
            authenticated: $authenticated,
            user: $user,
            headers: $headers,
            jsonBody: $jsonBody,
            rawBody: $rawBody,
            malformedJson: $malformedJson,
            query: $query,
            credential: $credential,
            method: $method,
            path: $path,
            authKind: $authKind,
        );
    }

    /**
     * Package {@see HttpResponse} → Illuminate JsonResponse for the HTTP kernel.
     */
    public static function toIlluminate(HttpResponse $response): JsonResponse
    {
        return new JsonResponse(
            $response->body,
            $response->status,
            $response->headers,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function headersFromIlluminate(Request $request): array
    {
        $out = [];
        foreach ($request->headers->all() as $name => $values) {
            if (! is_string($name)) {
                continue;
            }
            $value = is_array($values) ? ($values[0] ?? '') : $values;
            $out[$name] = is_string($value) ? $value : (string) $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            if (! is_string($name) && ! is_int($name)) {
                continue;
            }
            $key = strtolower((string) $name);
            if (is_array($value)) {
                $value = $value[0] ?? '';
            }
            $out[$key] = is_string($value) ? $value : (string) $value;
        }

        return $out;
    }

    private static function looksLikeJson(Request $request): bool
    {
        $contentType = strtolower((string) $request->headers->get('content-type', ''));
        if (str_contains($contentType, 'json')) {
            return true;
        }

        $raw = $request->getContent();
        if ($raw === '') {
            return false;
        }

        $trim = ltrim($raw);

        return $trim !== '' && ($trim[0] === '{' || $trim[0] === '[');
    }

    /**
     * @return list<string>
     */
    private static function tokenAbilitiesFromUser(mixed $user): array
    {
        if (! is_object($user)) {
            return [];
        }

        if (method_exists($user, 'currentAccessToken')) {
            try {
                $token = $user->currentAccessToken();
            } catch (\Throwable) {
                $token = null;
            }
            if (is_object($token)) {
                $abilities = $token->abilities ?? null;
                if (is_array($abilities)) {
                    return array_values(array_filter($abilities, static fn ($a) => is_string($a)));
                }
            }
        }

        if (property_exists($user, 'token_abilities') && is_array($user->token_abilities)) {
            return array_values(array_filter($user->token_abilities, static fn ($a) => is_string($a)));
        }

        return [];
    }

    /**
     * Build server-derived credential facts. Never copies caller/tenant from client body.
     *
     * @param  list<string>  $tokenAbilities
     * @param  array<string, mixed>  $explicitCredential
     * @return array<string, mixed>
     */
    private static function buildCredential(
        bool $authenticated,
        array $tokenAbilities,
        ?string $oauthClientId,
        ?string $oauthClientType,
        array $explicitCredential,
    ): array {
        // Start from explicit server injection, strip identity spoof keys.
        $credential = $explicitCredential;
        unset(
            $credential['caller'],
            $credential['tenant'],
            $credential['tenant_id'],
            $credential['actor'],
        );
        // Never allow client to set server_caller via fixture spoof unless tests need it —
        // keep server_caller only if already in explicitCredential from trusted test harness.
        // (explicit path is for unit tests that inject adapter/server_caller deliberately.)

        if ($tokenAbilities !== []) {
            $credential['token_abilities'] = $tokenAbilities;
        }

        if ($oauthClientId !== null && $oauthClientId !== '') {
            $credential['oauth_client_id'] = $oauthClientId;
        }
        if ($oauthClientType !== null && $oauthClientType !== '') {
            $credential['oauth_client_type'] = $oauthClientType;
        }

        if ($authenticated || $tokenAbilities !== [] || $oauthClientId !== null || $oauthClientType !== null) {
            if (! isset($credential['adapter']) && ! isset($credential['source']) && ! isset($credential['server_caller'])) {
                $credential['adapter'] = 'http';
            }
        }

        // Unauthenticated + no credential facts → empty (fail closed).
        if (! $authenticated && $tokenAbilities === [] && $oauthClientId === null && $oauthClientType === null
            && ! isset($explicitCredential['adapter'])
            && ! isset($explicitCredential['source'])
            && ! isset($explicitCredential['server_caller'])
            && empty($explicitCredential['token_abilities'])
        ) {
            return [];
        }

        return $credential;
    }

    /**
     * @param  list<string>  $tokenAbilities
     */
    private static function resolveAuthKind(bool $authenticated, array $tokenAbilities): string
    {
        if (! $authenticated) {
            return HttpAuthGate::AUTH_NONE;
        }

        foreach ($tokenAbilities as $ability) {
            $lower = strtolower($ability);
            if ($lower === 'capabilities:cli' || str_contains($lower, 'cli')) {
                return HttpAuthGate::AUTH_CLI_TOKEN;
            }
            if ($lower === 'capabilities:api' || $lower === 'api') {
                return HttpAuthGate::AUTH_API_TOKEN;
            }
        }

        return HttpAuthGate::AUTH_USER;
    }

    /**
     * @param  array<mixed>  $query
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $query): array
    {
        $out = [];
        foreach ($query as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }
}
