import { apiRequest } from '../api.js';

const STORAGE_KEY = 'petgo_guest_cart_token';

function getStoredToken() {
    return sessionStorage.getItem(STORAGE_KEY);
}

async function createGuestCart() {
    const response = await apiRequest('guest/cart', {
        method: 'POST'
    });

    const token = response.guest_cart_token;

    if (typeof token !== 'string' || token.length === 0) {
        throw new Error('A API não retornou o token do carrinho.');
    }

    sessionStorage.setItem(STORAGE_KEY, token);

    return token;
}

async function getOrCreateGuestCartToken() {
    const existingToken = getStoredToken();

    if (existingToken) {
        return existingToken;
    }

    return createGuestCart();
}


export async function addGuestCartItem(productId, quantity) {
    const guestCartToken = await getOrCreateGuestCartToken();

    const response = await apiRequest('guest/cart/items', {
        method: 'POST',
        guestCartToken,
        body: {
            product_id: productId,
            quantity: quantity
        }
    });

    window.dispatchEvent(
        new CustomEvent('petgo:cart-updated', {
            detail: {
                cart: response.data ?? response
            }
        })
    );

    return response;
}

export async function getGuestCart() {
    const guestCartToken = getStoredToken();

    if (!guestCartToken) {
        return null;
    }

    return apiRequest('guest/cart', {
        guestCartToken
    });
    
}

export async function updateGuestCartItem(itemId, quantity) {
    const guestCartToken = getStoredToken();

    if (!guestCartToken) {
        throw new Error('Carrinho não encontrado.');
    }

    const response = await apiRequest(
        `guest/cart/items/${itemId}`,
        {
            method: 'PATCH',
            guestCartToken,
            body: {
                quantity
            }
        }
    );

    window.dispatchEvent(
        new CustomEvent('petgo:cart-updated')
    );

    return response;
}

export async function removeGuestCartItem(itemId) {
    const guestCartToken = getStoredToken();

    if (!guestCartToken) {
        throw new Error('Carrinho não encontrado.');
    }

    const response = await apiRequest(
        `guest/cart/items/${itemId}`,
        {
            method: 'DELETE',
            guestCartToken
        }
    );

    window.dispatchEvent(
        new CustomEvent('petgo:cart-updated')
    );

    return response;
}

