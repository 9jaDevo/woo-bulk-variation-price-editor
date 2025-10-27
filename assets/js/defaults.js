/* Default Attributes Page Script */
(function () {
    if (typeof window === 'undefined') return;

    const restRoot = (typeof wbvDefaults !== 'undefined' && wbvDefaults.rest_root) ? wbvDefaults.rest_root : '/wp-json/wbvpricer/v1';
    const nonce = (typeof wbvDefaults !== 'undefined' && wbvDefaults.nonce) ? wbvDefaults.nonce : '';

    function qs(selector) { return document.querySelector(selector); }

    let lastProducts = [];

    async function performSearch(query, perPage, page) {
        const res = await fetch(`${restRoot}/search?per_page=${perPage}&page=${page}&s=${encodeURIComponent(query)}`, {
            headers: { 'X-WP-Nonce': nonce }
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({ message: 'Network error' }));
            throw new Error(err.message || 'REST error');
        }
        return res.json();
    }

    function renderProductsForDefaults(container, products) {
        container.innerHTML = '';
        if (!products || products.length === 0) {
            container.innerHTML = '<p>No variable products found</p>';
            // Clear attributes panel when no products
            const attributesDiv = qs('#wbv-defaults-attributes');
            if (attributesDiv) {
                attributesDiv.innerHTML = '<p style="color:#999; font-style:italic;">Search for products first to see available attributes.</p>';
            }
            return;
        }

        console.log('Rendering', products.length, 'products for defaults');

        products.forEach(product => {
            const wrap = document.createElement('div');
            wrap.className = 'wbv-product wbv-product-defaults';
            wrap.dataset.productId = product.product_id;
            wrap.style.marginBottom = '1.25rem';
            wrap.style.padding = '12px';
            wrap.style.background = '#fff';
            wrap.style.border = '1px solid #e5e5e5';
            wrap.style.borderRadius = '4px';

            const header = document.createElement('div');
            header.className = 'wbv-product-header';
            header.style.marginBottom = '10px';

            const productCheckbox = document.createElement('input');
            productCheckbox.type = 'checkbox';
            productCheckbox.className = 'wbv-select-product-default';
            productCheckbox.dataset.productId = product.product_id;
            productCheckbox.style.marginRight = '10px';
            header.appendChild(productCheckbox);

            const title = document.createElement('strong');
            title.textContent = product.title + (product.sku ? ' — ' + product.sku : '');
            title.style.fontSize = '15px';
            header.appendChild(title);

            wrap.appendChild(header);

            // Display current default attributes
            const defaultsDiv = document.createElement('div');
            defaultsDiv.className = 'wbv-current-defaults';
            defaultsDiv.style.padding = '10px';
            defaultsDiv.style.background = '#f9f9f9';
            defaultsDiv.style.borderRadius = '3px';

            const defaultsLabel = document.createElement('strong');
            defaultsLabel.textContent = 'Current Defaults: ';
            defaultsDiv.appendChild(defaultsLabel);

            if (product.default_attributes && product.default_attributes.length > 0) {
                const defaultsList = product.default_attributes.map(attr =>
                    `${attr.label}: ${attr.display_value}`
                ).join(', ');
                const span = document.createElement('span');
                span.textContent = defaultsList;
                defaultsDiv.appendChild(span);
            } else {
                const span = document.createElement('span');
                span.textContent = 'None set';
                span.style.fontStyle = 'italic';
                span.style.color = '#999';
                defaultsDiv.appendChild(span);
            }

            wrap.appendChild(defaultsDiv);
            container.appendChild(wrap);
        });

        // Show the defaults selector panel automatically with all available attributes
        console.log('Calling showDefaultsSelector after rendering products');
        showDefaultsSelector();
    }

    function showDefaultsSelector() {
        console.log('showDefaultsSelector called');
        const attributesDiv = qs('#wbv-defaults-attributes');

        console.log('attributesDiv:', attributesDiv);
        console.log('lastProducts count:', lastProducts ? lastProducts.length : 0);

        if (!attributesDiv || !lastProducts || lastProducts.length === 0) {
            console.warn('Cannot show selector - missing elements or no products');
            return;
        }

        // Collect ALL attributes from all products
        const attributesMap = new Map();

        lastProducts.forEach(product => {
            console.log('Processing product:', product.product_id, product.title);
            if (product && product.variations) {
                console.log('  - has', product.variations.length, 'variations');
                product.variations.forEach(v => {
                    if (v.attributes && Array.isArray(v.attributes)) {
                        console.log('    - variation has', v.attributes.length, 'attributes');
                        v.attributes.forEach(attr => {
                            if (!attributesMap.has(attr.key)) {
                                attributesMap.set(attr.key, {
                                    key: attr.key,
                                    label: attr.label,
                                    values: new Set()
                                });
                            }
                            attributesMap.get(attr.key).values.add(attr.value);
                        });
                    }
                });
            }
        });

        console.log('Total unique attributes found:', attributesMap.size);

        // Render attribute selectors
        attributesDiv.innerHTML = '';

        if (attributesMap.size === 0) {
            attributesDiv.innerHTML = '<p style="color:#999; font-style:italic;">No attributes found in the displayed products.</p>';
            return;
        }

        attributesMap.forEach((attrData, attrKey) => {
            console.log('Creating selector for attribute:', attrKey, 'with', attrData.values.size, 'values');

            const attrDiv = document.createElement('div');
            attrDiv.style.marginBottom = '15px';
            attrDiv.style.display = 'flex';
            attrDiv.style.alignItems = 'center';

            const label = document.createElement('label');
            label.textContent = attrData.label + ': ';
            label.style.display = 'inline-block';
            label.style.minWidth = '150px';
            label.style.fontWeight = 'bold';
            attrDiv.appendChild(label);

            const select = document.createElement('select');
            select.dataset.attributeKey = attrKey;
            select.style.minWidth = '250px';
            select.style.minHeight = '34px';

            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = '— No change —';
            select.appendChild(defaultOption);

            Array.from(attrData.values).sort().forEach(value => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                select.appendChild(option);
            });

            attrDiv.appendChild(select);
            attributesDiv.appendChild(attrDiv);
        });

        console.log('Attribute selectors rendered successfully');
    }

    function updateSelectionCount() {
        const selected = Array.from(document.querySelectorAll('.wbv-select-product-default:checked'));
        const descriptionElem = qs('.wbv-selection-description');

        if (!descriptionElem) return;

        if (selected.length === 0) {
            descriptionElem.textContent = '⚠️  No products selected. Check the boxes above to select products.';
            descriptionElem.style.color = '#d63638';
        } else {
            descriptionElem.textContent = `✓ ${selected.length} product(s) selected for default attribute update`;
            descriptionElem.style.color = '#00a32a';
        }
    }

    function collectDefaultsFromUI() {
        const selects = document.querySelectorAll('#wbv-defaults-attributes select');
        const defaults = {};

        selects.forEach(select => {
            const key = select.dataset.attributeKey;
            const value = select.value;
            if (value) {
                defaults[key] = value;
            }
        });

        return defaults;
    }

    async function handleDefaultsPreview() {
        const selected = Array.from(document.querySelectorAll('.wbv-select-product-default:checked'));
        if (selected.length === 0) {
            alert('Please select at least one product');
            return;
        }

        const defaults = collectDefaultsFromUI();
        if (Object.keys(defaults).length === 0) {
            alert('Please select at least one default attribute value');
            return;
        }

        const product_ids = selected.map(cb => parseInt(cb.dataset.productId));

        try {
            const res = await fetch(`${restRoot}/set-defaults`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({
                    product_ids: product_ids,
                    defaults: defaults,
                    dry_run: true
                })
            });

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.message || 'Preview failed');
            }

            renderPreview(data.preview, product_ids.length);
        } catch (e) {
            alert('Preview error: ' + e.message);
        }
    }

    async function handleDefaultsApply() {
        const selected = Array.from(document.querySelectorAll('.wbv-select-product-default:checked'));
        if (selected.length === 0) {
            alert('Please select at least one product');
            return;
        }

        const defaults = collectDefaultsFromUI();
        if (Object.keys(defaults).length === 0) {
            alert('Please select at least one default attribute value');
            return;
        }

        const operationLabel = qs('#wbv-defaults-operation-label') ? qs('#wbv-defaults-operation-label').value.trim() : '';
        const product_ids = selected.map(cb => parseInt(cb.dataset.productId));

        if (!confirm(`Apply default attributes to ${product_ids.length} product(s)?`)) {
            return;
        }

        try {
            const res = await fetch(`${restRoot}/set-defaults`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({
                    product_ids: product_ids,
                    defaults: defaults,
                    dry_run: false,
                    operation_label: operationLabel
                })
            });

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.message || 'Update failed');
            }

            if (data.status === 'scheduled') {
                alert(`✓ Update scheduled for ${data.total} products in the background. Operation ID: ${data.operation_id}`);
            } else if (data.status === 'completed') {
                alert(`✓ Successfully updated ${data.updated || product_ids.length} products!`);
            }

            // Refresh search
            doSearch(1);
        } catch (e) {
            alert('Update error: ' + e.message);
        }
    }

    function renderPreview(preview, totalSelected) {
        const previewDiv = qs('#wbv-defaults-preview');
        if (!previewDiv) return;

        previewDiv.innerHTML = '';

        if (!preview || preview.length === 0) {
            previewDiv.innerHTML = '<p>No changes to preview</p>';
            return;
        }

        const heading = document.createElement('h3');
        heading.textContent = 'Preview: Default Attributes Changes';
        previewDiv.appendChild(heading);

        const previewLimit = 50;
        if (totalSelected > previewLimit) {
            const warning = document.createElement('div');
            warning.style.padding = '10px';
            warning.style.background = '#fff3cd';
            warning.style.border = '1px solid #ffc107';
            warning.style.borderRadius = '4px';
            warning.style.marginBottom = '15px';
            warning.innerHTML = `<strong>⚠️ Preview Limited:</strong> Showing first ${previewLimit} of ${totalSelected} selected products. All ${totalSelected} products will be updated when you click "Apply Changes".`;
            previewDiv.appendChild(warning);
        }

        const table = document.createElement('table');
        table.style.width = '100%';
        table.style.borderCollapse = 'collapse';

        const thead = document.createElement('thead');
        thead.innerHTML = '<tr><th style="text-align:left; padding:8px; border:1px solid #ddd;">Product</th><th style="text-align:left; padding:8px; border:1px solid #ddd;">Old Defaults</th><th style="text-align:left; padding:8px; border:1px solid #ddd;">New Defaults</th></tr>';
        table.appendChild(thead);

        const tbody = document.createElement('tbody');

        preview.forEach(item => {
            const tr = document.createElement('tr');

            const tdProduct = document.createElement('td');
            tdProduct.textContent = item.product_name;
            tdProduct.style.padding = '8px';
            tdProduct.style.border = '1px solid #ddd';
            tr.appendChild(tdProduct);

            const tdOld = document.createElement('td');
            tdOld.textContent = formatDefaults(item.old_defaults);
            tdOld.style.padding = '8px';
            tdOld.style.border = '1px solid #ddd';
            tr.appendChild(tdOld);

            const tdNew = document.createElement('td');
            tdNew.textContent = formatDefaults(item.new_defaults);
            tdNew.style.padding = '8px';
            tdNew.style.border = '1px solid #ddd';
            tdNew.style.fontWeight = 'bold';
            tdNew.style.color = '#2271b1';
            tr.appendChild(tdNew);

            tbody.appendChild(tr);
        });

        table.appendChild(tbody);
        previewDiv.appendChild(table);
    }

    function formatDefaults(defaults) {
        if (!defaults || Object.keys(defaults).length === 0) {
            return 'None';
        }
        return Object.entries(defaults).map(([key, value]) => {
            const label = key.replace(/^pa_/, '').replace(/_/g, ' ');
            return `${label}: ${value}`;
        }).join(', ');
    }

    let currentPage = 1;

    async function doSearch(page) {
        const search = qs('#wbv-defaults-search');
        const per = qs('#wbv-defaults-per-page');
        const results = qs('#wbv-defaults-results');

        const q = search.value.trim();
        const perPage = per.value;

        console.log('Searching for:', q, 'per page:', perPage, 'page:', page);

        results.innerHTML = '<p>Searching…</p>';

        try {
            const data = await performSearch(q, perPage, page);
            console.log('Search response:', data);
            console.log('Products returned:', data.products ? data.products.length : 0);

            lastProducts = data.products || [];
            renderProductsForDefaults(results, lastProducts);
        } catch (e) {
            console.error('Search error:', e);
            results.innerHTML = `<p style="color:#d63638;">${e.message}</p>`;
        }
    }

    function setupHandlers() {
        const btn = qs('#wbv-defaults-search-btn');
        const search = qs('#wbv-defaults-search');
        const previewBtn = qs('#wbv-defaults-preview-btn');
        const applyBtn = qs('#wbv-defaults-apply-btn');
        const selectAllCheckbox = qs('#wbv-defaults-select-all');

        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                currentPage = 1;
                doSearch(currentPage);
            });
        }

        if (search) {
            search.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    currentPage = 1;
                    doSearch(currentPage);
                }
            });
        }

        if (previewBtn) {
            previewBtn.addEventListener('click', function (e) {
                e.preventDefault();
                handleDefaultsPreview();
            });
        }

        if (applyBtn) {
            applyBtn.addEventListener('click', function (e) {
                e.preventDefault();
                handleDefaultsApply();
            });
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                const checkboxes = document.querySelectorAll('.wbv-select-product-default');
                checkboxes.forEach(cb => {
                    cb.checked = selectAllCheckbox.checked;
                });
                updateSelectionCount();
            });
        }

        // Delegate change event for product checkboxes
        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('wbv-select-product-default')) {
                updateSelectionCount();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupHandlers();
    });

})();
