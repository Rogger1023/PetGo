import { apiRequest } from '../js/api.js';
import { getAuthToken,clearAuthSession } from '../js/services/auth.js';

const statusElement = document.querySelector('#admin-status');
const dashboard = document.querySelector('#admin-dashboard');
const ordersBody = document.querySelector('#admin-orders-body');
const ordersCount = document.querySelector('#admin-orders-count');
const detailsSection = document.querySelector('#admin-order-details');
const detailsContent = document.querySelector('#admin-order-content');

const money = (cents) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(Number(cents ?? 0) / 100);
};

function createElement(tag, value = '') {
    const element = document.createElement(tag);
    element.textContent = value;
    return element;
}

function setStatus(message, isError = false) {
    statusElement.textContent = message;
    statusElement.className = isError
        ? 'admin-error'
        : 'admin-success';
}

function addDetail(parent, label, value) {
    const row = document.createElement('div');
    row.className = 'admin-detail-row';

    row.append(
        createElement('span', label),
        createElement('strong', value)
    );

    parent.appendChild(row);
}

async function showOrder(id) {
    detailsSection.hidden = false;
    detailsContent.replaceChildren();
    detailsContent.textContent = 'Carregando pedido...';

    try {
        const response = await apiRequest(
            `admin/orders/${encodeURIComponent(id)}`,
            {
                token: getAuthToken()
            }
        );

        const order = response.data ?? response;

        detailsContent.replaceChildren();

        addDetail(detailsContent, 'Pedido', `#${order.id}`);
        addDetail(detailsContent, 'Status', String(order.status));
        addDetail(detailsContent, 'Subtotal', money(order.subtotal_cents));
        addDetail(detailsContent, 'Entrega', money(order.shipping_cents));
        addDetail(detailsContent, 'Total', money(order.total_cents));

        if (order.user) {
            addDetail(
                detailsContent,
                'Cliente',
                order.user.name ?? order.user.email ?? 'Não informado'
            );
        }

        if (Array.isArray(order.items)) {
            const heading = createElement('h3', 'Produtos');
            detailsContent.appendChild(heading);

            for (const item of order.items) {
                addDetail(
                    detailsContent,
                    `${item.product_name} × ${item.quantity}`,
                    money(item.line_total_cents)
                );
            }
        }

        detailsSection.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

    } catch (error) {
        detailsContent.textContent =
            `Não foi possível consultar o pedido: ${error.message}`;
    }
}

function renderOrders(orders) {
    ordersBody.replaceChildren();

    if (orders.length === 0) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');

        cell.colSpan = 5;
        cell.textContent = 'Nenhum pedido encontrado.';

        row.appendChild(cell);
        ordersBody.appendChild(row);
        return;
    }

    for (const order of orders) {
        const row = document.createElement('tr');

        const id = createElement('td', `#${order.id}`);

        const date = createElement(
            'td',
            order.created_at
                ? new Date(order.created_at).toLocaleDateString('pt-BR')
                : '—'
        );

        const status = createElement(
            'td',
            String(order.status ?? 'Não informado')
        );

        const total = createElement(
            'td',
            money(order.total_cents)
        );

        const action = document.createElement('td');
        const button = createElement('button', 'Ver detalhes');

        button.className = 'admin-action';

        button.addEventListener('click', () => {
            showOrder(order.id);
        });

        action.appendChild(button);

        row.append(id, date, status, total, action);

        ordersBody.appendChild(row);
    }
}

async function initializeAdmin() {
    const token = getAuthToken();

    if (!token) {
        setStatus('Você precisa fazer login para acessar o painel.', true);
        return;
    }

    try {
        const response = await apiRequest('admin/orders', {
            token
        });

        const orders = response.data;

        if (!Array.isArray(orders)) {
            throw new Error('Formato de resposta inesperado.');
        }

        renderOrders(orders);

        ordersCount.textContent =
            response.meta?.total ?? orders.length;

        dashboard.hidden = false;

        setStatus('Painel carregado com sucesso.');

    } catch (error) {
        dashboard.hidden = true;

        if (error.status === 403) {
            setStatus(
                'Acesso negado. Sua conta não possui permissão de administrador.',
                true
            );
            return;
        }

        if (error.status === 401) {
            clearAuthSession();

            setStatus(
                'Sessão inválida ou expirada. Faça login novamente.',
                true
            );

            return;
        }

        setStatus(
            `Não foi possível carregar o painel: ${error.message}`,
            true
        );
    }
}

initializeAdmin();
