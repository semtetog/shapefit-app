<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// --- CONFIGURAÇÃO INICIAL ---
$user_id = $_SESSION['user_id'];
$current_date = date('Y-m-d');



// --- BUSCA DE DADOS USANDO FUNÇÕES ---
$user_profile_data = getUserProfileData($conn, $user_id);


// =======================================================
// =======================================================
// LÓGICA DO BANNER DE PESO DINÂMICO (VERSÃO SIMPLIFICADA E FINAL)
// =======================================================
$show_edit_button = true; // Por padrão, o botão é mostrado
$days_until_next_weight_update = 0;

try {
    $stmt_last_weight = $conn->prepare("SELECT MAX(date_recorded) AS last_date FROM sf_user_weight_history WHERE user_id = ?");
    if ($stmt_last_weight) {
        $stmt_last_weight->bind_param("i", $user_id);
        $stmt_last_weight->execute();
        $result = $stmt_last_weight->get_result()->fetch_assoc();
        $stmt_last_weight->close();

        // Se existe um último registro
        if ($result && !empty($result['last_date'])) {
            $last_log_date = new DateTime($result['last_date']);
            $unlock_date = (clone $last_log_date)->modify('+7 days'); // Clona e adiciona 7 dias
            $today = new DateTime('today');

            // Se hoje AINDA NÃO É a data de desbloqueio
            if ($today < $unlock_date) {
                // Então o botão de edição NÃO deve ser mostrado
                $show_edit_button = false;
                // E calculamos os dias restantes
                $days_until_next_weight_update = (int)$today->diff($unlock_date)->days;
                if ($days_until_next_weight_update == 0) $days_until_next_weight_update = 1; // Mostra "1 dia" em vez de "0"
            }
        }
    }
} catch (Exception $e) {
    error_log("Erro ao processar data de peso: " . $e->getMessage());
}
// =======================================================

// =======================================================
// LÓGICA FINAL PARA O CARD DE RANKING DINÂMICO
// =======================================================
$ranking_preview_data = [];

// 1. Busca sua posição e seus pontos
$stmt_my_rank = $conn->prepare("SELECT rank, points FROM (SELECT id, points, RANK() OVER (ORDER BY points DESC) as rank FROM sf_users) as r WHERE id = ?");
$stmt_my_rank->bind_param("i", $user_id);
$stmt_my_rank->execute();
$my_rank_result = $stmt_my_rank->get_result()->fetch_assoc();
$my_rank = $my_rank_result['rank'] ?? 'N/A';
$my_points = $my_rank_result['points'] ?? 0;
$ranking_preview_data['my_rank'] = $my_rank;

// 2. Define quem é o oponente
$opponent_rank = null;
$opponent_data = null;
if ($my_rank > 1) {
    // Se não sou o primeiro, meu oponente é quem está uma posição acima
    $opponent_rank = $my_rank - 1;
} else {
    // Se sou o primeiro, meu "oponente" para exibição é o segundo colocado
    $opponent_rank = 2;
}

// 3. Busca os dados do oponente
if ($opponent_rank) {
    $stmt_opponent = $conn->prepare("
        SELECT * FROM (
            SELECT u.id, u.name, u.points, up.profile_image_filename, up.gender, RANK() OVER (ORDER BY u.points DESC) as rank 
            FROM sf_users u LEFT JOIN sf_user_profiles up ON u.id = up.user_id
        ) as ranked_users
        WHERE rank = ? LIMIT 1
    ");
    $stmt_opponent->bind_param("i", $opponent_rank);
    $stmt_opponent->execute();
    $opponent_data = $stmt_opponent->get_result()->fetch_assoc();
    $ranking_preview_data['opponent'] = $opponent_data;
    $stmt_opponent->close();
}

// 4. Calcula a porcentagem do progresso
$user_progress_percentage = 0;
if ($my_rank > 1 && isset($opponent_data['points']) && $opponent_data['points'] > 0) {
    // Progresso em relação ao cara da frente
    $user_progress_percentage = min(100, round(($my_points / $opponent_data['points']) * 100));
} elseif ($my_rank == 1) {
    // Se sou o primeiro, a barra está 100% cheia
    $user_progress_percentage = 100;
}

// Função de ajuda para a foto (pode ser movida para functions.php)
if (!function_exists('getRankingUserProfileImageUrl')) {
    function getRankingUserProfileImageUrl($player_data) {
        if (!empty($player_data['profile_image_filename'])) { return BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($player_data['profile_image_filename']); }
        $gender = strtolower($player_data['gender'] ?? 'male');
        return ($gender === 'female') ? 'https://i.ibb.co/XkpfDjbj/FEMININO.webp' : 'https://i.ibb.co/gLcMfWyn/MASCULINO.webp';
    }
}
// =======================================================

$user_name_parts = explode(' ', $user_profile_data['name']);
$first_name = htmlspecialchars($user_name_parts[0]);
if (empty($_SESSION['user_name'])) {
    $_SESSION['user_name'] = $user_profile_data['name'];
}
// Pega os pontos do usuário a partir dos dados já buscados
$user_points = $user_profile_data['points'] ?? 0;

$age_years = calculateAge($user_profile_data['dob']);
$total_daily_calories_goal = calculateTargetDailyCalories($user_profile_data['gender'], (float)$user_profile_data['weight_kg'], (int)$user_profile_data['height_cm'], $age_years, $user_profile_data['activity_level'], $user_profile_data['objective']);
$macros_goal = calculateMacronutrients($total_daily_calories_goal, $user_profile_data['objective']);
// --- LÓGICA DE HIDRATAÇÃO (NOVO E MELHORADO) ---
$water_goal_data = getWaterIntakeSuggestion((float)$user_profile_data['weight_kg']);
$water_goal_cups = $water_goal_data['cups']; // Pega a meta em copos
$water_goal_ml = $water_goal_data['total_ml']; // Pega a meta em ml
$cup_size_ml = $water_goal_data['cup_size_ml']; // <-- ADICIONE ESTA LINHA

$daily_tracking = getDailyTrackingRecord($conn, $user_id, $current_date);
$water_consumed = $daily_tracking['water_consumed_cups'] ?? 0;
$kcal_consumed = $daily_tracking['kcal_consumed'] ?? 0;
$carbs_consumed = $daily_tracking['carbs_consumed_g'] ?? 0.00;
$protein_consumed = $daily_tracking['protein_consumed_g'] ?? 0.00;
$fat_consumed = $daily_tracking['fat_consumed_g'] ?? 0.00;

$routine_items = getRoutineItemsForUser($conn, $user_id, $current_date);
$routine_todos = [];
$routine_completed = [];

foreach ($routine_items as $item) {
    if ($item['completion_status'] == 1) { // <-- MUDANÇA AQUI: de === '1' para == 1
        $routine_completed[] = $item;
    } else {
        $routine_todos[] = $item;
    }
}


// <-- ADICIONE ESTE BLOCO
$total_missions = count($routine_items);
$completed_missions = count($routine_completed);
$routine_progress_percentage = ($total_missions > 0) ? round(($completed_missions / $total_missions) * 100) : 0;
$meal_suggestion_data = getMealSuggestions($conn);
$calories_by_meal_type = getCaloriesByMealType($conn, $user_id, $current_date);


// --- PREPARAÇÃO PARA O LAYOUT ---
$current_weight_display = number_format((float)$user_profile_data['weight_kg'], 1) . "kg";
$extra_css = ['main_app_specific.css', 'modal.css']; 
$extra_js = ['script.js', 'carousel_logic.js', 'weight_logic.js', 'routine_logic.js'];
$page_title = "Início";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<div class="container main-app-container">
    
    <input type="hidden" id="csrf_token_main_app" value="<?php echo $_SESSION['csrf_token']; ?>">
    
    
    <header class="main-header-app">
        <div class="user-greeting">
            Olá, <span class="user-first-name"><?php echo $first_name; ?></span>!
        </div>
        <div class="header-actions">
             <!-- Contador de Pontos Adicionado -->
             <a href="<?php echo BASE_APP_URL; ?>/points_history.php" class="points-counter-badge">
                <img src="https://i.ibb.co/8LXQt0Xy/POINTS.webp" alt="Pontos">
                <span id="user-points-display">
    <?php 
    // Se o número for inteiro, formata sem decimais. Senão, com 1 decimal.
    echo fmod($user_points, 1) == 0 ? number_format($user_points, 0, ',', '.') : number_format($user_points, 1, ',', '.');
    ?>
</span>
            </a>
            
        </div>
    </header>


<style>
/* ======================================================= */
/*  CSS GERAL DO CARROSSEL - ATUALIZADO E RESPONSIVO       */
/* ======================================================= */
.video-carousel {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    border-radius: 12px;
    overflow: hidden;
    background-color: #1c1c1c;
    margin-bottom: 20px;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    box-shadow: 0 0 0 2px #1c1c1c inset;
    clip-path: inset(0 round 12px);
    transform: translateZ(0);
}

.carousel-container {
    position: absolute;
    width: 100%;
    height: 100%;
    z-index: 1;
    border-radius: 12px;
    overflow: hidden;
}

.video-overlay {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    z-index: 2;
    background-color: transparent;
}

.carousel-video {
    position: absolute;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: translateX(100%);
    transition: transform 0.6s ease-in-out;
    border-radius: 10px;
    z-index: 1;
    -webkit-tap-highlight-color: transparent;
    -webkit-user-select: none;
    -webkit-touch-callout: none;
}

.carousel-video.no-transition { 
    transition: none !important; 
}

.carousel-video.active { 
    transform: translateX(0); 
}

.carousel-video.prev { 
    transform: translateX(-100%); 
}

/* Esconder controles nativos em todos os navegadores */
.carousel-video::-webkit-media-controls,
.carousel-video::-webkit-media-controls-enclosure,
.carousel-video::-webkit-media-controls-panel,
.carousel-video::-webkit-media-controls-play-button,
.carousel-video::-webkit-media-controls-start-playback-button {
    display: none !important;
    opacity: 0 !important;
    pointer-events: none !important;
    visibility: hidden !important;
}

.carousel-video:focus { 
    outline: none; 
    box-shadow: none; 
}

.pagination-container {
    position: absolute;
    bottom: 15px;
    left: 50%;
    transform: translateX(-50%);
    width: 60%; 
    max-width: 180px;
    display: flex;
    gap: 5px;
    height: 4px;
    z-index: 3;
}

.pagination-item {
    flex: 1;
    background: rgba(255, 255, 255, 0.35);
    border-radius: 2px;
    overflow: hidden;
}

.pagination-fill {
    height: 100%;
    width: 0%;
    background: #FF6B00;
    transition: width linear;
}

/* Estilos específicos para iOS */
@supports (-webkit-touch-callout: none) {
    .video-carousel {
        -webkit-perspective: 1000;
        perspective: 1000;
    }
    
    .carousel-video {
        transform: translate3d(100%, 0, 0);
    }
    
    .carousel-video.active {
        transform: translate3d(0, 0, 0);
    }
    
    .carousel-video.prev {
        transform: translate3d(-100%, 0, 0);
    }
}
</style>

<section class="video-carousel">
    <div class="carousel-container">
        <div class="video-overlay"></div>
        <video class="carousel-video active" muted playsinline loop autoplay webkit-playsinline>
            <source src="assets/videos/banners/1.mp4" type="video/mp4">
        </video>
    </div>
    <div class="pagination-container"></div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const videoSources = [
        'assets/videos/banners/1.mp4',
        'assets/videos/banners/2.mp4',
        'assets/videos/banners/3.mp4',
        'assets/videos/banners/4.mp4'
    ];
    
    const mainCarouselElement = document.querySelector('.video-carousel');
    const carouselContainer = document.querySelector('.carousel-container');
    const paginationContainer = document.querySelector('.pagination-container');
    const firstVideo = document.querySelector('.carousel-video');
    
    let currentIndex = 0;
    const duration = 5000;
    const progressFills = [];
    let videoElements = [firstVideo];
    let carouselInterval;
    let isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || 
               (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    let userInteracted = false;

    // 1. Cria a paginação
    function createPagination() {
        videoSources.forEach(() => {
            const item = document.createElement('div');
            item.className = 'pagination-item';
            const fill = document.createElement('div');
            fill.className = 'pagination-fill';
            item.appendChild(fill);
            paginationContainer.appendChild(item);
            progressFills.push(fill);
        });
    }

    // 2. Carrega os vídeos restantes
    function loadRemainingVideos() {
        for (let i = 1; i < videoSources.length; i++) {
            const video = document.createElement('video');
            video.className = 'carousel-video';
            video.muted = true;
            video.playsinline = true;
            video.webkitPlaysinline = true;
            video.setAttribute('x-webkit-airplay', 'deny');
            video.setAttribute('playsinline', 'true');
            video.setAttribute('webkit-playsinline', 'true');
            video.loop = true;
            video.preload = "auto";
            video.setAttribute('disablepictureinpicture', 'true');
            video.setAttribute('disableremoteplayback', 'true');
            
            const source = document.createElement('source');
            source.src = videoSources[i];
            source.type = 'video/mp4';
            video.appendChild(source);
            carouselContainer.appendChild(video);
            videoElements.push(video);
            
            // Previne fullscreen no iOS
            video.addEventListener('webkitbeginfullscreen', (e) => {
                e.preventDefault();
                video.webkitExitFullscreen();
            });
        }
    }

    // 3. Controla a barra de progresso
    function startProgressBarAnimation() {
        progressFills.forEach(fill => {
            fill.style.transition = 'none';
            fill.style.width = '0%';
            void fill.offsetWidth;
        });
        if (progressFills[currentIndex]) {
            progressFills[currentIndex].style.transition = `width ${duration}ms linear`;
            progressFills[currentIndex].style.width = '100%';
        }
    }

    // 4. Troca de vídeo
    function showNextVideo() {
        const currentVideo = videoElements[currentIndex];
        const oldPrevVideo = document.querySelector('.carousel-video.prev');
        if (oldPrevVideo) {
            oldPrevVideo.classList.add('no-transition');
            oldPrevVideo.classList.remove('prev');
            oldPrevVideo.offsetHeight;
            oldPrevVideo.classList.remove('no-transition');
        }

        currentIndex = (currentIndex + 1) % videoElements.length;
        const nextVideo = videoElements[currentIndex];

        currentVideo.classList.remove('active');
        currentVideo.classList.add('prev');
        currentVideo.pause();

        nextVideo.classList.add('active');
        nextVideo.currentTime = 0;
        
        // Tenta dar play no próximo vídeo
        const playPromise = nextVideo.play();
        if (playPromise !== undefined) {
            playPromise.catch(() => {});
        }
        
        startProgressBarAnimation();
    }

    // 5. Lógica de clique nos banners
    function handleCarouselClick(e) {
        // No iOS, a primeira interação desbloqueia os vídeos
        if (isIOS && !userInteracted) {
            e.preventDefault();
            userInteracted = true;
            videoElements.forEach(video => {
                video.play().catch(() => {});
            });
            return;
        }

        // Navegação normal
        switch(currentIndex) {
            case 0:
                window.location.href = 'https://appshapefit.com/explore_recipes.php';
                break;
            case 1:
                const waterCard = document.getElementById('water-card');
                if (waterCard) waterCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                break;
            case 2:
                const routineSection = document.getElementById('routine-section');
                if (routineSection) routineSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                break;
                
                case 3: // Banner 4
    window.location.href = 'https://appshapefit.com/ranking.php';
    break;
                
        }
    }

    // 6. Inicia o carrossel
    async function startCarousel() {
        // Adiciona o ouvinte de clique
        if (mainCarouselElement) {
            mainCarouselElement.addEventListener('click', handleCarouselClick);
        }

        // No iOS, adiciona listeners extras para detectar interação
        if (isIOS) {
            document.addEventListener('touchstart', function() {
                if (!userInteracted) {
                    userInteracted = true;
                    videoElements.forEach(video => {
                        video.play().catch(() => {});
                    });
                }
            }, { once: true });
        }

        // Tenta dar play no primeiro vídeo
        try {
            await firstVideo.play();
        } catch (err) {
            console.log("Autoplay bloqueado. Aguardando interação do usuário...");
        }

        startProgressBarAnimation();
        carouselInterval = setInterval(showNextVideo, duration);
        loadRemainingVideos();
    }

    // Inicia tudo
    createPagination();
    startCarousel();
});
</script>

     <section class="ranking-preview-section">
       <a href="<?php echo BASE_APP_URL; ?>/ranking.php" class="ranking-clash-card card-shadow">
        <div class="player-info left">
            <img src="<?php echo getRankingUserProfileImageUrl($user_profile_data); ?>" alt="Sua foto">
            <span class="player-name">Você</span>
            <span class="player-rank"><?php echo $ranking_preview_data['my_rank']; ?>º</span>
        </div>

        <div class="clash-center">
            <?php if ($my_rank == 1): ?>
                <span class="clash-title-winner"><i class="fas fa-star"></i> Você está no Topo! <i class="fas fa-star"></i></span>
                <div class="progress-bar-clash winner">
                    <div class="progress-fill" style="width: 100%;"></div>
                </div>
                <span class="clash-cta-winner">Mantenha a liderança!</span>
            <?php else: ?>
                <span class="clash-title">Disputa de Pontos</span>
                <div class="progress-bar-clash">
                    <div class="progress-fill" style="width: <?php echo $user_progress_percentage; ?>%;"></div>
                </div>
                <span class="clash-cta">Suba no Ranking!</span>
            <?php endif; ?>
        </div>

        <div class="player-info right">
            <?php if (isset($ranking_preview_data['opponent'])): 
                $opponent = $ranking_preview_data['opponent'];
            ?>
                <img src="<?php echo getRankingUserProfileImageUrl($opponent); ?>" alt="Foto do oponente">
                <span class="player-name"><?php echo htmlspecialchars(explode(' ', $opponent['name'])[0]); ?></span>
                <span class="player-rank opponent-rank"><i class="fas fa-trophy"></i> <?php echo $opponent['rank']; ?>º</span>
            <?php endif; ?>
        </div>
    </a>
    </section>

<div id="weight-banner-container">
    <?php if ($show_edit_button): ?>
        <div class="current-weight-banner card-shadow-light can-edit">
            <span>Peso Atual: <strong id="current-weight-value"><?php echo $current_weight_display; ?></strong></span>
            <!-- O botão agora tem um data-action e não mais um ID -->
            <button data-action="open-weight-modal" class="edit-weight-btn" aria-label="Editar peso">
                <i class="fas fa-edit"></i>
            </button>
        </div>
    <?php else: ?>
        <div class="current-weight-banner card-shadow-light is-locked">
            <div class="locked-info">
                <i class="fas fa-history"></i>
                <span>Próxima atualização de peso em:</span>
            </div>
            <div class="countdown">
                <?php echo $days_until_next_weight_update; ?>
                <span class="countdown-label"><?php echo ($days_until_next_weight_update > 1) ? 'dias' : 'dia'; ?></span>
            </div>
        </div>
    <?php endif; ?>
</div>
    
    <!-- === Seu Consumo Hoje === -->
    <section class="daily-summary-card card-shadow">
        <h2 class="section-title text-center">Seu Consumo Hoje</h2>
        <div class="consumption-grid">
            <div class="consumption-item"><div class="progress-circle" data-value="<?php echo round($kcal_consumed); ?>" data-goal="<?php echo round($total_daily_calories_goal); ?>"><svg viewBox="0 0 36 36" class="circular-chart"><path class="circle-bg" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><path class="circle" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><text x="18" y="20.35" class="percentage-text"><?php echo round($kcal_consumed); ?></text></svg></div><p>Kcal</p></div>
            <div class="consumption-item"><div class="progress-circle" data-value="<?php echo round($carbs_consumed); ?>" data-goal="<?php echo round($macros_goal['carbs_g']); ?>"><svg viewBox="0 0 36 36" class="circular-chart"><path class="circle-bg" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><path class="circle" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><text x="18" y="20.35" class="percentage-text"><?php echo round($carbs_consumed); ?>g</text></svg></div><p>Carbs</p></div>
            <div class="consumption-item"><div class="progress-circle" data-value="<?php echo round($protein_consumed); ?>" data-goal="<?php echo round($macros_goal['protein_g']); ?>"><svg viewBox="0 0 36 36" class="circular-chart"><path class="circle-bg" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><path class="circle" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><text x="18" y="20.35" class="percentage-text"><?php echo round($protein_consumed); ?>g</text></svg></div><p>Proteína</p></div>
            <div class="consumption-item"><div class="progress-circle" data-value="<?php echo round($fat_consumed); ?>" data-goal="<?php echo round($macros_goal['fat_g']); ?>"><svg viewBox="0 0 36 36" class="circular-chart"><path class="circle-bg" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><path class="circle" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><text x="18" y="20.35" class="percentage-text"><?php echo round($fat_consumed); ?>g</text></svg></div><p>Gordura</p></div>
        </div>
    </section>

    <!-- === Hidratação === -->
    <!-- =================================== -->
<!-- NOVO CARD DE HIDRATAÇÃO ANIMADO     -->
<!-- =================================== -->
<!-- CARD DE HIDRATAÇÃO COM SVG ANIMADO  -->
<!-- =================================== -->
<section id="water-card" class="hydration-animated-card card-shadow">
    <div class="card-content">
        <div class="hydration-info">
            <div class="hydration-header">
                <i class="fas fa-tint water-drop-icon"></i>
                <h3>Mantenha-se Hidratado</h3>
            </div>
            
            <!-- ======================================================= -->
            <!--          SUBSTITUA PELO CÓDIGO ABAIXO                   -->
            <!-- ======================================================= -->
            
            <div class="hydration-goal-container">
                <p class="hydration-goal-text">
                    Sua meta: <strong><span id="water-goal-display-cups"><?php echo $water_goal_cups; ?></span></strong> copos
                    <span class="goal-in-ml">(aprox. <?php echo number_format($water_goal_ml, 0, ',', '.'); ?> ml)</span>
                </p>
                <div class="info-tooltip-wrapper">
                    <i class="fas fa-info-circle info-icon"></i>
                    <div class="tooltip-content">
                        Copo padrão de <?php echo $cup_size_ml; ?>ml
                    </div>
                </div>
            </div>

            <div class="water-controls">
                <button class="water-btn" id="decrease-water" aria-label="Remover copo">-</button>
                <div class="water-status" id="water-status-display">
                     <span id="water-amount-display"><?php echo $water_consumed; ?></span> / <span id="water-goal-display-total"><?php echo $water_goal_cups; ?></span>
                </div>
                <button class="water-btn" id="increase-water" aria-label="Adicionar copo">+</button>
            </div>
        </div>

        <div class="glass-container-svg">
            <svg id="animated-glass" width="100" height="140" viewBox="0 0 100 140">
                <defs>
                    <clipPath id="glass-mask">
                        <path d="M 10 0 H 90 L 80 140 H 20 Z" />
                    </clipPath>
                    
                     <linearGradient id="water-gradient" x1="0%" y1="0%" x2="0%" y2="100%"> <stop offset="0%" stop-color="#4fc3f7" /> <stop offset="80%" stop-color="#1976d2" /> </linearGradient>
                    
                    <filter id="water-glow" x="-30%" y="-30%" width="160%" height="160%">
                        <feGaussianBlur stdDeviation="4" result="blur" />
                        <feComposite in="SourceGraphic" in2="blur" operator="over" />
                    </filter>
                </defs>

                <g clip-path="url(#glass-mask)">
                    <g id="water-level-group" transform="translate(0, 140)" filter="url(#water-glow)">
                        <path id="wave1" d="M -400 10 C -300 15, -300 5, -200 10 C -100 15, -100 5, 0 10 C 100 15, 100 5, 200 10 C 300 15, 300 5, 400 10 L 400 140 H -400 Z" 
                              fill="url(#water-gradient)" opacity="0.9"/>
                              
                        <path id="wave2" d="M -400 5 C -300 10, -300 0, -200 5 C -100 10, -100 0, 0 5 C 100 10, 100 0, 200 5 C 300 10, 300 0, 400 5 L 400 140 H -400 Z" 
                              fill="url(#water-gradient)" opacity="0.7"/>
                    </g>
                </g>

                <path d="M 10 0 H 90 L 80 140 H 20 Z" 
                      stroke="rgba(255, 255, 255, 0.35)" 
                      stroke-width="1.5" 
                      fill="none"/>
            </svg>
        </div>
    </div>
</section>

<!-- === Missões Diárias (VERSÃO GAMIFICADA V2 - TUDO DENTRO DO CARD) === -->
<section id="routine-section" class="routine-section-gamified" 
         data-total-missions="<?php echo $total_missions; ?>" 
         data-completed-missions="<?php echo $completed_missions; ?>"
         data-csrf-token="<?php echo $_SESSION['csrf_token']; ?>">

    <!-- O header da seção continua aqui fora, como um título geral -->
    <div class="section-header">
        <h2 class="section-title">Sua Jornada Diária</h2>
        <a href="<?php echo BASE_APP_URL; ?>/routine.php" class="view-all-link animated-underline">Ver todas</a>
    </div>

    <!-- O container das missões agora engloba TUDO -->
    <div class="interactive-missions-container">
        <!-- Barra de Progresso, AGORA DENTRO do container principal -->
        <div class="routine-progress-container">
            <div class="routine-progress-track">
                <div class="routine-progress-fill" id="routine-progress-fill" style="width: <?php echo $routine_progress_percentage; ?>%;"></div>
                <div class="progress-character" id="progress-character" style="left: <?php echo $routine_progress_percentage; ?>%;">
    <i class="fas fa-walking"></i>
</div>
            </div>
            <p class="routine-progress-text" id="routine-progress-text">
                Você completou <strong><?php echo $routine_progress_percentage; ?>%</strong> da sua rotina hoje!
            </p>
        </div>

        <!-- O container para os cards que trocam (a "tela" da missão) -->
        <div class="mission-screen">
            <?php if (!empty($routine_todos)): ?>
                <?php foreach($routine_todos as $index => $item): ?>
                    <div class="mission-card-interactive <?php echo ($index > 0) ? 'hidden' : ''; ?>" 
                         data-routine-id="<?php echo $item['id']; ?>">
                        
                        <div class="mission-icon-interactive">
                            <i class="fas <?php echo htmlspecialchars($item['icon_class'] ?? 'fa-tasks'); ?>"></i>
                        </div>
                        <p class="mission-title-interactive"><?php echo htmlspecialchars($item['title']); ?>?</p>
                        
                        <div class="mission-actions-interactive">
                            <button class="mission-btn-yes" data-action="yes">Sim</button>
                            <button class="mission-btn-no" data-action="no">Não</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

         <!-- NOVO CARD DE CELEBRAÇÃO ANIMADO -->
<div id="routine-celebration-card" class="mission-card-interactive celebration <?php echo !empty($routine_todos) ? 'hidden' : ''; ?>">
    <!-- Container para a explosão de confetes -->
   <div class="confetti-container">
    <div class="confetti"></div><div class="confetti"></div><div class="confetti"></div><div class="confetti"></div><div class="confetti"></div>
    <div class="confetti"></div><div class="confetti"></div><div class="confetti"></div><div class="confetti"></div><div class="confetti"></div>
    <div class="confetti"></div><div class="confetti"></div><div class="confetti"></div><div class="confetti"></div><div class="confetti"></div>
    <div class="confetti"></div><div class="confetti"></div><div class="confetti"></div><div class="confetti"></div><div class="confetti"></div>
</div>
    
    <!-- O conteúdo principal (troféu e texto) -->
    <div class="celebration-content">
        <div class="mission-icon-interactive celebration-icon"><i class="fas fa-trophy"></i></div>
        <p class="mission-title-interactive">Jornada Concluída!</p>
        <p class="celebration-subtitle">Você é incrível! Volte amanhã.</p>
    </div>
</div>
    </div>
</section>
    
    <!-- === Card de Refeição === -->
    <section class="meal-prompt-card card-shadow-prominent">
        <i class="fas fa-utensils meal-prompt-icon"></i>
        <h2 id="meal-time-greeting"><?php echo htmlspecialchars($meal_suggestion_data['greeting']); ?></h2>
        <p>O que você vai comer agora?</p>
        <a href="<?php echo BASE_APP_URL; ?>/add_food_to_diary.php?meal_type=<?php echo urlencode($meal_suggestion_data['db_param']); ?>&date=<?php echo $current_date; ?>" class="btn add-meal-main-btn"><i class="fas fa-plus btn-icon-fa"></i> Adicionar Refeição</a>
    </section>

    <!-- === Sugestões de Refeições === -->
    <section class="meal-suggestions-section">
        <div class="section-header"><h2 class="section-title">Sugestões para <span id="suggestion-meal-type"><?php echo htmlspecialchars($meal_suggestion_data['display_name']); ?></span></h2><a href="<?php echo BASE_APP_URL; ?>/recipe_list.php?meal_type=<?php echo urlencode($meal_suggestion_data['db_param']); ?>" class="view-all-link animated-underline">Ver mais</a></div>
        <div class="suggestions-carousel">
            <?php if (!empty($meal_suggestion_data['recipes'])): foreach($meal_suggestion_data['recipes'] as $recipe): ?>
              <div class="suggestion-item card-shadow-light"><a href="<?php echo BASE_APP_URL; ?>/view_recipe.php?id=<?php echo $recipe['id']; ?>" class="recipe-suggestion-link"><img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename'] ? $recipe['image_filename'] : 'placeholder_food.jpg'); ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>"><div class="recipe-info"><h3><?php echo htmlspecialchars($recipe['name']); ?></h3><span><?php echo round($recipe['total_kcal_per_serving']); ?> kcal</span></div></a></div>
            <?php endforeach; else: ?>
                 <p class="card-shadow-light" style="text-align:center; padding:15px; width:100%;">Nenhuma sugestão.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- === Lista de Refeições de Hoje === -->
    <section class="todays-meals-list-section">
        <div class="section-header"><h2 class="section-title">Suas Refeições de Hoje</h2></div>
        <ul class="meal-list-main">
            <?php
            $meal_map = ['breakfast' => ['icon' => 'fa-coffee', 'label' => 'Café da Manhã'],'morning_snack' => ['icon' => 'fa-apple-alt', 'label' => 'Lanche da Manhã'],'lunch' => ['icon' => 'fa-drumstick-bite', 'label' => 'Almoço'],'afternoon_snack' => ['icon' => 'fa-cookie-bite', 'label' => 'Lanche da Tarde'],'dinner' => ['icon' => 'fa-concierge-bell', 'label' => 'Jantar'],'supper' => ['icon' => 'fa-moon', 'label' => 'Ceia'],];
            foreach ($meal_map as $type => $details): ?>
            <li class="meal-list-item card-shadow-light"><a href="<?php echo BASE_APP_URL; ?>/diary.php?date=<?php echo $current_date; ?>&meal=<?php echo $type; ?>"><span><i class="fas <?php echo $details['icon']; ?>"></i><?php echo $details['label']; ?></span><span class="meal-kcal"><?php echo $calories_by_meal_type[$type] ?? 0; ?> kcal</span></a></li>
            <?php endforeach; ?>
        </ul>
    </section>

</div> <!-- FIM DO .main-app-container -->

<!-- Modal EXCLUSIVO para Editar Peso -->
<div id="edit-weight-modal" class="weight-modal"> <!-- <<< MUDANÇA AQUI -->
    <div class="weight-modal-content">             <!-- <<< MUDANÇA AQUI -->
        <button id="close-weight-modal" class="weight-modal-close-btn">×</button> <!-- <<< MUDANÇA AQUI -->
        <h3 class="weight-modal-title">Atualizar seu Peso</h3>                   <!-- <<< MUDANÇA AQUI -->
        <div class="form-group">
            <label for="new-weight-input">Novo peso (kg)</label>
            <input type="number" id="new-weight-input" class="form-control" placeholder="Ex: 75.5" step="0.1" value="<?php echo (float)$user_profile_data['weight_kg']; ?>">
            <small id="weight-error-message" class="error-message" style="display: none;"></small>
        </div>
        <button id="save-weight-btn" class="btn btn-primary">Salvar</button>
    </div>
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>