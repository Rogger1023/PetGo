export function renderProductImage(container, product) {
    if (!container || !product?.image_url) {
        return;
    }

    const fallback = container.textContent || '🐾';

    const image = document.createElement('img');

    image.src = product.image_url;
    image.alt = `Imagem de ${product.name}`;
    image.loading = 'lazy';
    image.decoding = 'async';

    image.addEventListener('error', () => {
        container.textContent = fallback;
    });

    container.replaceChildren(image);
}
