
import { renderLayout } from './components/layout.js';

import { getAuthToken,getAuthUser,login,register,logout } from './services/auth.js';
import { transferGuestCartToAccount } from './services/cart.js';

renderLayout();

const authForms = document.querySelector('#auth-forms');
const loginForm = document.querySelector('#login-form');
const registerForm = document.querySelector('#register-form');
const authenticatedPanel = document.querySelector('#authenticated-panel');
const authenticatedName = document.querySelector('#authenticated-name');
const authenticatedEmail = document.querySelector('#authenticated-email');
const logoutButton = document.querySelector('#logout-button');
const status = document.querySelector('#auth-status');

function showMessage(message, isError = false) {
    status.textContent = message;
    status.className = isError ? 'auth-error' : 'auth-success';
}

function getErrorMessage(error) {
    const validationErrors = error.details;

    if (validationErrors && typeof validationErrors === 'object') {
        const firstError = Object.values(validationErrors)
            .flat()
            .find((value) => typeof value === 'string');

        if (firstError) {
            return firstError;
        }
    }

    return error.message || 'Não foi possível concluir a operação.';
}

function showMode(mode) {
    loginForm.hidden = mode !== 'login';
    registerForm.hidden = mode !== 'register';

    document.querySelectorAll('[data-auth-mode]').forEach((button) => {
        button.setAttribute(
            'aria-pressed',
            String(button.dataset.authMode === mode)
        );
    });
}

function renderSession() {
    const token = getAuthToken();
    const user = getAuthUser();

    const authenticated = Boolean(token);

    authForms.hidden = authenticated;
    authenticatedPanel.hidden = !authenticated;

    if (authenticated) {
        authenticatedName.textContent =
            user?.name || 'Nome não informado';

        authenticatedEmail.textContent =
            user?.email || 'E-mail não informado';
    }
}

document.querySelectorAll('[data-auth-mode]').forEach((button) => {
    button.addEventListener('click', () => {
        showMode(button.dataset.authMode);
        showMessage('');
    });
});


async function transferCartAfterAuthentication() {
    try {
        const result = await transferGuestCartToAccount();

        if (result.failed.length > 0) {
            console.warn(
                'Itens não transferidos:',
                result.failed
            );

            return (
                `${result.failed.length} item(ns) não foram transferidos. ` +
                'Eles permanecem no carrinho de visitante.'
            );
        }

        if (result.transferred > 0) {
            return (
                `${result.transferred} item(ns) transferido(s) ` +
                'para sua conta com sucesso!'
            );
        }

        return 'Seu carrinho está pronto.';

    } catch (error) {
        console.error('Erro na transferência:', error);

        return (
            'Login realizado, mas não foi possível verificar ' +
            'a transferência do carrinho.'
        );
    }
}

loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const email = document.querySelector('#login-email').value.trim();
    const password = document.querySelector('#login-password').value;
    const button = loginForm.querySelector('[type="submit"]');

    button.disabled = true;
    showMessage('Entrando...');

    try {
        await login(email, password);

        const cartMessage = await transferCartAfterAuthentication();

        loginForm.reset();

        renderSession();

        showMessage(`Login realizado com sucesso! ${cartMessage}`);

    } catch (error) {
        showMessage(getErrorMessage(error), true);

    } finally {
        button.disabled = false;
    }
});

registerForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const name = document.querySelector('#register-name').value.trim();
    const email = document.querySelector('#register-email').value.trim();
    const password = document.querySelector('#register-password').value;
    const passwordConfirmation = document.querySelector(
        '#register-confirmation'
    ).value;

    if (password !== passwordConfirmation) {
        showMessage('As senhas não coincidem.', true);
        return;
    }

    const button = registerForm.querySelector('[type="submit"]');

    button.disabled = true;
    showMessage('Criando conta...');

    try {
        const result = await register({
            name,
            email,
            password,
            password_confirmation: passwordConfirmation
        });

        registerForm.reset();

        if (result.authenticated) {
            const cartMessage = await transferCartAfterAuthentication();

            renderSession();

            showMessage(`Conta criada com sucesso! ${cartMessage}`);
        } else {
            showMode('login');
            document.querySelector('#login-email').value = email;

            showMessage('Conta criada! Faça login para continuar.');
        }

    } catch (error) {
        showMessage(getErrorMessage(error), true);

    } finally {
        button.disabled = false;
    }
});

logoutButton.addEventListener('click', async () => {
    logoutButton.disabled = true;

    try {
        const warning = await logout();

        renderSession();
        showMode('login');

        showMessage(
            warning || 'Você saiu da sua conta.'
        );

    } finally {
        logoutButton.disabled = false;
    }
});

showMode(
    new URLSearchParams(location.search).get('mode') === 'register'
        ? 'register'
        : 'login'
);

renderSession();
