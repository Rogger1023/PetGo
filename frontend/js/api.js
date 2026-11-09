const API_BASE_URL = '/api';

/**
 * Realiza requisições HTTP à API Laravel.
 *
 * @param {string} endpoint - Caminho da API.
 * @param {object} options - Configurações da requisição.
 */
export async function apiRequest(endpoint, options = {}) {
    const {
        method = 'GET',
        body,
        token = null,
        guestCartToken = null
    } = options;

    const headers = {
        Accept: 'application/json'
    };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    if (guestCartToken) {
        headers['X-Guest-Cart-Token'] = guestCartToken;
    }

    const path = endpoint.replace(/^\/+/, '');

    const response = await fetch(
        `${API_BASE_URL}/${path}`,
        {
            method,
            headers,
            ...(body !== undefined
                ? { body: JSON.stringify(body) }
                : {})
        }
    );

    const contentType = response.headers.get('content-type') ?? '';

    const data = contentType.includes('application/json')
        ? await response.json()
        : null;

    if (!response.ok) {
        const error = new Error(
            data?.message ?? `Erro HTTP ${response.status}`
        );

        error.status = response.status;
        error.details = data?.errors ?? null;

        throw error;
    }

    return data;
}
