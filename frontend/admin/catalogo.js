import { apiRequest } from '../js/api.js';
import { getAuthToken } from '../js/services/auth.js';

const $ = (selector) => document.querySelector(selector);

const productForm = $('#product-form');
const categoryForm = $('#category-form');

let products = [];
let categories = [];
let currentPage = 1;
let editingProductId = null;
let editingCategoryId = null;

function field(form, name) {
    return form.elements.namedItem(name);
}

function showStatus(message, isError = false) {
    const status = $('#catalog-status');

    status.textContent = message;
    status.className = isError ? 'admin-error' : 'admin-success';
}

function errorMessage(error) {
    const details = error?.details?.errors ?? error?.details;

    if (details && typeof details === 'object') {
        const first = Object.values(details)
            .flat()
            .find((value) => typeof value === 'string');

        if (first) return first;
    }

    return error?.message ?? 'Ocorreu um erro inesperado.';
}

function adminRequest(endpoint, options = {}) {
    const token = getAuthToken();

    if (!token) {
        throw new Error('Faça login para acessar o painel.');
    }

    return apiRequest(endpoint, {
        ...options,
        token
    });
}

function collection(response) {
    const items = response?.data ?? response;

    if (!Array.isArray(items)) {
        throw new Error('Formato de listagem inválido.');
    }

    return items;
}

function money(cents) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(Number(cents ?? 0) / 100);
}

function priceToCents(value) {
    const text = String(value).trim();

    if (!/^\d+(?:[.,]\d{1,2})?$/.test(text)) {
        throw new Error('Informe o preço como 89,90.');
    }

    const [reais, centavos = ''] = text.replace(',', '.').split('.');

    const result =
        Number(reais) * 100 +
        Number(centavos.padEnd(2, '0'));

    if (!Number.isSafeInteger(result)) {
        throw new Error('Preço inválido.');
    }

    return result;
}

function slugify(value) {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
}

function cell(value) {
    const td = document.createElement('td');
    td.textContent = String(value ?? '');
    return td;
}

function actionButton(label, action, id) {
    const button = document.createElement('button');

    button.type = 'button';
    button.className = 'catalog-secondary';
    button.textContent = label;
    button.dataset.action = action;
    button.dataset.id = String(id);

    return button;
}

function fillCategorySelect() {
    const select = field(productForm, 'category_id');
    const selected = select.value;

    select.replaceChildren();

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Selecione a categoria';

    select.appendChild(placeholder);

    for (const category of categories) {
        const option = document.createElement('option');

        option.value = String(category.id);
        option.textContent = category.name;

        select.appendChild(option);
    }

    select.value = selected;
}

function renderCategories() {
    const tbody = $('#categories-body');
    tbody.replaceChildren();

    if (categories.length === 0) {
        const tr = document.createElement('tr');
        const td = cell('Nenhuma categoria cadastrada.');

        td.colSpan = 3;
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
    }

    for (const category of categories) {
        const tr = document.createElement('tr');

        const actions = document.createElement('td');
        actions.className = 'catalog-actions';

        actions.append(
            actionButton('Editar', 'edit-category', category.id),
            actionButton('Excluir', 'delete-category', category.id)
        );

        tr.append(
            cell(category.name),
            cell(category.slug),
            actions
        );

        tbody.appendChild(tr);
    }
}

function renderProducts(response) {
    const tbody = $('#products-body');
    tbody.replaceChildren();

    if (products.length === 0) {
        const tr = document.createElement('tr');
        const td = cell('Nenhum produto encontrado.');

        td.colSpan = 6;
        tr.appendChild(td);
        tbody.appendChild(tr);
    }

    for (const product of products) {
        const tr = document.createElement('tr');

        const category = categories.find(
            (item) => Number(item.id) === Number(product.category_id)
        );

        const active = Boolean(product.is_active);
        const actions = document.createElement('td');

        actions.className = 'catalog-actions';

        actions.append(
            actionButton('Editar', 'edit-product', product.id),
            actionButton(
                active ? 'Desativar' : 'Reativar',
                'toggle-product',
                product.id
            )
        );

        tr.append(
            cell(product.name),
            cell(product.category?.name ?? category?.name ?? '—'),
            cell(money(product.price_cents)),
            cell(product.stock),
            cell(active ? 'Ativo' : 'Inativo'),
            actions
        );

        tbody.appendChild(tr);
    }

    const pagination = $('#product-pages');
    pagination.replaceChildren();

    const lastPage = Number(response?.meta?.last_page ?? 1);

    const previous = actionButton('Anterior', 'previous-page', 0);
    previous.disabled = currentPage <= 1;

    const next = actionButton('Próxima', 'next-page', 0);
    next.disabled = currentPage >= lastPage;

    const label = document.createElement('span');
    label.textContent = `Página ${currentPage} de ${lastPage}`;

    pagination.append(previous, label, next);
}

async function loadCategories() {
    const response = await adminRequest('categories');

    categories = collection(response);

    fillCategorySelect();
    renderCategories();
}

async function loadProducts() {
    const params = new URLSearchParams({
        page: String(currentPage)
    });

    const search = $('#product-search').value.trim();
    const status = $('#product-status').value;

    if (search) {
        params.set('search', search);
    }

    if (status !== 'all') {
        params.set('status', status);
    }

    const response = await adminRequest(
        `admin/products?${params.toString()}`
    );

    products = collection(response);

    renderProducts(response);
}

function openProductEditor(product = null) {
    editingProductId = product?.id ?? null;

    productForm.reset();
    fillCategorySelect();

    $('#product-editor-title').textContent = product
        ? 'Editar produto'
        : 'Novo produto';

    if (product) {
        field(productForm, 'name').value = product.name;
        field(productForm, 'slug').value = product.slug;

        field(productForm, 'category_id').value =
            String(product.category_id);

        field(productForm, 'price_reais').value =
            (Number(product.price_cents) / 100)
                .toFixed(2)
                .replace('.', ',');

        field(productForm, 'stock').value = product.stock;

        field(productForm, 'description').value =
            product.description ?? '';

        field(productForm, 'is_active').checked =
            Boolean(product.is_active);
    }

    $('#product-editor').hidden = false;

    $('#product-editor').scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
}

function closeProductEditor() {
    editingProductId = null;
    productForm.reset();
    $('#product-editor').hidden = true;
}

function openCategoryEditor(category = null) {
    editingCategoryId = category?.id ?? null;

    categoryForm.reset();

    if (category) {
        field(categoryForm, 'name').value = category.name;
        field(categoryForm, 'slug').value = category.slug;
        field(categoryForm, 'description').value =
            category.description ?? '';
    }

    $('#category-editor').hidden = false;
}

function closeCategoryEditor() {
    editingCategoryId = null;
    categoryForm.reset();
    $('#category-editor').hidden = true;
}

field(productForm, 'name').addEventListener('blur', () => {
    const slug = field(productForm, 'slug');

    if (!slug.value.trim()) {
        slug.value = slugify(field(productForm, 'name').value);
    }
});

field(categoryForm, 'name').addEventListener('blur', () => {
    const slug = field(categoryForm, 'slug');

    if (!slug.value.trim()) {
        slug.value = slugify(field(categoryForm, 'name').value);
    }
});

$('#new-product').addEventListener('click', () => {
    openProductEditor();
});

$('#cancel-product').addEventListener('click', closeProductEditor);

$('#new-category').addEventListener('click', () => {
    openCategoryEditor();
});

$('#cancel-category').addEventListener('click', closeCategoryEditor);

$('#product-filters').addEventListener('submit', async (event) => {
    event.preventDefault();

    currentPage = 1;

    try {
        await loadProducts();
    } catch (error) {
        showStatus(errorMessage(error), true);
    }
});

productForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const button = productForm.querySelector('[type="submit"]');
    button.disabled = true;

    try {
        const form = new FormData(productForm);

        const categoryId = Number(form.get('category_id'));
        const stock = Number(form.get('stock'));

        if (!Number.isSafeInteger(categoryId) || categoryId <= 0) {
            throw new Error('Selecione uma categoria válida.');
        }

        if (!Number.isSafeInteger(stock) || stock < 0) {
            throw new Error('Informe um estoque válido.');
        }

        const data = {
            category_id: categoryId,
            name: String(form.get('name')).trim(),
            slug: String(form.get('slug')).trim(),
            description:
                String(form.get('description') ?? '').trim() || null,
            price_cents: priceToCents(form.get('price_reais')),
            stock,
            is_active: form.has('is_active')
        };

        const endpoint = editingProductId
            ? `admin/products/${editingProductId}`
            : 'admin/products';

        await adminRequest(endpoint, {
            method: editingProductId ? 'PATCH' : 'POST',
            body: data
        });

        closeProductEditor();

        currentPage = 1;
        await loadProducts();

        showStatus('Produto salvo com sucesso.');

    } catch (error) {
        showStatus(errorMessage(error), true);

    } finally {
        button.disabled = false;
    }
});

categoryForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const button = categoryForm.querySelector('[type="submit"]');
    button.disabled = true;

    try {
        const form = new FormData(categoryForm);

        const data = {
            name: String(form.get('name')).trim(),
            slug: String(form.get('slug')).trim(),
            description:
                String(form.get('description') ?? '').trim() || null
        };

        const endpoint = editingCategoryId
            ? `admin/categories/${editingCategoryId}`
            : 'admin/categories';

        await adminRequest(endpoint, {
            method: editingCategoryId ? 'PATCH' : 'POST',
            body: data
        });

        closeCategoryEditor();

        await loadCategories();
        await loadProducts();

        showStatus('Categoria salva com sucesso.');

    } catch (error) {
        showStatus(errorMessage(error), true);

    } finally {
        button.disabled = false;
    }
});

$('#products-body').addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;

    const product = products.find(
        (item) => String(item.id) === button.dataset.id
    );

    if (!product) return;

    if (button.dataset.action === 'edit-product') {
        openProductEditor(product);
        return;
    }

    if (button.dataset.action !== 'toggle-product') return;

    const active = Boolean(product.is_active);

    const confirmed = window.confirm(
        active
            ? `Desativar o produto "${product.name}"?`
            : `Reativar o produto "${product.name}"?`
    );

    if (!confirmed) return;

    button.disabled = true;

    try {
        if (active) {
            await adminRequest(`admin/products/${product.id}`, {
                method: 'DELETE'
            });
        } else {
            await adminRequest(`admin/products/${product.id}`, {
                method: 'PATCH',
                body: { is_active: true }
            });
        }

        await loadProducts();

        showStatus(
            active
                ? 'Produto desativado.'
                : 'Produto reativado.'
        );

    } catch (error) {
        showStatus(errorMessage(error), true);
        button.disabled = false;
    }
});

$('#categories-body').addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;

    const id = button.dataset.id;

    if (button.dataset.action === 'edit-category') {
        try {
            const response = await adminRequest(`categories/${id}`);
            openCategoryEditor(response.data ?? response);
        } catch (error) {
            showStatus(errorMessage(error), true);
        }

        return;
    }

    if (button.dataset.action !== 'delete-category') return;

    const category = categories.find(
        (item) => String(item.id) === id
    );

    if (!category) return;

    if (!window.confirm(`Excluir a categoria "${category.name}"?`)) {
        return;
    }

    button.disabled = true;

    try {
        await adminRequest(`admin/categories/${id}`, {
            method: 'DELETE'
        });

        await loadCategories();
        await loadProducts();

        showStatus('Categoria excluída com sucesso.');

    } catch (error) {
        showStatus(errorMessage(error), true);
        button.disabled = false;
    }
});

$('#product-pages').addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button || button.disabled) return;

    if (button.dataset.action === 'previous-page') {
        currentPage -= 1;
    } else if (button.dataset.action === 'next-page') {
        currentPage += 1;
    } else {
        return;
    }

    try {
        await loadProducts();
    } catch (error) {
        showStatus(errorMessage(error), true);
    }
});

async function initializeCatalog() {
    if (!getAuthToken()) {
        showStatus('Faça login para acessar o catálogo administrativo.', true);
        return;
    }

    try {
        await loadCategories();
        await loadProducts();

        $('#catalog-content').hidden = false;
        showStatus('Catálogo administrativo carregado.');

    } catch (error) {
        $('#catalog-content').hidden = true;

        if (error.status === 403) {
            showStatus('Acesso negado: conta sem permissão de administrador.', true);
            return;
        }

        if (error.status === 401) {
            showStatus('Sessão expirada. Faça login novamente.', true);
            return;
        }

        showStatus(errorMessage(error), true);
    }
}

initializeCatalog();
