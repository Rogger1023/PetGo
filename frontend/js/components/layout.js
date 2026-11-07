import {getGuestCart,updateGuestCartItem,removeGuestCartItem} from '../services/guest-cart.js';
export function renderLayout({ showSearch = false } = {}) {
    const header = document.querySelector('#site-header');
    const footer = document.querySelector('#site-footer');

    if (!header || !footer) {
        throw new Error(
            'Os elementos de layout não foram encontrados.'
        );
    }

    header.innerHTML = `
        <div class="container header-content">

            <a href="/" class="logo" aria-label="PetGo - início">
                <span aria-hidden="true">🐾</span>
                <span>PetGo</span>
            </a>
            
            ${showSearch ? `
                <div
                    class="header-search"
                    role="search"
                    aria-label="Pesquisa de produtos nesta página"
                >
                    <label for="product-search" class="sr-only">
                        Buscar produtos exibidos nesta página
                    </label>

                    <input
                        type="search"
                        id="product-search"
                        placeholder="Buscar nesta página..."
                        autocomplete="off"
                    >
                </div>
            ` : ''}

            <button
                type="button"
                class="menu-toggle"
                aria-label="Abrir menu"
                aria-controls="primary-navigation"
                aria-expanded="false"
            >
                <span aria-hidden="true">☰</span>
            </button>

            <nav
                id="primary-navigation"
                class="main-nav"
                aria-label="Navegação principal"
            >
                <a href="/catalogo.html">Produtos</a>
                <a href="/#sobre">Sobre nós</a>
            </nav>
            
            <div class="header-actions">

                <div class="header-action">
                    <button
                        type="button"
                        class="header-action-button"
                        aria-label="Informações da conta"
                        aria-controls="account-panel"
                        aria-expanded="false"
                    >
                        <span aria-hidden="true">👤</span>
                        <span>Conta</span>
                    </button>

                    <div
                        id="account-panel"
                        class="header-action-panel"
                        role="region"
                        aria-label="Informações da conta"
                        hidden
                    >
                        <strong>Minha conta</strong>
                        <p>
                            Em breve você poderá entrar,
                            cadastrar-se e consultar seus pedidos.
                        </p>
                    </div>
                </div>

                <div class="header-action">
                    <button
                        type="button"
                        class="header-action-button"
                        aria-label="Informações do carrinho"
                        aria-controls="cart-panel"
                        aria-expanded="false"
                    >
                        <span aria-hidden="true">🛒</span>
                        <span>Carrinho</span>
                        <span id="cart-item-count" class="cart-item-count" hidden>0</span>
                    </button>

                    <div
                        id="cart-panel"
                        class="header-action-panel"
                        role="region"
                        aria-label="Informações do carrinho"
                        hidden
                    >
                        <strong>Meu carrinho</strong>
                        <p id="cart-summary-status" role="status">
                            Carregando carrinho...
                        </p>

                        <ul id="cart-items-list" class="cart-items-list"></ul>

                        <p id="cart-summary-total" class="cart-summary-total" hidden></p>
                        <a href="/carrinho.html" class="cart-page-link">
                            Ver carrinho completo
                        </a>
                    </div>
                </div>

            </div>

        </div>
    `;


    footer.innerHTML = `
        <div class="container footer-content">

            <strong>🐾 PetGo</strong>

            <p>Projeto acadêmico de e-commerce.</p>

        </div>
    `;

    const menuToggle = header.querySelector('.menu-toggle');
    const navigation = header.querySelector('.main-nav');

    function setMenuOpen(isOpen) {
        navigation.classList.toggle('is-open', isOpen);

        menuToggle.setAttribute(
            'aria-expanded',
            String(isOpen)
        );

        menuToggle.setAttribute(
            'aria-label',
            isOpen ? 'Fechar menu' : 'Abrir menu'
        );
    }

    menuToggle.addEventListener('click', () => {
        const isOpen = navigation.classList.contains('is-open');

        setMenuOpen(!isOpen);
    });

    navigation.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            setMenuOpen(false);
        });
    });

    menuToggle.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setMenuOpen(false);
        }
    });

    navigation.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setMenuOpen(false);
            menuToggle.focus();
        }
    });

    const actionButtons = header.querySelectorAll(
        '.header-action-button'
    );

    function closeActionPanels() {
        actionButtons.forEach((button) => {
            const panelId = button.getAttribute('aria-controls');
            const panel = document.getElementById(panelId);

            panel.hidden = true;
            button.setAttribute('aria-expanded', 'false');
        });
    }

    actionButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const panelId = button.getAttribute('aria-controls');
            const panel = document.getElementById(panelId);

            const shouldOpen = panel.hidden;

            closeActionPanels();

            panel.hidden = !shouldOpen;

            button.setAttribute(
                'aria-expanded',
                String(shouldOpen)
            );
        });
    });

    document.addEventListener('click', (event) => {
        if (!header.querySelector('.header-actions').contains(event.target)) {
            closeActionPanels();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeActionPanels();
        }
    });

const cartCount = header.querySelector('#cart-item-count');
const cartStatus = header.querySelector('#cart-summary-status');
const cartItemsList = header.querySelector('#cart-items-list');
const cartTotal = header.querySelector('#cart-summary-total');

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
});

let cartRequestVersion = 0;

function getItemQuantity(item) {
    const quantity = Number(item.quantity);

    return Number.isSafeInteger(quantity) && quantity > 0
        ? quantity
        : 0;
}


function getItemSubtotal(item) {
    const quantity = getItemQuantity(item);

    const subtotal = item.subtotal_cents ?? item.total_cents;

    if (subtotal != null) {
        const cents = Number(subtotal);

        return Number.isSafeInteger(cents) && cents >= 0
            ? cents
            : null;
    }

    const unitPrice = item.unit_price_cents
        ?? item.product?.price_cents;

    if (unitPrice != null) {
        const cents = Number(unitPrice);

        return Number.isSafeInteger(cents) && cents >= 0
            ? cents * quantity
            : null;
    }

    return null;
}

function renderCartSummary(cart) {
    cartItemsList.replaceChildren();
    cartTotal.hidden = true;

    if (cart === null) {
        cartCount.hidden = true;
        cartStatus.hidden = false;
        cartStatus.textContent = 'Seu carrinho está vazio.';
        return;
    }

    if (!Array.isArray(cart.items)) {
        cartCount.hidden = true;
        cartStatus.hidden = false;
        cartStatus.textContent =
            'Os itens do carrinho não vieram na resposta da API.';
        return;
    }

    const items = cart.items;

    const totalQuantity = items.reduce(
        (total, item) => total + getItemQuantity(item),
        0
    );

    cartCount.textContent = String(totalQuantity);
    cartCount.hidden = totalQuantity === 0;

    if (items.length === 0) {
        cartStatus.hidden = false;
        cartStatus.textContent = 'Seu carrinho está vazio.';
        return;
    }

    cartStatus.hidden = true;

    const fragment = document.createDocumentFragment();

    let cartTotalCents = 0;
    let allPricesAvailable = true;

    items.forEach((item) => {
        const quantity = getItemQuantity(item);

        const productName =
            item.product?.name ??
            item.product_name ??
            `Produto #${item.product_id ?? item.id}`;

        const subtotal = getItemSubtotal(item);

        const listItem = document.createElement('li');
        listItem.className = 'cart-summary-item';

        const nameElement = document.createElement('strong');
        nameElement.textContent = productName;

        const quantityElement = document.createElement('span');
        quantityElement.textContent = `${quantity} unidade(s)`;

        listItem.append(nameElement, quantityElement);

        if (subtotal !== null) {
            const priceElement = document.createElement('span');

            priceElement.className = 'cart-item-price';
            priceElement.textContent =
                currencyFormatter.format(subtotal / 100);

            listItem.appendChild(priceElement);
            cartTotalCents += subtotal;
        } else {
            allPricesAvailable = false;
        }
        
        const actions = document.createElement('div');
        actions.className = 'cart-item-actions';

        const quantityInput = document.createElement('input');
        quantityInput.type = 'number';
        quantityInput.className = 'cart-item-quantity';
        quantityInput.min = '1';
        quantityInput.step = '1';
        quantityInput.value = String(quantity);
        quantityInput.setAttribute(
            'aria-label',
            `Quantidade de ${productName}`
        );

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

        listItem.appendChild(actions);
        
        fragment.appendChild(listItem);
    });

    cartItemsList.appendChild(fragment);

    if (allPricesAvailable) {
        cartTotal.textContent =
            `Subtotal: ${currencyFormatter.format(cartTotalCents / 100)}`;

        cartTotal.hidden = false;
    }
}

async function loadCartSummary() {
    const requestVersion = ++cartRequestVersion;

    cartStatus.hidden = false;
    cartStatus.textContent = 'Carregando carrinho...';

    try {
        const response = await getGuestCart();

        if (requestVersion !== cartRequestVersion) {
            return;
        }

        const cart = response?.data ?? response;

        renderCartSummary(cart);

    } catch (error) {
        if (requestVersion !== cartRequestVersion) {
            return;
        }

        console.error('Erro ao consultar carrinho:', error);

        cartItemsList.replaceChildren();
        cartCount.hidden = true;
        cartTotal.hidden = true;

        cartStatus.hidden = false;
        cartStatus.textContent =
            'Não foi possível consultar o carrinho.';
    }
}


cartItemsList.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');

    if (!button || button.disabled) {
        return;
    }

    const itemId = Number(button.dataset.itemId);
    const action = button.dataset.action;

    if (!Number.isSafeInteger(itemId) || itemId < 1) {
        return;
    }

    const itemElement = button.closest('.cart-summary-item');
    const quantityInput = itemElement.querySelector(
        '.cart-item-quantity'
    );

    const quantity = Number(quantityInput.value);

    if (
        action === 'update' &&
        (
            !Number.isSafeInteger(quantity) ||
            quantity < 1
        )
    ) {
        cartStatus.hidden = false;
        cartStatus.textContent = 'Informe uma quantidade válida.';
        return;
    }

    const buttons = cartItemsList.querySelectorAll('button');

    buttons.forEach((itemButton) => {
        itemButton.disabled = true;
    });

    cartStatus.hidden = false;
    cartStatus.textContent = 'Atualizando carrinho...';

    try {
        if (action === 'update') {
            await updateGuestCartItem(itemId, quantity);
        } else if (action === 'remove') {
            await removeGuestCartItem(itemId);
        }

    } catch (error) {
        console.error('Erro ao atualizar carrinho:', error);

        cartStatus.hidden = false;
        cartStatus.textContent =
            error.message || 'Não foi possível atualizar o carrinho.';

    } finally {
        buttons.forEach((itemButton) => {
            itemButton.disabled = false;
        });
    }
});


window.addEventListener('petgo:cart-updated', () => {
    loadCartSummary();
});

loadCartSummary();


}
