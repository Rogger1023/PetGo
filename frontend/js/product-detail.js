import { apiRequest } from './api.js';
import { renderLayout } from './components/layout.js';
import { renderProductImage } from './components/product-image.js';
import { addCartItem } from './services/cart.js';

renderLayout();

const status = document.querySelector('#product-detail-status');
const detail = document.querySelector('#product-detail');

const categoryElement = document.querySelector('#detail-category');
const nameElement = document.querySelector('#detail-name');
const priceElement = document.querySelector('#detail-price');
const stockElement = document.querySelector('#detail-stock');
const descriptionElement = document.querySelector('#detail-description');
const cartForm = document.querySelector('#add-to-cart-form');
const quantityInput = document.querySelector('#product-quantity');
const addButton = document.querySelector('#add-to-cart-button');
const cartFeedback = document.querySelector('#cart-feedback');

let currentProduct = null;

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
});

function getProductId() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');

    return id && /^[1-9]\d*$/.test(id)
        ? id
        : null;
}

function formatPrice(product) {
    if (typeof product.formatted_price === 'string') {
        return product.formatted_price;
    }

    if (typeof product.price_formatted === 'string') {
        return product.price_formatted;
    }

    if (product.price_cents != null) {
        const cents = Number(product.price_cents);

        if (Number.isFinite(cents)) {
            return currencyFormatter.format(cents / 100);
        }
    }

    if (product.price != null) {
        if (
            typeof product.price === 'string' &&
            product.price.startsWith('R$')
        ) {
            return product.price;
        }

        const price = Number(product.price);

        if (Number.isFinite(price)) {
            return currencyFormatter.format(price);
        }
    }

    return 'Preço não informado';
}

function renderProduct(product) {
    const category = product.category;

    categoryElement.textContent =
        typeof category === 'object' && category !== null
            ? category.name ?? 'Sem categoria'
            : product.category_name ?? 'Sem categoria';

    nameElement.textContent = product.name;
    priceElement.textContent = formatPrice(product);

    descriptionElement.textContent =
        product.description || 'Descrição não disponível.';

    const imageContainer = document.querySelector(
    '.product-detail-image'
    );

    renderProductImage(imageContainer, product);

    stockElement.classList.remove('available', 'unavailable');

    if (product.stock == null) {
        stockElement.textContent = 'Consulte a disponibilidade';
    } else if (Number(product.stock) > 0) {
        stockElement.textContent = '✓ Produto disponível';
        stockElement.classList.add('available');
    } else {
        stockElement.textContent = 'Produto esgotado';
        stockElement.classList.add('unavailable');
    }

    currentProduct = product;

    const stock = Number(product.stock);

    const hasStock =
        Number.isSafeInteger(stock) &&
        stock > 0;

    cartForm.hidden = !hasStock;

    if (hasStock) {
        quantityInput.max = String(stock);
        quantityInput.value = '1';
    }

    document.title = `${product.name} | PetGo`;

    detail.hidden = false;
    status.textContent = '';
}

async function loadProduct() {
    const productId = getProductId();

    if (!productId) {
        status.textContent = 'Produto inválido.';
        return;
    }

    try {
        const response = await apiRequest(`products/${productId}`);

        if (!response.data || Array.isArray(response.data)) {
            throw new Error('Formato inesperado da API.');
        }

        renderProduct(response.data);

    } catch (error) {
        console.error('Erro ao carregar produto:', error);

        detail.hidden = true;
        status.textContent =
            'Produto não encontrado ou indisponível.';
    }
}

cartForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!currentProduct) {
        return;
    }

    const quantity = Number(quantityInput.value);
    const stock = Number(currentProduct.stock);

    cartFeedback.textContent = '';
    cartFeedback.className = 'cart-feedback';

    if (
        !Number.isSafeInteger(quantity) ||
        quantity < 1 ||
        !Number.isSafeInteger(stock) ||
        quantity > stock
    ) {
        cartFeedback.textContent = 'Quantidade inválida.';
        cartFeedback.classList.add('error');
        return;
    }

    addButton.disabled = true;
    addButton.textContent = 'Adicionando...';

    try {
        await addCartItem(currentProduct.id, quantity);

        cartFeedback.textContent =
            'Produto adicionado ao carrinho com sucesso!';

        cartFeedback.classList.add('success');

    } catch (error) {
        console.error('Erro ao adicionar ao carrinho:', error);

        cartFeedback.textContent =
            error.message || 'Não foi possível adicionar o produto.';

        cartFeedback.classList.add('error');

    } finally {
        addButton.disabled = false;
        addButton.textContent = 'Adicionar ao carrinho';
    }
});


loadProduct();
