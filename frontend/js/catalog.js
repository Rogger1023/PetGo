import { apiRequest } from './api.js';
import { createLinkedProductCard } from './components/product-card.js';
import { renderLayout } from './components/layout.js';

renderLayout();

const productsGrid = document.querySelector('#catalog-products');
const status = document.querySelector('#catalog-status');
const pagination = document.querySelector('#catalog-pagination');

const searchInput = document.querySelector('#catalog-search');
const categorySelect = document.querySelector('#catalog-category');

const urlParams = new URLSearchParams(window.location.search);

const search = (urlParams.get('search') ?? '').trim().slice(0, 100);

const rawCategoryId = urlParams.get('category_id') ?? '';

const categoryId = /^[1-9]\d*$/.test(rawCategoryId)
    ? rawCategoryId
    : '';

searchInput.value = search;

function getCurrentPage() {
    const params = new URLSearchParams(window.location.search);

    const page = Number(params.get('page') ?? 1);

    return Number.isSafeInteger(page) && page > 0
        ? page
        : 1;
}

function renderProducts(products) {
    const fragment = document.createDocumentFragment();

    products.forEach((product) => {
        fragment.appendChild(createLinkedProductCard(product));
    });

    productsGrid.replaceChildren(fragment);
}

function createPageLink(label, page) {
    const link = document.createElement('a');

    link.href = `/catalogo.html?${buildQuery(page)}`;
    link.textContent = label;

    return link;
}

function renderPagination(meta) {
    pagination.replaceChildren();

    const currentPage = Number(meta.current_page);
    const lastPage = Number(meta.last_page);

    if (lastPage <= 1) {
        return;
    }

    if (currentPage > 1) {
        pagination.appendChild(
            createPageLink('← Anterior', currentPage - 1)
        );
    }

    const pageInfo = document.createElement('span');

    pageInfo.className = 'page-current';

    pageInfo.textContent =
        `Página ${currentPage} de ${lastPage}`;

    pagination.appendChild(pageInfo);

    if (currentPage < lastPage) {
        pagination.appendChild(
            createPageLink('Próxima →', currentPage + 1)
        );
    }
}

    async function loadCategories() {
        try {
            const response = await apiRequest('categories');

            const categories = Array.isArray(response.data)
                ? response.data
                : Array.isArray(response)
                    ? response
                    : [];

            const fragment = document.createDocumentFragment();

            categories.forEach((category) => {
                const option = document.createElement('option');

                option.value = String(category.id);
                option.textContent = category.name;

                fragment.appendChild(option);
            });

            categorySelect.appendChild(fragment);
            categorySelect.value = categoryId;

        } catch (error) {
            console.error('Erro ao carregar categorias:', error);
            categorySelect.disabled = true;
        }
    }
    
    function buildQuery(page) {
        const params = new URLSearchParams();

        params.set('page', String(page));

        if (search !== '') {
            params.set('search', search);
        }

        if (categoryId !== '') {
            params.set('category_id', categoryId);
        }

        return params.toString();
    }

async function loadCatalog() {
    const page = getCurrentPage();

    status.textContent = 'Carregando catálogo...';

    try {
        const response = await apiRequest(
            `products?${buildQuery(page)}`
        );

        if (!Array.isArray(response.data)) {
            throw new Error('Resposta inválida da API.');
        }

        const products = response.data;

        renderProducts(products);

        if (products.length === 0) {
            status.textContent =
                'Nenhum produto encontrado nesta página.';
        } else {
            const total = response.meta?.total ?? products.length;

            status.textContent =
                `${total} produto(s) encontrado(s).`;
        }

        if (response.meta) {
            renderPagination(response.meta);
        }

    } catch (error) {
        console.error('Erro ao carregar catálogo:', error);

        productsGrid.replaceChildren();
        pagination.replaceChildren();

        status.textContent =
            'Não foi possível carregar o catálogo.';
    }
}

loadCategories();
loadCatalog();
