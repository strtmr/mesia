/**
 * Zugriff auf die Sync-API (/api/*). Sitzung über das normale Login-Cookie.
 */

export class ApiError extends Error {}
/** Keine Verbindung (offline, DNS, Timeout). */
export class NetworkError extends ApiError {}
/** Sitzung abgelaufen: neu anmelden, die Outbox bleibt erhalten. */
export class UnauthorizedError extends ApiError {}
export class HttpError extends ApiError {
    constructor(status, message) {
        super(message);
        this.status = status;
    }
}

const TIMEOUT_MS = 15000;

export function createApi({ fetchFn = (...args) => fetch(...args), baseUrl = '' } = {}) {
    async function request(method, path, body = undefined) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), TIMEOUT_MS);
        let response;
        try {
            response = await fetchFn(baseUrl + path, {
                method,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'mealplanner',
                    ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
                },
                body: body === undefined ? undefined : JSON.stringify(body),
                signal: controller.signal,
                cache: 'no-store',
            });
        } catch (error) {
            throw new NetworkError(error?.message ?? 'Netzwerkfehler');
        } finally {
            clearTimeout(timeout);
        }

        if (response.status === 401) {
            throw new UnauthorizedError('Nicht angemeldet');
        }
        if (!response.ok) {
            throw new HttpError(response.status, `HTTP ${response.status}`);
        }

        return response.json();
    }

    return {
        me: () => request('GET', '/api/me'),
        snapshot: () => request('GET', '/api/sync/snapshot'),
        push: (deviceId, mutations) => request('POST', '/api/sync/push', { device_id: deviceId, mutations }),
        pull: (since, limit) => request('GET', `/api/sync/pull?since=${since}&limit=${limit}`),
    };
}
