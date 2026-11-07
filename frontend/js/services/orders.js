import { apiRequest } from '../api.js';
import { getAuthToken, getAuthUser } from './auth.js';

function requireToken() {
    const token = getAuthToken();

    if (!token) {
        throw new Error('Faça login para continuar.');
    }

    return token;
}

export async function getAddresses() {
    return apiRequest('addresses', {
        token: requireToken()
    });
}

export async function createAddress(data) {
    return apiRequest('addresses', {
        method: 'POST',
        token: requireToken(),
        body: data
    });
}

export async function getOrders(page = 1) {
    return apiRequest(`orders?page=${page}`, {
        token: requireToken()
    });
}

export async function getOrder(id) {
    return apiRequest(`orders/${encodeURIComponent(id)}`, {
        token: requireToken()
    });
}

export function getCheckoutKey() {
    const user = getAuthUser();

    const identifier = String(user?.id ?? user?.email ?? 'account');
    const storageKey = `petgo_checkout_key:${identifier}`;

    let key = sessionStorage.getItem(storageKey);

    if (!key) {
        key = crypto.randomUUID();
        sessionStorage.setItem(storageKey, key);
    }

    return key;
}

export function clearCheckoutKey() {
    const user = getAuthUser();
    const identifier = String(user?.id ?? user?.email ?? 'account');

    sessionStorage.removeItem(
        `petgo_checkout_key:${identifier}`
    );
}

export async function createOrder(addressId) {
    return apiRequest('checkout', {
        method: 'POST',
        token: requireToken(),
        body: {
            address_id: Number(addressId),
            checkout_key: getCheckoutKey()
        }
    });
}

export function formatMoney(cents) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(Number(cents ?? 0) / 100);
}

export function unwrapResource(response) {
    return response?.data ?? response;
}
