import { apiRequest } from '../api.js';
import { getAuthToken,getAuthUser } from './auth.js';

import { getGuestCart,addGuestCartItem,updateGuestCartItem,removeGuestCartItem, clearGuestCartToken } from './guest-cart.js';

function notifyCartUpdated() {
    window.dispatchEvent(
        new CustomEvent('petgo:cart-updated')
    );
}

export function isAuthenticatedCart() {
    return Boolean(getAuthToken());
}

export async function getCart() {
    const token = getAuthToken();

    if (!token) {
        return getGuestCart();
    }

    return apiRequest('cart', {
        token
    });
}

export async function addCartItem(productId, quantity) {
    const token = getAuthToken();

    if (!token) {
        return addGuestCartItem(productId, quantity);
    }

    const response = await apiRequest('cart/items', {
        method: 'POST',
        token,
        body: {
            product_id: productId,
            quantity
        }
    });

    notifyCartUpdated();

    return response;
}

export async function updateCartItem(itemId, quantity) {
    const token = getAuthToken();

    if (!token) {
        return updateGuestCartItem(itemId, quantity);
    }

    const response = await apiRequest(
        `cart/items/${encodeURIComponent(itemId)}`,
        {
            method: 'PATCH',
            token,
            body: {
                quantity
            }
        }
    );

    notifyCartUpdated();

    return response;
}

export async function removeCartItem(itemId) {
    const token = getAuthToken();

    if (!token) {
        return removeGuestCartItem(itemId);
    }

    const response = await apiRequest(
        `cart/items/${encodeURIComponent(itemId)}`,
        {
            method: 'DELETE',
            token
        }
    );

    notifyCartUpdated();

    return response;
}


export async function transferGuestCartToAccount() {
    if (!getAuthToken()) {
        throw new Error('É necessário estar autenticado.');
    }

    const user = getAuthUser();
    const userIdentifier = user?.id ?? user?.email;

    if (!userIdentifier) {
        throw new Error('Não foi possível identificar a conta.');
    }

    const guestResponse = await getGuestCart();

    if (!guestResponse) {
        return { transferred: 0, failed: [] };
    }

    const guestCart = guestResponse.data ?? guestResponse;

    if (!Array.isArray(guestCart.items)) {
        throw new Error('Resposta inválida do carrinho de visitante.');
    }

    const items = guestCart.items;

    if (items.length === 0) {
        clearGuestCartToken();
        return { transferred: 0, failed: [] };
    }

    let transferred = 0;
    const failed = [];

    for (const item of items) {
        const productId = Number(item.product_id);
        const quantity = Number(item.quantity);
        const itemId = Number(item.id);

        const productName = item.product?.name ??
            `Produto ${item.product_id}`;

        if (
            !Number.isSafeInteger(productId) ||
            productId < 1 ||
            !Number.isSafeInteger(itemId) ||
            itemId < 1 ||
            !Number.isSafeInteger(quantity) ||
            quantity < 1
        ) {
            failed.push(`${productName}: dados inválidos.`);
            continue;
        }

        const progressKey =
            `petgo:transfer:${userIdentifier}:${guestCart.id}:${itemId}`;

        try {
            const alreadyTransferred =
                sessionStorage.getItem(progressKey) === '1';

            if (!alreadyTransferred) {
                await addCartItem(productId, quantity);

                sessionStorage.setItem(progressKey, '1');
            }

            await removeGuestCartItem(itemId);

            sessionStorage.removeItem(progressKey);

            transferred++;

        } catch (error) {
            failed.push(
                `${productName}: ${error.message}`
            );
        }
    }

    if (failed.length === 0) {
        clearGuestCartToken();
    }

    window.dispatchEvent(
        new CustomEvent('petgo:cart-updated')
    );

    return { transferred, failed };
}

