
import { apiRequest } from './api.js';
import { createProductCard } from './components/product-card.js';

const productsGrid = document.querySelector('#products-grid');
const productsStatus = document.querySelector('#products-status');

async function loadProducts() {
    productsStatus.textContent = 'Carregando produtos...';

    try {
        const response = await apiRequest('products');

        const products = response.data ?? [];

        if (!Array.isArray(products)) {
            throw new Error('Formato inesperado da API.');
        }

        productsGrid.replaceChildren();

        if (products.length === 0) {
            productsStatus.textContent =
                'Ainda não temos produtos disponíveis.';
            return;
        }

        const fragment = document.createDocumentFragment();

        products.forEach((product) => {
            const card = createProductCard(product);
            fragment.appendChild(card);
        });

        productsGrid.appendChild(fragment);

        productsStatus.textContent = '';

    } catch (error) {
        console.error('Erro ao carregar produtos:', error);

        productsStatus.textContent =
            'Não foi possível carregar os produtos. Tente novamente.';
    }
}

loadProducts();
