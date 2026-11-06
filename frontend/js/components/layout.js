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
                <a href="/#produtos">Produtos</a>
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
                    </button>

                    <div
                        id="cart-panel"
                        class="header-action-panel"
                        role="region"
                        aria-label="Informações do carrinho"
                        hidden
                    >
                        <strong>Meu carrinho</strong>
                        <p>
                            A integração com o carrinho será
                            implementada nas próximas etapas.
                        </p>
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


}
