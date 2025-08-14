// =========================================================================
//                  SHAPEFIT - SCRIPT.JS (VERSÃO FINAL E UNIFICADA)
// =========================================================================

document.addEventListener('DOMContentLoaded', function () {
    console.log('script.js vFinal carregado e executando.');

    // Bloco 0: Funções Globais de Ajuda
    window.showSinglePopup = function(points, eventType = 'gain') {
        if (points === 0) return;
        const popup = document.createElement('div');
        popup.className = 'points-popup';
        popup.classList.add(eventType);
        const absPoints = Math.abs(points);
        let message = '';
        if (eventType === 'bonus') { message = `+${absPoints} PONTOS BÔNUS!`; }
        else if (eventType === 'loss') { message = `-${absPoints} Pontos`; }
        else { message = `+${absPoints} Pontos`; }
        const iconHTML = `<img src="https://i.ibb.co/8LXQt0Xy/POINTS.webp" alt="Pontos">`;
        popup.innerHTML = iconHTML + `<span>${message}</span>`;
        document.body.appendChild(popup);
        setTimeout(() => popup.remove(), 3000);
    }
    window.showAppNotification = function(message, type = 'info') { // <-- ÚNICA ALTERAÇÃO NECESSÁRIA
        document.querySelector('.app-notification-popup')?.remove();
        const popup = document.createElement('div');
        popup.className = `app-notification-popup ${type}`;
        let iconClass = 'fa-info-circle';
        if (type === 'success') iconClass = 'fa-check-circle';
        if (type === 'error') iconClass = 'fa-exclamation-triangle';
        popup.innerHTML = `<i class="fas ${iconClass}"></i><span>${message}</span>`;
        document.body.appendChild(popup);
        setTimeout(() => popup.remove(), 4000);
    }
    window.updateUserPointsDisplay = function(newTotal) { // <-- ALTERE AQUI TAMBÉM
        const pointsDisplay = document.getElementById('user-points-display');
        if (pointsDisplay) {
            const totalAsNumber = Number(newTotal);
            let formattedTotal = (totalAsNumber % 1 === 0)
                ? totalAsNumber.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
                : totalAsNumber.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
            pointsDisplay.textContent = formattedTotal;
        }
    }
    const csrfToken = document.getElementById('csrf_token_main_app')?.value;

    // =========================================================================
    // Bloco 1: LÓGICA DE SELEÇÃO DE OPÇÕES UNIFICADA (A GRANDE CORREÇÃO)
    // =========================================================================
    
    document.querySelectorAll('.selectable-option').forEach(label => {
        const input = label.querySelector('input[type="radio"], input[type="checkbox"]');
        if (!input) return;

        if (input.checked) {
            label.classList.add('selected');
        }

        label.addEventListener('click', function (event) {
            event.preventDefault();
            const clickedInput = this.querySelector('input[type="radio"], input[type="checkbox"]');
            if (!clickedInput) return;

            if (clickedInput.type === 'radio') {
                if (!clickedInput.checked) {
                    clickedInput.checked = true;
                    document.querySelectorAll(`input[type="radio"][name="${clickedInput.name}"]`).forEach(otherRadio => {
                        otherRadio.closest('.selectable-option')?.classList.remove('selected');
                    });
                    label.classList.add('selected');
                }
            } else if (clickedInput.type === 'checkbox') {
                clickedInput.checked = !clickedInput.checked;
                label.classList.toggle('selected', clickedInput.checked);
                if (label.closest('#restriction-form')) {
                    runRestrictionLogic(clickedInput.dataset.slug);
                }
            }
        });
    });

    const restrictionForm = document.getElementById('restriction-form');
    if (restrictionForm) {
        const checkboxes = {
            vegan: restrictionForm.querySelector('input[data-slug="vegan"]'),
            vegetarian: restrictionForm.querySelector('input[data-slug="vegetarian"]'),
            pescetarian: restrictionForm.querySelector('input[data-slug="pescetarian"]'),
            no_eggs: restrictionForm.querySelector('input[data-slug="no_eggs"]'),
            no_dairy: restrictionForm.querySelector('input[data-slug="dairy_lactose_free"]'),
            no_fish: restrictionForm.querySelector('input[data-slug="no_fish_seafood"]')
        };

        function setRestrictionState(slug, shouldBeChecked) {
            const checkbox = checkboxes[slug];
            if (checkbox && checkbox.checked !== shouldBeChecked) {
                checkbox.checked = shouldBeChecked;
                checkbox.closest('.selectable-option')?.classList.toggle('selected', shouldBeChecked);
            }
        }

        function runRestrictionLogic(clickedSlug) {
            const isNowChecked = checkboxes[clickedSlug]?.checked;
            if (isNowChecked) {
                if (clickedSlug === 'vegan') {
                    setRestrictionState('vegetarian', false); setRestrictionState('pescetarian', false);
                    setRestrictionState('no_eggs', true); setRestrictionState('no_dairy', true); setRestrictionState('no_fish', true);
                } else if (clickedSlug === 'vegetarian') {
                    setRestrictionState('vegan', false); setRestrictionState('pescetarian', false);
                    setRestrictionState('no_fish', true);
                } else if (clickedSlug === 'pescetarian') {
                    setRestrictionState('vegan', false); setRestrictionState('vegetarian', false);
                }
            } else {
                if ((clickedSlug === 'no_eggs' || clickedSlug === 'no_dairy') && checkboxes.vegan?.checked) { setRestrictionState('vegan', false); }
                if (clickedSlug === 'no_fish' && (checkboxes.vegan?.checked || checkboxes.vegetarian?.checked)) { setRestrictionState('vegan', false); setRestrictionState('vegetarian', false); }
            }
        }
    }
    
    const phoneInputGroup = document.querySelector('.phone-input-group'); 
    if (phoneInputGroup) { const phoneInputs = phoneInputGroup.querySelectorAll('input[type="tel"], input[name="phone_prefix"]'); phoneInputs.forEach(input => { input.addEventListener('focus', () => phoneInputGroup.classList.add('focused')); input.addEventListener('blur', () => { setTimeout(() => { if (!Array.from(phoneInputs).some(el => el === document.activeElement)) { phoneInputGroup.classList.remove('focused'); } }, 0); }); }); }
    
    const ufSelectEl = document.getElementById('uf_select'); 
    const citySelectEl = document.getElementById('city_select'); 
    async function loadCitiesForUf(ufValue, cityToSelect) { if (!ufSelectEl || !citySelectEl) return; citySelectEl.disabled = true; if (!ufValue) { citySelectEl.innerHTML = '<option value="">Selecione um estado</option>'; return; } citySelectEl.innerHTML = '<option value="">Carregando...</option>'; try { const response = await fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${ufValue}/municipios?orderBy=nome`); if (!response.ok) throw new Error('API IBGE falhou'); const cities = await response.json(); citySelectEl.innerHTML = '<option value="">Selecione a cidade</option>'; cities.forEach(city => { const option = new Option(city.nome, city.nome); if (cityToSelect && city.nome.toLowerCase() === cityToSelect.toLowerCase()) option.selected = true; citySelectEl.add(option); }); citySelectEl.disabled = false; } catch (error) { console.error("Erro ao carregar cidades:", error); citySelectEl.innerHTML = '<option value="">Erro ao carregar</option>'; } } 
    if (ufSelectEl && citySelectEl) { ufSelectEl.addEventListener('change', () => loadCitiesForUf(ufSelectEl.value, null)); if (ufSelectEl.value) { const cityToPreselect = document.getElementById('city_selected_on_load')?.value; loadCitiesForUf(ufSelectEl.value, cityToPreselect); } }
    
    const dobInputEl = document.getElementById('dob_input'); 
    if (dobInputEl) { const updateDobInputAppearance = () => { dobInputEl.type = (dobInputEl.value) ? 'date' : 'text'; }; updateDobInputAppearance(); dobInputEl.addEventListener('focus', () => dobInputEl.type = 'date'); dobInputEl.addEventListener('blur', updateDobInputAppearance); }
    
    // =========================================================================
    // Bloco 2: Lógica Específica da Página Principal (main_app.php)
    // =========================================================================
    const mainAppContainer = document.querySelector('.main-app-container');
    const routineSectionV3 = document.querySelector('.routine-section-v3');
    if (routineSectionV3) {
        function checkIfMissionsAreDone() {
            const missionsCarousel = routineSectionV3.querySelector('.missions-carousel');
            if (!missionsCarousel) return;
            const remainingMissions = missionsCarousel.querySelectorAll('.mission-card-v3:not(.celebration)');
            if (remainingMissions.length === 0 && !missionsCarousel.querySelector('.celebration')) {
                const celebrationCardHTML = `<div class="mission-card-v3 celebration" style="opacity: 0; transform: scale(0.9);"><div class="mission-icon-v3 celebration-icon"><i class="fas fa-trophy"></i></div><div class="mission-details-v3"><p class="mission-title-v3">Missões de Rotina Concluídas!</p></div></div>`;
                missionsCarousel.innerHTML = celebrationCardHTML;
                setTimeout(() => { const celebrationCard = missionsCarousel.querySelector('.celebration'); if (celebrationCard) { celebrationCard.style.transition = 'opacity 0.5s ease, transform 0.5s ease'; celebrationCard.style.opacity = '1'; celebrationCard.style.transform = 'scale(1)'; } }, 50);
            }
        }
        routineSectionV3.addEventListener('click', function (event) {
            const completeButton = event.target.closest('.mission-complete-button');
            if (!completeButton || completeButton.disabled) return;
            completeButton.disabled = true;
            completeButton.classList.add('is-processing');
            const itemElement = completeButton.closest('.mission-card-v3');
            if (!itemElement) { completeButton.disabled = false; completeButton.classList.remove('is-processing'); return; }
            itemElement.classList.add('is-completing');
            const routineId = itemElement.dataset.routineId;
            const formData = new FormData();
            formData.append('routine_id', routineId);
            formData.append('status', '1');
            formData.append('csrf_token', csrfToken);
            fetch(`${BASE_APP_URL}/api/update_routine_status.php`, { method: 'POST', body: formData })
                .then(response => response.json().then(data => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok) { throw new Error(data.message || 'Erro ao atualizar'); }
                    itemElement.addEventListener('transitionend', () => { itemElement.remove(); checkIfMissionsAreDone(); }, { once: true });
                    if (data.points_awarded > 0) { showSinglePopup(data.points_awarded, 'gain'); }
                    if (data.new_total_points !== undefined) { updateUserPointsDisplay(data.new_total_points); }
                })
                .catch(error => {
                    console.error('Erro ao atualizar rotina:', error);
                    showAppNotification(error.message || 'Não foi possível atualizar a missão.', 'error');
                    itemElement.classList.remove('is-completing');
                    completeButton.classList.remove('is-processing');
                    completeButton.disabled = false;
                });
        });
    }
    if (mainAppContainer) {
        const waterAmountDisplay = document.getElementById('water-amount-display');
        const increaseBtn = document.getElementById('increase-water');
        const decreaseBtn = document.getElementById('decrease-water');
        const waterGoalDisplay = document.getElementById('water-goal-display-total');
        const waterLevelGroup = document.getElementById('water-level-group');

        if (waterAmountDisplay && increaseBtn && decreaseBtn && waterGoalDisplay && waterLevelGroup) {
            const isMobile = window.matchMedia('(max-width: 768px)').matches;
            const config = isMobile ? { waveSpeed: 1.5, waveHeight: 1.2, transition: '0.5s cubic-bezier(0.25, 0.1, 0.25, 1.1)', maxGlow: 8 } : { waveSpeed: 1, waveHeight: 2, transition: '0.8s cubic-bezier(0.25, 0.1, 0.25, 1)', maxGlow: 12 };
            let currentWater = parseInt(waterAmountDisplay.textContent, 10);
            const goalWater = parseInt(waterGoalDisplay.textContent, 10);
            let debounceTimer; let waveAnimationId;
            const updateWaterUI = () => {
                waterAmountDisplay.textContent = currentWater;
                const glassContainer = document.querySelector('.glass-container-svg');
                if (glassContainer) {
                    const percentage = goalWater > 0 ? Math.min(100, (currentWater / goalWater) * 100) : 0;
                    const yPosition = 140 - (140 * (percentage / 100));
                    waterLevelGroup.style.transition = config.transition;
                    waterLevelGroup.setAttribute('transform', `translate(0, ${yPosition})`);
                    if (isMobile) {
                        const glowSize = (percentage / 100) * config.maxGlow;
                        const glowOpacity = percentage / 100 * 0.6;
                        glassContainer.style.filter = `drop-shadow(0 0 ${glowSize}px rgba(52, 152, 219, ${glowOpacity}))`;
                    }
                    if (percentage > 0 && percentage < 100) { animateWaves(); } else { cancelAnimationFrame(waveAnimationId); }
                    glassContainer.classList.toggle('full', percentage >= 100);
                }
            };
            function animateWaves() {
                cancelAnimationFrame(waveAnimationId);
                const waveElements = document.querySelectorAll('#wave1, #wave2');
                let startTime = null;
                const duration = 2000 / config.waveSpeed;
                function waveStep(timestamp) {
                    if (!startTime) startTime = timestamp;
                    const progress = (timestamp - startTime) / duration;
                    const waveOffset = Math.sin(progress * Math.PI * 2) * config.waveHeight;
                    waveElements.forEach(el => { el.setAttribute('transform', `translate(0, ${waveOffset})`); });
                    waveAnimationId = requestAnimationFrame(waveStep);
                }
                waveAnimationId = requestAnimationFrame(waveStep);
            }
            const saveWaterCount = () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const formData = new FormData();
                    formData.append('cups', currentWater);
                    formData.append('csrf_token', csrfToken);
                    fetch(`${BASE_APP_URL}/api/update_water.php`, { method: 'POST', body: formData })
                        .then(response => response.json().then(data => ({ ok: response.ok, data })))
                        .then(({ ok, data }) => {
                            if (!ok) throw new Error(data.message);
                            if (data.events && data.events.length > 0) {
                                data.events.forEach((event, index) => { setTimeout(() => showSinglePopup(event.points, event.type), index * 600); });
                            }
                            if (data.new_total_points !== undefined) { updateUserPointsDisplay(data.new_total_points); }
                        })
                        .catch(err => { console.error('Falha ao salvar água:', err); showAppNotification(err.message, 'error'); });
                }, 700);
            };
            increaseBtn.addEventListener('click', () => { currentWater++; updateWaterUI(); saveWaterCount(); });
            decreaseBtn.addEventListener('click', () => { if (currentWater > 0) { currentWater--; updateWaterUI(); saveWaterCount(); } });
            updateWaterUI();
        }

        const tooltipWrapper = document.querySelector('.info-tooltip-wrapper');
        if (tooltipWrapper) {
            tooltipWrapper.addEventListener('click', (event) => { event.stopPropagation(); tooltipWrapper.classList.toggle('is-active'); });
            document.addEventListener('click', (event) => { if (!tooltipWrapper.contains(event.target)) { tooltipWrapper.classList.remove('is-active'); } });
        }

        document.querySelectorAll('.progress-circle').forEach(circle => {
            const value = parseFloat(circle.dataset.value) || 0;
            const goal = parseFloat(circle.dataset.goal) || 1;
            const circlePath = circle.querySelector('.circle');
            if (circlePath) {
                let percentage = goal > 0 ? (value / goal) * 100 : 0;
                percentage = Math.max(0, Math.min(percentage, 100));
                circlePath.style.strokeDasharray = `${percentage}, 100`;
            }
        });
    }
});