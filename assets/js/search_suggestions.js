(() => {
    const form = document.querySelector('.search-bar');
    const input = form?.querySelector('input[name="q"]');
    const list = document.getElementById('searchSuggestions');
    if (!form || !input || !list) return;

    let controller;
    let timer;
    const hide = () => { list.hidden = true; list.replaceChildren(); };
    const show = products => {
        list.replaceChildren();
        if (!products.length) return hide();
        products.forEach(product => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = `/Project/product.php?id=${encodeURIComponent(product.product_id)}`;
            const name = document.createElement('strong');
            name.textContent = product.name;
            const detail = document.createElement('span');
            detail.textContent = `${product.category_name} · ৳${Number(product.price).toLocaleString('en-BD', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            link.append(name, detail);
            item.append(link);
            list.append(item);
        });
        list.hidden = false;
    };
    const search = async () => {
        const query = input.value.trim();
        if (!query) return hide();
        controller?.abort();
        controller = new AbortController();
        try {
            const response = await fetch(`/Project/search_suggestions.php?q=${encodeURIComponent(query)}`, {signal: controller.signal});
            if (!response.ok) throw new Error('Suggestion request failed');
            show((await response.json()).products || []);
        } catch (error) {
            if (error.name !== 'AbortError') hide();
        }
    };
    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(search, 180);
    });
    input.addEventListener('focus', () => { if (input.value.trim()) search(); });
    input.addEventListener('keydown', event => { if (event.key === 'Escape') { hide(); input.focus(); } });
    form.addEventListener('focusout', () => window.setTimeout(hide, 150));
})();
