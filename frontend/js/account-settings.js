import { apiRequest } from './api.js';
import {
    getAuthToken,
    getAuthUser,
    updateStoredAuthUser
} from './services/auth.js';

const profileForm = document.querySelector('#profile-edit-form');
const profileName = document.querySelector('#profile-name');
const profileButton = document.querySelector('#profile-save');

const addressForm = document.querySelector('#account-address-form');
const addressList = document.querySelector('#account-address-list');
const addressTitle = document.querySelector('#account-address-title');
const addressSubmit = document.querySelector('#account-address-save');
const addressCancel = document.querySelector('#account-address-cancel');
const addressNew = document.querySelector('#account-address-new');

const message = document.querySelector('#account-settings-status');
const settings = document.querySelector('#account-settings');

let addresses = [];
let editingAddressId = null;
let busy = false;

function notify(text, error = false) {
    message.textContent = text;
    message.className = error ? 'auth-error' : 'auth-success';
}

function errorMessage(error) {
    const details = error?.details;

    const first = details && typeof details === 'object'
        ? Object.values(details)
            .flat()
            .find(value => typeof value === 'string')
        : null;

    return first ||
        error?.message ||
        'Não foi possível concluir a operação.';
}

function request(endpoint, options = {}) {
    const token = getAuthToken();

    if (!token) {
        throw new Error('Faça login para continuar.');
    }

    return apiRequest(endpoint, {
        ...options,
        token
    });
}

function getAddressData() {
    const data = new FormData(addressForm);

    const fields = [
        'recipient_name',
        'phone',
        'postal_code',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'reference'
    ];

    const payload = {};

    for (const key of fields) {
        const value = String(data.get(key) ?? '').trim();

        payload[key] =
            ['complement', 'reference'].includes(key) && !value
                ? null
                : value;
    }

    payload.state = payload.state.toUpperCase();
    payload.is_default = data.has('is_default');

    return payload;
}

function describeAddress(address) {
    const location = [
        address.street,
        address.number,
        address.neighborhood,
        address.city,
        address.state
    ].filter(Boolean).join(', ');

    return `${location} — CEP ${address.postal_code}`;
}

function addressButton(text, onClick) {
    const button = document.createElement('button');

    button.type = 'button';
    button.className = 'account-action-button';
    button.textContent = text;

    button.addEventListener('click', onClick);

    return button;
}

function renderAddresses() {
    addressList.replaceChildren();

    if (addresses.length === 0) {
        const empty = document.createElement('p');

        empty.textContent = 'Nenhum endereço cadastrado.';
        addressList.appendChild(empty);

        return;
    }

    for (const address of addresses) {
        const card = document.createElement('article');
        card.className = 'account-address-card';

        const title = document.createElement('strong');

        title.textContent =
            `${address.recipient_name}` +
            `${address.is_default ? ' • Principal' : ''}`;

        const details = document.createElement('p');
        details.textContent = describeAddress(address);

        const actions = document.createElement('div');
        actions.className = 'account-address-actions';

        actions.append(
            addressButton('Editar', () => editAddress(address)),
            addressButton('Excluir', () => deleteAddress(address))
        );

        card.append(title, details, actions);
        addressList.appendChild(card);
    }
}

async function loadAddresses() {
    const response = await request('addresses');

    addresses = Array.isArray(response?.data)
        ? response.data
        : [];

    renderAddresses();
}

function closeAddressForm() {
    editingAddressId = null;

    addressForm.reset();
    addressForm.hidden = true;

    addressTitle.textContent = 'Adicionar endereço';
    addressSubmit.textContent = 'Salvar endereço';
}

function editAddress(address) {
    editingAddressId = address.id;
    addressForm.reset();

    for (const field of addressForm.elements) {
        if (!field.name) continue;

        if (field.type === 'checkbox') {
            field.checked = Boolean(address[field.name]);
        } else if (Object.hasOwn(address, field.name)) {
            field.value = address[field.name] ?? '';
        }
    }

    addressTitle.textContent = 'Editar endereço';
    addressSubmit.textContent = 'Salvar alterações';

    addressForm.hidden = false;

    addressForm.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
    });
}

async function deleteAddress(address) {
    if (
        busy ||
        !window.confirm(
            `Excluir o endereço ${describeAddress(address)}?`
        )
    ) {
        return;
    }

    busy = true;

    try {
        await request(
            `addresses/${encodeURIComponent(address.id)}`,
            { method: 'DELETE' }
        );

        await loadAddresses();

        if (editingAddressId === address.id) {
            closeAddressForm();
        }

        notify('Endereço excluído com sucesso.');

    } catch (error) {
        notify(errorMessage(error), true);

    } finally {
        busy = false;
    }
}

profileForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (busy) return;

    const name = profileName.value.trim();

    if (!name) {
        notify('Informe seu nome.', true);
        return;
    }

    busy = true;
    profileButton.disabled = true;

    try {
        const response = await request('me', {
            method: 'PATCH',
            body: { name }
        });

        const user = response?.user ?? response?.data?.user;

        if (!user) {
            throw new Error('Resposta do perfil inválida.');
        }

        updateStoredAuthUser(user);

        document.querySelector(
            '#authenticated-name'
        ).textContent = user.name;

        profileName.value = user.name;

        notify('Nome atualizado com sucesso.');

    } catch (error) {
        notify(errorMessage(error), true);

    } finally {
        busy = false;
        profileButton.disabled = false;
    }
});

addressForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (busy) return;

    busy = true;
    addressSubmit.disabled = true;

    const id = editingAddressId;

    try {
        await request(
            id === null
                ? 'addresses'
                : `addresses/${encodeURIComponent(id)}`,
            {
                method: id === null ? 'POST' : 'PUT',
                body: getAddressData()
            }
        );

        closeAddressForm();

        await loadAddresses();

        notify(
            id === null
                ? 'Endereço cadastrado.'
                : 'Endereço atualizado.'
        );

    } catch (error) {
        notify(errorMessage(error), true);

    } finally {
        busy = false;
        addressSubmit.disabled = false;
    }
});

addressNew.addEventListener('click', () => {
    closeAddressForm();

    addressForm.hidden = false;

    addressForm.querySelector(
        '[name="recipient_name"]'
    ).focus();
});

addressCancel.addEventListener(
    'click',
    closeAddressForm
);

async function initialize() {
    const token = getAuthToken();

    settings.hidden = !token;

    if (!token) {
        closeAddressForm();
        addressList.replaceChildren();

        addresses = [];
        return;
    }

    const user = getAuthUser();

    profileName.value = user?.name ?? '';

    try {
        await loadAddresses();

    } catch (error) {
        notify(
            `Não foi possível carregar os endereços: ${errorMessage(error)}`,
            true
        );
    }
}

window.addEventListener(
    'petgo:auth-changed',
    initialize
);

initialize();
