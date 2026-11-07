import { renderLayout } from './components/layout.js';
import { getCart } from './services/cart.js';
import { getAuthToken } from './services/auth.js';

import {
    getAddresses,
    createAddress,
    createOrder,
    clearCheckoutKey,
    formatMoney,
    unwrapResource
} from './services/orders.js';

renderLayout();

const itemsContainer = document.querySelector('#checkout-items');
const addressSelect = document.querySelector('#checkout-address');
const addressStatus = document.querySelector('#address-status');
const subtotalElement = document.querySelector('#checkout-subtotal');
const checkoutStatus = document.querySelector('#checkout-status');
const checkoutForm = document.querySelector('#checkout-form');
const confirmButton = document.querySelector('#confirm-order-button');

const addressForm = document.querySelector('#address-form');
const toggleAddressButton = document.querySelector('#toggle-address-form');
const saveAddressButton = document.querySelector('#save-address-button');

let cartHasItems = false;
let addressesReady = false;
let savingAddress = false;
let submittingOrder = false;

function getErrorMessage(error) {
    const details = error?.details?.errors ?? error?.details;

    if (details && typeof details === 'object') {
        const firstError = Object.values(details)
            .flat()
            .find((value) => typeof value === 'string');

        if (firstError) {
            return firstError;
        }
    }

    return error?.message || 'Ocorreu um erro inesperado.';
}

function showStatus(message, isError = false) {
    checkoutStatus.textContent = message;
    checkoutStatus.className = isError
        ? 'checkout-error'
        : 'checkout-success';
}

function refreshButton() {
    const addressId = Number(addressSelect.value);

    const validAddress =
        addressesReady &&
        Number.isSafeInteger(addressId) &&
        addressId > 0;

    confirmButton.disabled =
        !cartHasItems ||
        !validAddress ||
        savingAddress ||
        submittingOrder;
}

function toggleAddressForm(open) {
    addressForm.hidden = !open;

    toggleAddressButton.textContent = open
        ? 'Cancelar cadastro'
        : '+ Cadastrar novo endereço';

    toggleAddressButton.setAttribute(
        'aria-expanded',
        String(open)
    );
}

function renderItems(cart) {
    const items = Array.isArray(cart?.items)
        ? cart.items
        : [];

    itemsContainer.replaceChildren();

    cartHasItems = items.length > 0;

    if (!cartHasItems) {
        itemsContainer.textContent =
            'Seu carrinho está vazio. Adicione produtos antes de finalizar.';

        subtotalElement.textContent = formatMoney(0);
        refreshButton();
        return;
    }

    let subtotal = 0;

    for (const item of items) {
        const quantity = Number(item.quantity ?? 0);

        const unitPrice = Number(
            item.unit_price_cents ??
            item.product?.price_cents ??
            0
        );

        const lineTotal = quantity * unitPrice;

        subtotal += lineTotal;

        const row = document.createElement('div');
        row.className = 'checkout-item';

        const name = document.createElement('span');
        name.textContent =
            `${item.product?.name ?? 'Produto'} × ${quantity}`;

        const price = document.createElement('strong');
        price.textContent = formatMoney(lineTotal);

        row.append(name, price);
        itemsContainer.appendChild(row);
    }

    subtotalElement.textContent = formatMoney(subtotal);

    refreshButton();
}

function describeAddress(address) {
    const parts = [
        address.street,
        address.number,
        address.neighborhood,
        address.city,
        address.state
    ].filter(Boolean);

    return parts.length > 0
        ? parts.join(', ')
        : `Endereço #${address.id}`;
}

async function loadAddresses(preferredAddressId = null) {
    try {
        const response = await getAddresses();
        const data = unwrapResource(response);

        const addresses = Array.isArray(data)
            ? data
            : Array.isArray(data?.addresses)
                ? data.addresses
                : [];

        const previousId = preferredAddressId ??
            addressSelect.value;

        addressSelect.replaceChildren();

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Selecione um endereço';

        addressSelect.appendChild(placeholder);

        for (const address of addresses) {
            const option = document.createElement('option');

            option.value = String(address.id);
            option.textContent = describeAddress(address);

            addressSelect.appendChild(option);
        }

        addressesReady = addresses.length > 0;
        addressSelect.disabled = !addressesReady;

        if (addressesReady) {
            const selected =
                addresses.find(
                    (address) =>
                        String(address.id) === String(previousId)
                ) ??
                addresses.find((address) => address.is_default) ??
                addresses[0];

            addressSelect.value = String(selected.id);

            addressStatus.textContent =
                'Endereço selecionado para entrega.';
        } else {
            addressStatus.textContent =
                'Nenhum endereço cadastrado. Cadastre um para continuar.';

            toggleAddressForm(true);
        }

        refreshButton();

        return true;

    } catch (error) {
        addressesReady = false;
        addressSelect.disabled = true;

        addressStatus.textContent =
            `Não foi possível carregar os endereços: ${getErrorMessage(error)}`;

        refreshButton();

        return false;
    }
}

function getAddressData() {
    const formData = new FormData(addressForm);

    const data = {
        recipient_name: String(
            formData.get('recipient_name') ?? ''
        ).trim(),

        phone: String(
            formData.get('phone') ?? ''
        ).trim(),

        postal_code: String(
            formData.get('postal_code') ?? ''
        ).trim(),

        street: String(
            formData.get('street') ?? ''
        ).trim(),

        number: String(
            formData.get('number') ?? ''
        ).trim(),

        neighborhood: String(
            formData.get('neighborhood') ?? ''
        ).trim(),

        city: String(
            formData.get('city') ?? ''
        ).trim(),

        state: String(
            formData.get('state') ?? ''
        ).trim().toUpperCase(),

        is_default: formData.has('is_default')
    };

    const complement = String(
        formData.get('complement') ?? ''
    ).trim();

    const reference = String(
        formData.get('reference') ?? ''
    ).trim();

    if (complement) {
        data.complement = complement;
    }

    if (reference) {
        data.reference = reference;
    }

    return data;
}

toggleAddressButton.addEventListener('click', () => {
    toggleAddressForm(addressForm.hidden);
});

addressSelect.addEventListener('change', refreshButton);

addressForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (savingAddress) {
        return;
    }

    savingAddress = true;
    saveAddressButton.disabled = true;

    refreshButton();
    showStatus('Salvando endereço...');

    try {
        const data = getAddressData();

        const response = await createAddress(data);
        const address = unwrapResource(response);

        const addressId = Number(address?.id);

        if (!Number.isSafeInteger(addressId) || addressId <= 0) {
            throw new Error(
                'O servidor não retornou o identificador do endereço.'
            );
        }

        const loaded = await loadAddresses(addressId);

        if (!loaded) {
            showStatus(
                'Endereço salvo, mas não foi possível atualizar a lista. Recarregue a página.',
                true
            );
            return;
        }

        addressForm.reset();
        toggleAddressForm(false);

        showStatus(
            'Endereço cadastrado e selecionado com sucesso!'
        );

    } catch (error) {
        showStatus(
            `Não foi possível cadastrar o endereço: ${getErrorMessage(error)}`,
            true
        );

    } finally {
        savingAddress = false;
        saveAddressButton.disabled = false;
        refreshButton();
    }
});

checkoutForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (submittingOrder || savingAddress || !cartHasItems) {
        return;
    }

    const addressId = Number(addressSelect.value);

    if (
        !addressesReady ||
        !Number.isSafeInteger(addressId) ||
        addressId <= 0
    ) {
        showStatus('Selecione um endereço válido.', true);
        return;
    }

    submittingOrder = true;
    refreshButton();

    showStatus('Processando seu pedido...');

    try {
        const response = await createOrder(addressId);
        const order = unwrapResource(response.order);

        const orderId = Number(order?.id);

        if (!Number.isSafeInteger(orderId) || orderId <= 0) {
            throw new Error(
                'O checkout respondeu sem um identificador de pedido.'
            );
        }

        clearCheckoutKey();

        window.location.assign(
            `/pedido.html?id=${encodeURIComponent(orderId)}&finalizado=1`
        );

    } catch (error) {
        showStatus(
            `Não foi possível confirmar o pedido: ${getErrorMessage(error)}`,
            true
        );

    } finally {
        submittingOrder = false;
        refreshButton();
    }
});

async function initializeCheckout() {
    if (!getAuthToken()) {
        window.location.assign(
            '/conta.html?next=%2Fcheckout.html'
        );
        return;
    }

    try {
        const response = await getCart();
        const cart = unwrapResource(response);

        renderItems(cart);

    } catch (error) {
        showStatus(
            `Não foi possível carregar o carrinho: ${getErrorMessage(error)}`,
            true
        );
        return;
    }

    await loadAddresses();
}

initializeCheckout();
