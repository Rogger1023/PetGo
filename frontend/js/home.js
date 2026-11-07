
import { apiRequest } from './api.js';
import { createLinkedProductCard } from './components/product-card.js';
import { renderLayout } from './components/layout.js';

renderLayout({ showSearch: true });

const productsGrid = document.querySelector('#products-grid');
const productsStatus = document.querySelector('#products-status');
const searchInput = document.querySelector('#product-search');

let allProducts = [];
let productsLoaded = false;

function normalizeText(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('pt-BR');
}

function renderProducts(products) {
    productsGrid.replaceChildren();

    if (products.length === 0) {
        return;
    }

    const fragment = document.createDocumentFragment();

    products.forEach((product) => {
        const card = createLinkedProductCard(product)
        fragment.appendChild(card);
    });

    productsGrid.appendChild(fragment);
}

function filterProducts() {
    if (!productsLoaded) {
        return;
    }

    const searchTerm = normalizeText(
        searchInput.value.trim()
    );

    const filteredProducts = allProducts.filter((product) => {
        const name = normalizeText(product.name);
        const category = normalizeText(product.category?.name);

        return (
            name.includes(searchTerm) ||
            category.includes(searchTerm)
        );
    });

    renderProducts(filteredProducts);

    if (allProducts.length === 0) {
        productsStatus.textContent =
            'Ainda não temos produtos disponíveis.';
        return;
    }

    if (filteredProducts.length === 0) {
        productsStatus.textContent =
            'Nenhum produto encontrado entre os itens desta página.';
        return;
    }

    productsStatus.textContent =
        `Exibindo ${filteredProducts.length} de ` +
        `${allProducts.length} produtos desta página.`;
}

async function loadProducts() {
    productsStatus.textContent = 'Carregando produtos...';

    try {
        const response = await apiRequest('products');

        if (!Array.isArray(response.data)) {
            throw new Error('Formato inesperado da API.');
        }

        allProducts = response.data;
        productsLoaded = true;

        filterProducts();

    } catch (error) {
        console.error('Erro ao carregar produtos:', error);

        productsStatus.textContent =
            'Não foi possível carregar os produtos. Tente novamente.';
    }
}

searchInput.addEventListener('input', filterProducts);

loadProducts();
