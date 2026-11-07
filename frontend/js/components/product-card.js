
import { formatPrice } from '../utils/currency.js';
import { renderProductImage } from './product-image.js';

function createElement(tag, className, text) {
    const element = document.createElement(tag);

    if (className) {
        element.className = className;
    }

    if (text !== undefined) {
        element.textContent = text;
    }

    return element;
}

export function createProductCard(product) {
    const card = createElement('article', 'product-card');

    const image = createElement(
        'div',
        'product-image',
        '🐾'
    );

    image.setAttribute('aria-hidden', 'true');

    const content = createElement('div', 'product-content');

    const category = createElement(
        'span',
        'product-category',
        product.category?.name ?? 'Produto PetGo'
    );

    const name = createElement(
        'h3',
        'product-name',
        product.name
    );

    const description = createElement(
        'p',
        'product-description',
        product.description ?? 'Confira este produto.'
    );

    const price = createElement(
        'strong',
        'product-price',
        formatPrice(product.price_cents)
    );

    const stockStatus = createElement(
        'span',
        `stock-status ${
            product.in_stock ? 'available' : 'unavailable'
        }`,
        product.in_stock
            ? 'Disponível'
            : 'Indisponível'
    );

    content.append(
        category,
        name,
        description,
        price,
        stockStatus
    );

    card.append(image, content);

    const imageContainer = card.querySelector('.product-image');

    renderProductImage(imageContainer, product);
    
    return card;
}
export function createLinkedProductCard(product) {
    const card = createProductCard(product);

    const nameElement = card.querySelector('.product-name');

    if (nameElement && product.id != null) {
        const link = document.createElement('a');

        link.href = `/produto.html?id=${encodeURIComponent(product.id)}`;
        link.textContent = nameElement.textContent;

        nameElement.replaceChildren(link);
    }

    return card;
}

