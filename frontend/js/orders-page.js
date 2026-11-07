import { renderLayout } from './components/layout.js';
import { getAuthToken } from './services/auth.js';

import {
    getOrders,
    getOrder,
    formatMoney,
    unwrapResource
} from './services/orders.js';

renderLayout();

const container = document.querySelector('#orders-content');
const view = document.body.dataset.ordersView;

function element(tag, text = '', className = '') {
    const node = document.createElement(tag);
    node.textContent = text;
    node.className = className;
    return node;
}

function formatDate(value) {
    if (!value) return 'Data não informada';

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? 'Data não informada'
        : date.toLocaleDateString('pt-BR');
}

function addRow(parent, label, value) {
    const row = element('div', '', 'checkout-item');

    row.append(
        element('span', label),
        element('strong', value)
    );

    parent.appendChild(row);
}

async function renderOrderList() {
    const params = new URLSearchParams(location.search);
    const requestedPage = Number(params.get('page') ?? 1);
    const page = Number.isSafeInteger(requestedPage) && requestedPage > 0
        ? requestedPage
        : 1;

    const response = await getOrders(page);
    const orders = response?.data;

    if (!Array.isArray(orders)) {
        throw new Error('Formato inválido da listagem de pedidos.');
    }

    container.replaceChildren();

    if (orders.length === 0) {
        container.appendChild(
            element('p', 'Nenhum pedido encontrado.')
        );
        return;
    }

    for (const order of orders) {
        const card = element('article', '', 'checkout-card');

        card.appendChild(
            element('h2', `Pedido #${order.id}`)
        );

        addRow(card, 'Data', formatDate(order.created_at));
        addRow(card, 'Situação', String(order.status ?? 'Não informada'));
        addRow(card, 'Total', formatMoney(order.total_cents));

        const link = element('a', 'Ver detalhes', 'checkout-action');
        link.href = `/pedido.html?id=${encodeURIComponent(order.id)}`;

        card.appendChild(link);
        container.appendChild(card);
    }

    const lastPage = Number(response?.meta?.last_page ?? page);

    if (page > 1 || page < lastPage) {
        const navigation = element('nav', '', 'orders-pagination');

        if (page > 1) {
            const previous = element('a', '← Anterior');
            previous.href = `/pedidos.html?page=${page - 1}`;
            navigation.appendChild(previous);
        }

        navigation.appendChild(element('span', `Página ${page}`));

        if (page < lastPage) {
            const next = element('a', 'Próxima →');
            next.href = `/pedidos.html?page=${page + 1}`;
            navigation.appendChild(next);
        }

        container.appendChild(navigation);
    }
}

async function renderOrderDetail() {
    const params = new URLSearchParams(location.search);
    const id = params.get('id');

    if (!id || !/^[1-9]\d*$/.test(id)) {
        throw new Error('Identificador do pedido inválido.');
    }

    const response = await getOrder(id);
    const order = unwrapResource(response);

    container.replaceChildren();

    if (params.get('finalizado') === '1') {
        container.appendChild(
            element(
                'p',
                'Pedido registrado com sucesso!',
                'checkout-success'
            )
        );
    }

    const card = element('section', '', 'checkout-card');

    card.appendChild(
        element('h2', `Pedido #${order.id}`)
    );

    addRow(card, 'Data', formatDate(order.created_at));
    addRow(card, 'Situação', String(order.status ?? 'Não informada'));
    addRow(card, 'Subtotal', formatMoney(order.subtotal_cents));
    addRow(card, 'Entrega', formatMoney(order.shipping_cents));
    addRow(card, 'Total', formatMoney(order.total_cents));

    if (order.payment) {
        addRow(
            card,
            'Pagamento',
            String(order.payment.status ?? 'Não informado')
        );
    }

    if (Array.isArray(order.items)) {
        card.appendChild(
            element('h3', 'Produtos do pedido', 'orders-section-title')
        );

        for (const item of order.items) {
            addRow(
                card,
                `${item.product_name} × ${item.quantity}`,
                formatMoney(item.line_total_cents)
            );
        }
    }

    if (order.address && typeof order.address === 'object') {
        const address = order.address;

        const addressText = [
            address.street ?? address.logradouro,
            address.number ?? address.numero,
            address.neighborhood ?? address.bairro,
            address.city ?? address.cidade,
            address.state ?? address.estado
        ].filter(Boolean).join(', ');

        if (addressText) {
            card.appendChild(
                element('h3', 'Endereço de entrega', 'orders-section-title')
            );

            card.appendChild(element('p', addressText));
        }
    }

    const link = element('a', 'Ver todos os pedidos', 'checkout-action');
    link.href = '/pedidos.html';

    card.appendChild(link);
    container.appendChild(card);
}

async function initializeOrdersPage() {
    if (!getAuthToken()) {
        container.replaceChildren();

        const link = element(
            'a',
            'Faça login para consultar seus pedidos.',
            'checkout-action'
        );

        link.href = '/conta.html';
        container.appendChild(link);
        return;
    }

    try {
        if (view === 'detail') {
            await renderOrderDetail();
        } else {
            await renderOrderList();
        }
    } catch (error) {
        container.replaceChildren();

        container.appendChild(
            element(
                'p',
                `Não foi possível carregar: ${error.message}`,
                'checkout-error'
            )
        );
    }
}

initializeOrdersPage();
