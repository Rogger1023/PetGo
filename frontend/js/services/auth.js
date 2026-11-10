import { apiRequest } from '../api.js';

const AUTH_ENDPOINTS = {
    register: 'register',
    login: 'login',
    logout: 'logout'
};

const TOKEN_KEY = 'petgo_auth_token';
const USER_KEY = 'petgo_auth_user';

export function getAuthToken() {
    return sessionStorage.getItem(TOKEN_KEY);
}

export function getAuthUser() {
    const stored = sessionStorage.getItem(USER_KEY);

    if (!stored) {
        return null;
    }

    try {
        return JSON.parse(stored);
    } catch {
        return null;
    }
}

export function updateStoredAuthUser(user) {
    if (!getAuthToken()) {
        throw new Error('Usuário não autenticado.');
    }

    sessionStorage.setItem(
        USER_KEY,
        JSON.stringify(user)
    );

    window.dispatchEvent(
        new CustomEvent('petgo:auth-changed')
    );
}

export function clearAuthSession() {
    sessionStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(USER_KEY);

    window.dispatchEvent(
        new CustomEvent('petgo:auth-changed')
    );
}

function extractToken(response) {
    return response?.access_token
        ?? response?.token
        ?? response?.data?.access_token
        ?? response?.data?.token
        ?? null;
}

function saveSession(response, fallbackEmail = '') {
    const token = extractToken(response);

    if (typeof token !== 'string' || token.length === 0) {
        return false;
    }

    const user = response?.user
        ?? response?.data?.user
        ?? { email: fallbackEmail };

    sessionStorage.setItem(TOKEN_KEY, token);
    sessionStorage.setItem(USER_KEY, JSON.stringify(user));

    window.dispatchEvent(new CustomEvent('petgo:auth-changed'));

    return true;
}

export async function login(email, password) {
    const response = await apiRequest(AUTH_ENDPOINTS.login, {
        method: 'POST',
        body: {
            email,
            password
        }
    });

    if (!saveSession(response, email)) {
        throw new Error(
            'Login respondido sem token. Verifique o formato retornado pela API.'
        );
    }

    return response;
}

export async function register(data) {
    const response = await apiRequest(AUTH_ENDPOINTS.register, {
        method: 'POST',
        body: data
    });

    return {
        response,
        authenticated: saveSession(response, data.email)
    };
}

export async function logout() {
    const token = getAuthToken();
    let warning = '';

    if (token) {
        try {
            await apiRequest(AUTH_ENDPOINTS.logout, {
                method: 'POST',
                token
            });
        } catch (error) {
            warning =
                'Sessão encerrada neste navegador, mas não foi possível confirmar a revogação do token no servidor.';
            console.error('Erro no logout da API:', error);
        }
    }

    clearAuthSession();

    return warning;
}
