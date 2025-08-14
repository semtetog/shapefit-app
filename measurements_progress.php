<?php
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

$user_id = $_SESSION['user_id'];
$page_title = "Suas Fotos e Medidas";

// --- NOVA LÓGICA: BUSCAR HISTÓRICO DE MEDIDAS ---
$history_data = [];
$stmt = $conn->prepare("SELECT * FROM sf_user_measurements WHERE user_id = ? ORDER BY date_recorded DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $history_data[] = $row;
}
$stmt->close();

$extra_css = ['main_app_specific.css', 'measurements.css'];
$extra_js = ['measurements_logic.js'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<div class="container main-app-container">
    <div class="header-nav">
        <a href="<?php echo BASE_APP_URL; ?>/progress.php" class="back-button" aria-label="Voltar"><i class="fas fa-chevron-left"></i></a>
        <h1 class="page-title">Fotos e Medidas</h1>
    </div>

    <!-- Formulário para novos registros -->
    <form id="measurements-form" enctype="multipart/form-data" class="card-shadow">
        <h3 class="section-title-form">Novo Registro de Progresso</h3>
        <input type="hidden" name="action" value="save_measurements">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="form-section">
            <div class="form-group date-input-group">
                <label for="date_recorded">Data do Registro</label>
                <input type="date" id="date_recorded" name="date_recorded" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>

        <div class="form-section">
            <h4 class="subsection-title">Fotos de Progresso</h4>
            <div class="photo-upload-grid">
                <div class="photo-upload-item">
                    <label for="photo_front">Frente</label>
                    <input type="file" name="photo_front" id="photo_front" class="photo-input" accept="image/*">
                    <label for="photo_front" class="photo-label"><i class="fas fa-camera"></i><span>Adicionar</span></label>
                </div>
                <div class="photo-upload-item">
                    <label for="photo_side">Lado</label>
                    <input type="file" name="photo_side" id="photo_side" class="photo-input" accept="image/*">
                    <label for="photo_side" class="photo-label"><i class="fas fa-camera"></i><span>Adicionar</span></label>
                </div>
                <div class="photo-upload-item">
                    <label for="photo_back">Costas</label>
                    <input type="file" name="photo_back" id="photo_back" class="photo-input" accept="image/*">
                    <label for="photo_back" class="photo-label"><i class="fas fa-camera"></i><span>Adicionar</span></label>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h4 class="subsection-title">Medidas Corporais (cm)</h4>
            <div class="measurements-grid">
                <div class="form-group"><label for="weight_kg">Peso (kg)</label><input type="number" step="0.1" name="weight_kg" id="weight_kg" class="form-control" placeholder="Ex: 75.5"></div>
                <div class="form-group"><label for="neck">Pescoço</label><input type="number" step="0.1" name="neck" id="neck" class="form-control" placeholder="Opcional"></div>
                <div class="form-group"><label for="chest">Tórax</label><input type="number" step="0.1" name="chest" id="chest" class="form-control" placeholder="Opcional"></div>
                <div class="form-group"><label for="waist">Cintura</label><input type="number" step="0.1" name="waist" id="waist" class="form-control" placeholder="Opcional"></div>
                <div class="form-group"><label for="abdomen">Abdômen</label><input type="number" step="0.1" name="abdomen" id="abdomen" class="form-control" placeholder="Opcional"></div>
                <div class="form-group"><label for="hips">Quadril</label><input type="number" step="0.1" name="hips" id="hips" class="form-control" placeholder="Opcional"></div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Salvar Progresso</button>
        </div>
    </form>

    <!-- =================================================================== -->
    <!--           NOVA SEÇÃO DE HISTÓRICO DE MEDIDAS                      -->
    <!-- =================================================================== -->
    <div class="history-section">
        <h3 class="section-title-form">Seu Histórico</h3>
        <?php if (empty($history_data)): ?>
            <p class="empty-state card-shadow">Você ainda não tem nenhum registro de progresso. Adicione suas fotos e medidas acima para começar!</p>
        <?php else: ?>
            <?php foreach ($history_data as $record): ?>
                <div class="history-item card-shadow">
                    <div class="history-item-header">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?php echo date('d/m/Y', strtotime($record['date_recorded'])); ?></span>
                    </div>
                    <div class="history-item-content">
                        <?php if (!empty($record['photo_front']) || !empty($record['photo_side']) || !empty($record['photo_back'])): ?>
                            <div class="history-photos">
                                <?php if($record['photo_front']): ?><a href="<?php echo BASE_ASSET_URL . '/assets/images/progress/' . $record['photo_front']; ?>" target="_blank"><img src="<?php echo BASE_ASSET_URL . '/assets/images/progress/' . $record['photo_front']; ?>" alt="Frente"></a><?php endif; ?>
                                <?php if($record['photo_side']): ?><a href="<?php echo BASE_ASSET_URL . '/assets/images/progress/' . $record['photo_side']; ?>" target="_blank"><img src="<?php echo BASE_ASSET_URL . '/assets/images/progress/' . $record['photo_side']; ?>" alt="Lado"></a><?php endif; ?>
                                <?php if($record['photo_back']): ?><a href="<?php echo BASE_ASSET_URL . '/assets/images/progress/' . $record['photo_back']; ?>" target="_blank"><img src="<?php echo BASE_ASSET_URL . '/assets/images/progress/' . $record['photo_back']; ?>" alt="Costas"></a><?php endif; ?>
                            </div>
                        <?php endif; ?>
                         <ul class="history-measurements-list">
                            <?php if($record['weight_kg']): ?><li><strong>Peso:</strong> <?php echo $record['weight_kg']; ?> kg</li><?php endif; ?>
                            <?php if($record['neck']): ?><li><strong>Pescoço:</strong> <?php echo $record['neck']; ?> cm</li><?php endif; ?>
                            <?php if($record['chest']): ?><li><strong>Tórax:</strong> <?php echo $record['chest']; ?> cm</li><?php endif; ?>
                            <?php if($record['waist']): ?><li><strong>Cintura:</strong> <?php echo $record['waist']; ?> cm</li><?php endif; ?>
                            <?php if($record['abdomen']): ?><li><strong>Abdômen:</strong> <?php echo $record['abdomen']; ?> cm</li><?php endif; ?>
                            <?php if($record['hips']): ?><li><strong>Quadril:</strong> <?php echo $record['hips']; ?> cm</li><?php endif; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- =================================================================== -->
    <!--           NOVO MODAL PARA VISUALIZAÇÃO DAS FOTOS                -->
    <!-- =================================================================== -->
    <div id="photo-modal" class="photo-modal-overlay" style="display: none;">
        <span class="photo-modal-close">×</span>
        <img class="photo-modal-content" id="modal-image">
    </div>
    <!-- =================================================================== -->
    
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>