import { renderLayout } from './components/layout.js';
import { getCart,updateCartItem,removeCartItem } from './services/cart.js';

renderLayout();

const status = document.querySelector('#cart-page-status');
const content = document.querySelector('#cart-page-content');
const itemsList = document.querySelector('#cart-page-items');
const countElement = document.querySelector('#cart-page-count');
const totalElement = document.querySelector('#cart-page-total');

const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
});

let busy = false;

function showStatus(message) {
    status.textContent = message;
    status.hidden = message === '';
}

function getQuantity(item) {
    const quantity = Number(item.quantity);

    return Number.isSafeInteger(quantity) && quantity > 0
        ? quantity
        : 0;
}

function getPriceCents(product) {
    const cents = Number(product?.price_cents);

    if (
        product?.price_cents == null ||
        !Number.isSafeInteger(cents) ||
        cents < 0
    ) {
        return null;
    }

    return cents;
}

function createCartItem(item) {
    const product = item.product ?? {};
    const quantity = getQuantity(item);
    const unitPrice = getPriceCents(product);

    const listItem = document.createElement('li');
    listItem.className = 'cart-page-item';

    const imageContainer = document.createElement('div');
    imageContainer.className = 'cart-page-image';
    imageContainer.textContent = '🐾';

    if (product.image_url) {
        const image = document.createElement('img');

        image.src = product.image_url;
        image.alt = product.name ?? 'Produto';
        image.loading = 'lazy';

        image.addEventListener('error', () => {
            imageContainer.textContent = '🐾';
        });

        imageContainer.replaceChildren(image);
    }

    const info = document.createElement('div');
    info.className = 'cart-page-item-info';

    const title = document.createElement('h2');
    const link = document.createElement('a');

    link.textContent = product.name ?? 'Produto';

    if (product.id != null) {
        link.href = `/produto.html?id=${encodeURIComponent(product.id)}`;
    } else {
        link.href = '/catalogo.html';
    }

    title.appendChild(link);

    const price = document.createElement('p');
    price.className = 'cart-page-item-price';

    if (unitPrice !== null) {
        price.textContent =
            `${currency.format(unitPrice / 100)} por unidade`;
    } else {
        price.textContent = 'Preço indisponível';
    }

    const actions = document.createElement('div');
    actions.className = 'cart-page-item-actions';

    const quantityInput = document.createElement('input');
    quantityInput.type = 'number';
    quantityInput.min = '1';
    quantityInput.step = '1';
    quantityInput.value = String(quantity);
    quantityInput.setAttribute(
        'aria-label',
        `Quantidade de ${product.name ?? 'produto'}`
    );

    const stock = Number(product.stock);

    if (Number.isSafeInteger(stock) && stock > 0) {
        quantityInput.max = String(stock);
    }

    const updateButton = document.createElement('button');
    updateButton.type = 'button';
    updateButton.textContent = 'Atualizar';
    updateButton.dataset.action = 'update';
    updateButton.dataset.itemId = String(item.id);

    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.textContent = 'Remover';
    removeButton.dataset.action = 'remove';
    removeButton.dataset.itemId = String(item.id);

    actions.append(
        quantityInput,
        updateButton,
        removeButton
    );

    info.append(title, price, actions);
    listItem.append(imageContainer, info);

    return listItem;
}

function renderCart(cart) {
    itemsList.replaceChildren();

    const items = cart?.items ?? [];

    if (!Array.isArray(items)) {
        throw new Error('Resposta inválida do carrinho.');
    }

    if (items.length === 0) {
        content.hidden = true;
        showStatus('Seu carrinho está vazio.');
        return;
    }

    const fragment = document.createDocumentFragment();

    let totalQuantity = 0;
    let totalCents = 0;
    let pricesAvailable = true;

    items.forEach((item) => {
        const quantity = getQuantity(item);
        const unitPrice = getPriceCents(item.product);

        totalQuantity += quantity;

        if (unitPrice !== null) {
            totalCents += unitPrice * quantity;
        } else {
            pricesAvailable = false;
        }

        fragment.appendChild(createCartItem(item));
    });

    itemsList.appendChild(fragment);

    countElement.textContent =
        `${totalQuantity} unidade(s) no carrinho`;

    totalElement.textContent = pricesAvailable
        ? currency.format(totalCents / 100)
        : 'Preço indisponível';

    content.hidden = false;
    showStatus('');
}

async function loadCart() {
    showStatus('Carregando carrinho...');

    try {
        const response = await getCart();
        const cart = response?.data ?? response;

        renderCart(cart);

    } catch (error) {
        console.error('Erro ao carregar carrinho:', error);

        content.hidden = true;

        showStatus(
            'Não foi possível carregar o carrinho.'
        );
    }
}

itemsList.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');

    if (!button || busy) {
        return;
    }

    const itemId = Number(button.dataset.itemId);
    const action = button.dataset.action;

    if (!Number.isSafeInteger(itemId) || itemId < 1) {
        return;
    }

    const itemElement = button.closest('.cart-page-item');
    const quantityInput = itemElement.querySelector('input');
    const quantity = Number(quantityInput.value);

    if (
        action === 'update' &&
        (
            !Number.isSafeInteger(quantity) ||
            quantity < 1 ||
            (
                quantityInput.max !== '' &&
                quantity > Number(quantityInput.max)
            )
        )
    ) {
        showStatus('Informe uma quantidade válida.');
        return;
    }

    busy = true;

    itemsList.querySelectorAll('button').forEach((itemButton) => {
        itemButton.disabled = true;
    });

    showStatus('Atualizando carrinho...');

    try {
        if (action === 'update') {
            await updateCartItem(itemId, quantity);
        } else if (action === 'remove') {
            await removeCartItem(itemId);
        }

        await loadCart();

    } catch (error) {
        console.error('Erro ao modificar carrinho:', error);

        showStatus(
            error.message || 'Não foi possível atualizar o carrinho.'
        );

    } finally {
        busy = false;

        itemsList.querySelectorAll('button').forEach((itemButton) => {
            itemButton.disabled = false;
        });
    }
});

loadCart();
