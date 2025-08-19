// Arquivo: assets/js/add_food_logic.js
// VERSÃO FINAL COM UNIDADES DE MEDIDA DINÂMICAS E SCANNER

document.addEventListener('DOMContentLoaded', function() {

    // --- SELEÇÃO DE ELEMENTOS DOM ---
    const logDateDisplay = document.getElementById('log_date_display');
    const logMealTypeDisplay = document.getElementById('log_meal_type_display');
    const logDateHidden = document.getElementById('log_date_hidden_for_meal');
    const logMealTypeHidden = document.getElementById('log_meal_type_hidden_for_meal');
    const logEntireMealForm = document.getElementById('log-entire-meal-form');
    const searchInput = document.getElementById('food-search-input');
    const resultsContainer = document.getElementById('search-results-container');
    const selectedFoodContainer = document.getElementById('selected-food-details-container');
    const currentMealListUL = document.getElementById('current-meal-items-list');
    const currentMealTotalKcalSpan = document.getElementById('current-meal-total-kcal');
    
    const scanBarcodeBtn = document.getElementById('scan-barcode-btn');
    const scannerModal = document.getElementById('barcode-scanner-modal');
    const closeScannerModalBtn = document.getElementById('close-scanner-modal-btn');
    const scannerContainer = document.getElementById('scanner-container');
    const scannerStatus = document.getElementById('scanner-status');

    const notFoundModal = document.getElementById('product-not-found-modal');
    const closeNotFoundModalBtn = notFoundModal?.querySelector('[data-close-modal="product-not-found-modal"]');
    const takeNutritionPhotoBtn = document.getElementById('take-nutrition-photo-btn');
    const chooseNutritionPhotoBtn = document.getElementById('choose-nutrition-photo-btn');
    
     const cancelButton = document.getElementById('cancel-log-entire-meal-btn');

    // Verifica se o botão realmente existe na página antes de adicionar o evento
    if (cancelButton) {
        // Adiciona um "ouvinte" que espera por um clique no botão
        cancelButton.addEventListener('click', function() {
            // Quando o botão for clicado, executa esta ação:
            // Volta para a página anterior no histórico do navegador.
            window.history.back();
        });
    }

    // --- VARIÁVEIS DE ESTADO ---
    let currentSelectedFoodData = null;
    let mealItems = [];
    let searchDebounceTimer;
    let quaggaScannerInitialized = false;
    let fileInputForNutrition;

    // --- DEFINIÇÕES E FUNÇÕES DE LÓGICA ---

    // Dicionário com os grupos de unidades de medida
    const unitGroups = {
        weight: { 'g': 'Grama (g)', 'kg': 'Quilograma (kg)' },
        volume: { 'ml': 'Mililitro (ml)', 'l': 'Litro (L)', 'tablespoon': 'Colher de Sopa', 'teaspoon': 'Colher de Chá', 'cup': 'Xícara' },
        unit: { 'unit': 'Unidade', 'slice': 'Fatia', 'piece': 'Pedaço' }
    };

    // Dicionário com as conversões aproximadas para gramas (se for peso/unidade) ou ml (se for volume)
    const unitConversions = {
        'g': 1, 'kg': 1000, 'ml': 1, 'l': 1000,
        'tablespoon': 15, 'teaspoon': 5, 'cup': 240,
        'slice': 25, 'unit': 150, 'piece': 50
    };

    function handleFoodSelection(foodData) {
        currentSelectedFoodData = foodData;
        
        // Determina qual grupo de unidades usar, com 'weight' como padrão
        const measureType = foodData.measure_type || 'weight';
        let availableUnits = {};

        if (measureType === 'weight') {
            availableUnits = { ...unitGroups.weight, ...unitGroups.unit };
        } else if (measureType === 'volume') {
            availableUnits = { ...unitGroups.volume, ...unitGroups.unit };
        } else { // 'unit'
            availableUnits = unitGroups.unit;
        }

        let unitOptionsHTML = '';
        for (const [value, text] of Object.entries(availableUnits)) {
            const isSelected = (foodData.serving_unit_default && foodData.serving_unit_default.toLowerCase() === value);
            unitOptionsHTML += `<option value="${value}" ${isSelected ? 'selected' : ''}>${text}</option>`;
        }
        
        selectedFoodContainer.innerHTML = `
            <h3>${foodData.name} ${foodData.brand && foodData.brand.toUpperCase() !== 'TACO' ? `(${foodData.brand})` : ''}</h3>
            <div class="quantity-unit-row">
                <div class="form-group">
                    <label for="food-quantity">Quantidade</label>
                    <input type="number" id="food-quantity" class="form-control" value="100" min="1" step="any">
                </div>
                <div class="form-group">
                    <label for="food-unit">Medida</label>
                    <select id="food-unit" class="form-control">${unitOptionsHTML}</select>
                </div>
            </div>
            <div class="macros-preview">
                <p>Calorias <span><span id="macro-kcal">0</span> kcal</span></p>
                <p>Carboidratos <span><span id="macro-carbs">0</span> g</span></p>
                <p>Proteínas <span><span id="macro-protein">0</span> g</span></p>
                <p>Gorduras <span><span id="macro-fat">0</span> g</span></p>
            </div>
            <div class="form-actions-details">
                <button type="button" id="add-food-item-to-meal-btn" class="btn btn-primary">Adicionar Alimento</button>
                <button type="button" id="cancel-add-food-item-btn" class="btn-icon-cancel" aria-label="Cancelar"><i class="fas fa-times"></i></button>
            </div>
        `;
        
        document.getElementById('food-quantity').addEventListener('input', updateMacrosPreview);
        document.getElementById('food-unit').addEventListener('change', updateMacrosPreview);
        document.getElementById('add-food-item-to-meal-btn').addEventListener('click', addFoodToMeal);
        document.getElementById('cancel-add-food-item-btn').addEventListener('click', () => {
            selectedFoodContainer.classList.remove('visible');
            setTimeout(() => selectedFoodContainer.innerHTML = '', 400);
        });
        
        resultsContainer.style.display = 'none';
        searchInput.value = '';
        updateMacrosPreview();
        setTimeout(() => selectedFoodContainer.classList.add('visible'), 10);
    }

    function updateMacrosPreview() {
        if (!currentSelectedFoodData) return;
        const quantityInput = document.getElementById('food-quantity');
        const unitSelect = document.getElementById('food-unit');
        if (!quantityInput || !unitSelect) return;

        const quantity = parseFloat(quantityInput.value) || 0;
        const unitKey = unitSelect.value;
        
        const conversionFactor = unitConversions[unitKey] || 1;
        const totalBaseAmount = quantity * conversionFactor;
        const nutrientFactor = totalBaseAmount / 100;

        document.getElementById('macro-kcal').textContent = Math.round((currentSelectedFoodData.kcal_100g || 0) * nutrientFactor);
        document.getElementById('macro-protein').textContent = ((currentSelectedFoodData.protein_100g || 0) * nutrientFactor).toFixed(1);
        document.getElementById('macro-carbs').textContent = ((currentSelectedFoodData.carbs_100g || 0) * nutrientFactor).toFixed(1);
        document.getElementById('macro-fat').textContent = ((currentSelectedFoodData.fat_100g || 0) * nutrientFactor).toFixed(1);
    }
    
    function addFoodToMeal() {
        if (!currentSelectedFoodData) return;
        const quantityInput = document.getElementById('food-quantity');
        const unitSelect = document.getElementById('food-unit');
        
        const quantity = parseFloat(quantityInput.value) || 0;
        if (quantity <= 0) {
            alert("Por favor, insira uma quantidade válida.");
            return;
        }

        mealItems.push({
            id: currentSelectedFoodData.id,
            name: currentSelectedFoodData.name,
            brand: currentSelectedFoodData.brand,
            quantity: quantity,
            unit: unitSelect.options[unitSelect.selectedIndex].text,
            kcal: parseFloat(document.getElementById('macro-kcal').textContent),
            protein: parseFloat(document.getElementById('macro-protein').textContent),
            carbs: parseFloat(document.getElementById('macro-carbs').textContent),
            fat: parseFloat(document.getElementById('macro-fat').textContent)
        });

        renderCurrentMealList();
        selectedFoodContainer.classList.remove('visible');
        setTimeout(() => selectedFoodContainer.innerHTML = '', 400);
    }
    
    function renderCurrentMealList() {
        if (!currentMealListUL) return;
        currentMealListUL.innerHTML = '';
        let totalKcal = 0;

        if (mealItems.length === 0) {
            currentMealListUL.innerHTML = '<li class="empty-meal-placeholder">Nenhum alimento adicionado ainda.</li>';
        } else {
            mealItems.forEach((item, index) => {
                const listItem = document.createElement('li');
                listItem.innerHTML = `
                    <div class="meal-item-info">
                        <span class="meal-item-name">${item.name}</span>
                        <span class="meal-item-details">${item.quantity} ${item.unit}</span>
                    </div>
                    <div class="meal-item-calories">
                        <span>${Math.round(item.kcal)}</span>kcal
                    </div>
                    <button type="button" class="btn-remove-item" data-index="${index}" aria-label="Remover item">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                currentMealListUL.appendChild(listItem);
                totalKcal += isNaN(item.kcal) ? 0 : item.kcal;
            });
        }

        if (currentMealTotalKcalSpan) {
            currentMealTotalKcalSpan.textContent = Math.round(totalKcal);
        }
        document.getElementById('save-entire-meal-btn').disabled = (mealItems.length === 0);
    }
    
    function performFoodSearch() {
        const term = searchInput.value.trim();
        if (term.length < 2) return;

        resultsContainer.innerHTML = '<p class="loading-results">Buscando...</p>';
        resultsContainer.style.display = 'block';
        selectedFoodContainer.classList.remove('visible');

        if (typeof BASE_APP_URL === 'undefined') {
            console.error("Variável global BASE_APP_URL não foi encontrada.");
            resultsContainer.innerHTML = '<p class="error-results">Erro de configuração.</p>';
            return;
        }

        const apiUrl = `${BASE_APP_URL}/api/ajax_search_food.php?term=${encodeURIComponent(term)}`;
        
        fetch(apiUrl)
            .then(response => {
                if (!response.ok) throw new Error(`Erro de rede: ${response.status}`);
                return response.json();
            })
            .then(data => {
                resultsContainer.innerHTML = '';
                if (data.success && data.data.length > 0) {
                    const ul = document.createElement('ul');
                    ul.className = 'search-results-list';
                    data.data.forEach(food => {
                        const li = document.createElement('li');
                        li.textContent = `${food.name} ${food.brand && food.brand.toUpperCase() !== 'TACO' ? `(${food.brand})` : ''}`;
                        li.dataset.foodData = JSON.stringify(food);
                        ul.appendChild(li);
                    });
                    resultsContainer.appendChild(ul);
                } else {
                    resultsContainer.innerHTML = `<p class="no-results">${data.message || 'Nenhum alimento encontrado.'}</p>`;
                }
            })
            .catch(error => {
                console.error('Erro na busca de alimentos:', error);
                resultsContainer.innerHTML = '<p class="error-results">Não foi possível buscar. Tente novamente.</p>';
            });
    }
    
    // --- LÓGICA DO SCANNER E OCR (INTOCADO) ---
    
    function onScanSuccess(decodedText) {
        if (!quaggaScannerInitialized) return;
        stopScanner();
        scannerStatus.textContent = `Código [${decodedText}] lido! Buscando...`;
        const apiUrl = `https://world.openfoodfacts.org/api/v2/product/${decodedText}.json?fields=product_name_pt,product_name,brands,nutriments,code`;
        fetch(apiUrl)
            .then(response => { if (!response.ok) throw new Error('Erro de rede'); return response.json(); })
            .then(data => {
                if (data.status === 1 && data.product && data.product.product_name) {
                    const p = data.product;
                    const n = p.nutriments || {};
                    const k = n['energy-kcal_100g'] ?? (n['energy_100g'] ? n['energy_100g'] / 4.184 : null);
                    const foodData = {
                        id: 'off_' + (p.code || decodedText),
                        name: p.product_name_pt || p.product_name,
                        brand: p.brands || 'N/A',
                        measure_type: 'weight', // OFF sempre retorna dados por 100g/100ml
                        serving_unit_default: 'g',
                        kcal_100g: k ? Math.round(k) : 0,
                        protein_100g: n.proteins_100g || 0,
                        carbs_100g: n.carbohydrates_100g || 0,
                        fat_100g: n.fat_100g || 0,
                    };
                    closeScannerModal();
                    handleFoodSelection(foodData);
                } else {
                    closeScannerModal();
                    if (notFoundModal) notFoundModal.style.display = 'flex';
                }
            })
            .catch(error => { console.error('Erro API OpenFoodFacts:', error); closeScannerModal(); alert("Não foi possível buscar o produto."); });
    }

    function startScanner() {
        if (typeof Quagga === 'undefined') {
            if (scannerStatus) scannerStatus.textContent = 'Erro: Biblioteca não carregada.';
            return;
        }

        Quagga.init({
            inputStream: { name: "Live", type: "LiveStream", target: scannerContainer, constraints: { width: 640, height: 480, facingMode: "environment" } },
            decoder: { readers: ["ean_reader"] },
            locate: true,
            numOfWorkers: 2,
        }, (err) => {
            if (err) { console.error("Erro ao iniciar Quagga:", err); if (scannerStatus) scannerStatus.textContent = 'Erro ao acessar câmera.'; return; }
            Quagga.start();
            quaggaScannerInitialized = true;
            if (scannerStatus) scannerStatus.textContent = "Aponte para o código de barras";
        });

        let detectedCodes = [];
        const requiredDetections = 5;

        Quagga.onDetected(result => {
            if (!quaggaScannerInitialized) return;
            const code = result.codeResult.code;
            if (!code || code.length < 12 || code.length > 13) return;
            detectedCodes.push(code);
            if (detectedCodes.length > 20) {
                const codeFrequency = detectedCodes.reduce((acc, val) => { acc[val] = (acc[val] || 0) + 1; return acc; }, {});
                const sortedCodes = Object.keys(codeFrequency).sort((a, b) => codeFrequency[b] - codeFrequency[a]);
                if (sortedCodes.length > 0 && codeFrequency[sortedCodes[0]] >= requiredDetections) {
                    onScanSuccess(sortedCodes[0]);
                }
                detectedCodes = [];
            }
        });

        Quagga.onProcessed(result => {
            const drawingCtx = Quagga.canvas.ctx.overlay;
            const drawingCanvas = Quagga.canvas.dom.overlay;
            drawingCtx.clearRect(0, 0, parseInt(drawingCanvas.getAttribute("width")), parseInt(drawingCanvas.getAttribute("height")));
            if (result && result.box) {
                Quagga.ImageDebug.drawPath(result.box, { x: 0, y: 1 }, drawingCtx, { color: "#4CAF50", lineWidth: 4 });
            }
        });
    }

    function stopScanner() { if (typeof Quagga !== 'undefined' && quaggaScannerInitialized) { Quagga.offDetected(); Quagga.offProcessed(); Quagga.stop(); quaggaScannerInitialized = false; } }
    function openScannerModal() { if (scannerModal) { scannerModal.style.display = 'flex'; startScanner(); } }
    function closeScannerModal() { if (scannerModal) { stopScanner(); scannerModal.style.display = 'none'; } }
    
    function createFileInput() {
        if (fileInputForNutrition) return;
        fileInputForNutrition = document.createElement('input');
        fileInputForNutrition.type = 'file';
        fileInputForNutrition.accept = 'image/*';
        fileInputForNutrition.style.display = 'none';
        document.body.appendChild(fileInputForNutrition);
        fileInputForNutrition.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) handleImageUpload(file);
        });
    }
    
    function handleImageUpload(file) {
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'loading-overlay-fullscreen';
        loadingOverlay.innerHTML = '<div class="loader"></div><p>Analisando imagem...</p>';
        document.body.appendChild(loadingOverlay);
        if (notFoundModal) notFoundModal.style.display = 'none';
        setTimeout(() => { loadingOverlay.remove(); alert("Análise da imagem concluída (simulação)!"); }, 4000);
    }

    // --- EVENT LISTENERS GERAIS (INTOCADO) ---
    logDateDisplay?.addEventListener('change', () => { if (logDateHidden) logDateHidden.value = logDateDisplay.value; });
    logMealTypeDisplay?.addEventListener('change', () => { if (logMealTypeHidden) logMealTypeHidden.value = logMealTypeDisplay.value; });
    searchInput?.addEventListener('input', () => { clearTimeout(searchDebounceTimer); searchDebounceTimer = setTimeout(performFoodSearch, 400); });
    resultsContainer?.addEventListener('click', (e) => { const li = e.target.closest('li'); if (li?.dataset.foodData) handleFoodSelection(JSON.parse(li.dataset.foodData)); });
    currentMealListUL?.addEventListener('click', (e) => { const removeBtn = e.target.closest('.btn-remove-item'); if (removeBtn) { mealItems.splice(parseInt(removeBtn.dataset.index, 10), 1); renderCurrentMealList(); } });
    logEntireMealForm?.addEventListener('submit', (e) => { if (mealItems.length === 0) { e.preventDefault(); alert('Adicione pelo menos um alimento.'); return; } let hiddenInput = logEntireMealForm.querySelector('input[name="meal_items_json"]'); if (!hiddenInput) { hiddenInput = document.createElement('input'); hiddenInput.type = 'hidden'; hiddenInput.name = 'meal_items_json'; logEntireMealForm.appendChild(hiddenInput); } hiddenInput.value = JSON.stringify(mealItems); });
    scanBarcodeBtn?.addEventListener('click', openScannerModal);
    closeScannerModalBtn?.addEventListener('click', closeScannerModal);
    scannerModal?.addEventListener('click', (e) => { if (e.target === scannerModal) closeScannerModal(); });
    closeNotFoundModalBtn?.addEventListener('click', () => { if (notFoundModal) notFoundModal.style.display = 'none'; });
    createFileInput();
    takeNutritionPhotoBtn?.addEventListener('click', () => { if (fileInputForNutrition) { fileInputForNutrition.setAttribute('capture', 'environment'); fileInputForNutrition.click(); } });
    chooseNutritionPhotoBtn?.addEventListener('click', () => { if (fileInputForNutrition) { fileInputForNutrition.removeAttribute('capture'); fileInputForNutrition.click(); } });

    // Inicialização da página
    renderCurrentMealList();
});