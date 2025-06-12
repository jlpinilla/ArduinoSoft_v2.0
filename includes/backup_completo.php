<?php
/**
 * =====================================================
 * INTERFAZ DE GESTIÓN DE BACKUPS COMPLETOS
 * =====================================================
 * Interfaz web para crear y gestionar backups completos
 * del proyecto y la base de datos
 */

// ===== CONTROL DE SEGURIDAD Y SESIÓN =====
if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// ===== INCLUIR GESTOR DE BACKUPS =====
require_once 'backup_manager.php';

// ===== VARIABLES DE CONTROL =====
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$message = '';
$error = '';

// ===== PROCESAMIENTO DE MENSAJES =====
// Obtener mensajes de éxito o error de la operación de restauración
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// ===== PROCESAMIENTO DE ACCIONES =====
try {
    $backupManager = new BackupManager();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        switch ($action) {
            case 'create_project':
                $include_uploads = isset($_POST['include_uploads']);
                $include_logs = isset($_POST['include_logs']);
                $result = $backupManager->createProjectBackup($include_uploads, $include_logs);
                $message = "Backup del proyecto creado exitosamente: {$result['filename']} (" . round($result['size'] / 1024 / 1024, 2) . " MB)";
                break;
                
            case 'create_database':
                $result = $backupManager->createDatabaseBackup();
                $message = "Backup de la base de datos creado exitosamente: {$result['filename']} (" . round($result['size'] / 1024, 2) . " KB)";
                break;
                
            case 'create_complete':
                $include_uploads = isset($_POST['include_uploads']);
                $include_logs = isset($_POST['include_logs']);
                $result = $backupManager->createCompleteBackup($include_uploads, $include_logs);
                $message = "Backup completo creado exitosamente: {$result['filename']} (" . round($result['size'] / 1024 / 1024, 2) . " MB)";
                break;
                
            case 'delete':
                $filename = $_POST['filename'] ?? '';
                if ($filename) {
                    $backupManager->deleteBackup($filename);
                    $message = "Backup eliminado exitosamente: $filename";
                }
                break;        }
    }
    
    // Obtener lista de backups
    $backups = $backupManager->getAvailableBackups();
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

/**
 * Formatear tamaño de archivo
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Obtener icono según tipo de backup
 */
function getBackupIcon($type) {
    switch ($type) {
        case 'complete': return '📦';
        case 'database': return '🗃️';
        case 'proyecto': return '📁';
        default: return '📄';
    }
}

/**
 * Obtener descripción según tipo de backup
 */
function getBackupDescription($type) {
    switch ($type) {
        case 'complete': return 'Backup Completo (Proyecto + Base de Datos)';
        case 'database': return 'Backup de Base de Datos';
        case 'proyecto': return 'Backup del Proyecto';
        default: return 'Backup Desconocido';
    }
}
?>

<!-- ===== INTERFAZ DE GESTIÓN DE BACKUPS ===== -->
<div class="backup-manager-container">
    
    <!-- ===== ENCABEZADO ===== -->
    <div class="page-header">
        <h2 class="page-title">📦 Gestión de Backups Completos</h2>
        <p class="page-subtitle">Cree y gestione copias de seguridad completas del proyecto y la base de datos</p>
    </div>
      <!-- ===== MENSAJES DE ESTADO ===== -->
    <?php if ($message): ?>
        <div class="alert alert-success" role="alert">
            <strong>✅ Éxito:</strong> <?php echo nl2br(htmlspecialchars($message)); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error" role="alert">
            <strong>❌ Error:</strong> <?php echo nl2br(htmlspecialchars($error)); ?>
        </div>
    <?php endif; ?>
    
    <!-- ===== PANEL DE CREACIÓN DE BACKUPS ===== -->
    <div class="backup-creation-panel">
        <h3>🛠️ Crear Nuevo Backup</h3>
        
        <div class="backup-options-grid">
            
            <!-- ===== BACKUP DEL PROYECTO ===== -->
            <div class="backup-option-card">
                <div class="option-header">
                    <span class="option-icon">📁</span>
                    <h4>Backup del Proyecto</h4>
                </div>
                <p class="option-description">
                    Crea una copia de seguridad de todos los archivos del proyecto web 
                    (PHP, CSS, JS, configuraciones, imágenes).
                </p>
                
                <form method="POST" class="backup-form">
                    <input type="hidden" name="action" value="create_project">
                    
                    <div class="form-options">
                        <label class="checkbox-option">
                            <input type="checkbox" name="include_uploads" checked>
                            <span>Incluir archivos multimedia</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="include_logs">
                            <span>Incluir archivos de log</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" onclick="return confirm('¿Crear backup del proyecto?')">
                        📁 Crear Backup del Proyecto
                    </button>
                </form>
            </div>
            
            <!-- ===== BACKUP DE LA BASE DE DATOS ===== -->
            <div class="backup-option-card">
                <div class="option-header">
                    <span class="option-icon">🗃️</span>
                    <h4>Backup de Base de Datos</h4>
                </div>
                <p class="option-description">
                    Crea un dump SQL completo de la base de datos con estructura y datos. 
                    Incluye scripts de restauración.
                </p>
                
                <form method="POST" class="backup-form">
                    <input type="hidden" name="action" value="create_database">
                      <div class="db-info">
                        <small>
                            <?php 
                            $config = parse_ini_file(__DIR__ . '/../config.ini', true);
                            ?>
                            <strong>Base de datos:</strong> <?php echo htmlspecialchars($config['database']['database'] ?? 'No configurada'); ?><br>
                            <strong>Host:</strong> <?php echo htmlspecialchars($config['database']['host'] ?? 'localhost'); ?>
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" onclick="return confirm('¿Crear backup de la base de datos?')">
                        🗃️ Crear Backup de Base de Datos
                    </button>
                </form>
            </div>
            
            <!-- ===== BACKUP COMPLETO ===== -->
            <div class="backup-option-card featured">
                <div class="option-header">
                    <span class="option-icon">📦</span>
                    <h4>Backup Completo</h4>
                    <span class="featured-badge">Recomendado</span>
                </div>
                <p class="option-description">
                    Crea un backup completo incluyendo tanto el proyecto como la base de datos. 
                    Ideal para migración a otro servidor.
                </p>
                
                <form method="POST" class="backup-form">
                    <input type="hidden" name="action" value="create_complete">
                    
                    <div class="form-options">
                        <label class="checkbox-option">
                            <input type="checkbox" name="include_uploads" checked>
                            <span>Incluir archivos multimedia</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="include_logs">
                            <span>Incluir archivos de log</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('¿Crear backup completo? Esta operación puede tardar varios minutos.')">
                        📦 Crear Backup Completo
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- ===== PANEL DE BACKUPS EXISTENTES ===== -->
    <div class="existing-backups-panel">
        <h3>📋 Backups Disponibles</h3>
        
        <?php if (empty($backups)): ?>
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <h4>No hay backups disponibles</h4>
                <p>Cree su primer backup usando las opciones de arriba</p>
            </div>
        <?php else: ?>
            <div class="backups-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($backups); ?></span>
                    <span class="stat-label">Backups Totales</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo formatFileSize(array_sum(array_column($backups, 'size'))); ?></span>
                    <span class="stat-label">Espacio Usado</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo count(array_filter($backups, function($b) { return $b['type'] === 'complete'; })); ?></span>
                    <span class="stat-label">Completos</span>
                </div>
            </div>
            
            <div class="backups-table-container">
                <table class="backups-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Nombre del Archivo</th>
                            <th>Fecha de Creación</th>
                            <th>Tamaño</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr class="backup-row" data-type="<?php echo $backup['type']; ?>">
                                <td class="backup-type">
                                    <span class="type-icon"><?php echo getBackupIcon($backup['type']); ?></span>
                                    <span class="type-text"><?php echo getBackupDescription($backup['type']); ?></span>
                                </td>
                                <td class="backup-filename">
                                    <code><?php echo htmlspecialchars($backup['filename']); ?></code>
                                </td>                                <td class="backup-date">
                                    <?php echo date('d/m/Y H:i:s', $backup['date']); ?>
                                    <small class="time-ago">(<?php echo timeAgo($backup['date']); ?>)</small>
                                </td>
                                <td class="backup-size">
                                    <?php echo formatFileSize($backup['size']); ?>
                                </td>                                <td class="backup-actions">
                                    <a href="download_backup.php?file=<?php echo urlencode($backup['filename']); ?>" 
                                       class="btn btn-sm btn-primary" 
                                       title="Descargar backup">
                                        💾 Descargar
                                    </a>                                      <button type="button" 
                                       class="btn btn-sm btn-warning" 
                                       title="Restaurar este backup"
                                       onclick="showRestoreConfirmation('<?php echo urlencode($backup['filename']); ?>', '<?php echo htmlspecialchars($backup['filename']); ?>', '<?php echo getBackupDescription($backup['type']); ?>', '<?php echo formatFileSize($backup['size']); ?>', '<?php echo date('d/m/Y H:i:s', $backup['date']); ?>', '<?php echo md5(session_id() . 'restore_backup'); ?>')">
                                        🔄 Restaurar
                                      </button>
                                    
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('¿Está seguro que desea eliminar este backup? Esta acción no se puede deshacer.')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar backup">
                                            🗑️ Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ===== INFORMACIÓN Y AYUDA ===== -->
    <div class="help-panel">
        <h3>ℹ️ Información Importante</h3>
        
        <div class="help-grid">
            <div class="help-item">
                <h4>📁 Backup del Proyecto</h4>
                <p>Incluye todos los archivos PHP, CSS, JavaScript, configuraciones y multimedia. Perfecto para actualizar el código en otro servidor.</p>
            </div>
            
            <div class="help-item">
                <h4>🗃️ Backup de Base de Datos</h4>
                <p>Genera un archivo SQL con la estructura completa y todos los datos. Incluye scripts de restauración para diferentes sistemas operativos.</p>
            </div>
            
            <div class="help-item">
                <h4>📦 Backup Completo</h4>
                <p>Combina proyecto y base de datos en un único archivo. La opción más completa para migrar todo el sistema a otro servidor.</p>
            </div>
              <div class="help-item">
                <h4>🔧 Restauración</h4>
                <p>Restaure cualquier backup directamente desde la interfaz web con un solo clic. El sistema creará automáticamente un backup de seguridad antes de realizar la restauración.</p>
            </div>
        </div>
          <div class="warning-box">
            <strong>⚠️ Importante:</strong>
            <ul>
                <li>Los backups se almacenan en el servidor hasta que los descargue</li>
                <li>Los backups completos pueden ser archivos de gran tamaño</li>
                <li>Asegúrese de tener suficiente espacio en disco antes de crear backups</li>
                <li>Los archivos de backup contienen información sensible, manéjelos con cuidado</li>
                <li>La restauración reemplazará archivos y datos actuales; se creará un backup de seguridad automático</li>
                <li>Después de una restauración, es recomendable cerrar sesión y volver a iniciar para evitar problemas de caché</li>
            </ul>
        </div>
    </div>
</div>

<?php
/**
 * Función para calcular tiempo transcurrido
 */
function timeAgo($timestamp) {
    $time_diff = time() - $timestamp;
    
    if ($time_diff < 60) {
        return 'hace ' . $time_diff . ' segundos';
    } elseif ($time_diff < 3600) {
        return 'hace ' . round($time_diff / 60) . ' minutos';
    } elseif ($time_diff < 86400) {
        return 'hace ' . round($time_diff / 3600) . ' horas';
    } else {
        return 'hace ' . round($time_diff / 86400) . ' días';
    }
}
?>

<!-- ===== ESTILOS ESPECÍFICOS PARA BACKUPS ===== -->
<style>
.backup-manager-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    text-align: center;
    margin-bottom: 40px;
}

.page-header h2 {
    color: var(--primary-color);
    margin-bottom: 10px;
}

.page-subtitle {
    color: var(--text-light);
    font-size: 1.1em;
}

/* ===== PANEL DE CREACIÓN ===== */
.backup-creation-panel {
    background: var(--card-background);
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: var(--shadow);
}

.backup-creation-panel h3 {
    margin-top: 0;
    margin-bottom: 25px;
    color: var(--primary-color);
}

.backup-options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 20px;
}

.backup-option-card {
    background: #f8f9fa;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 25px;
    transition: all 0.3s ease;
}

.backup-option-card:hover {
    border-color: var(--primary-color);
    box-shadow: 0 4px 12px rgba(0, 120, 212, 0.1);
}

.backup-option-card.featured {
    border-color: var(--success-color);
    background: linear-gradient(135deg, #f0fff0 0%, #e6ffe6 100%);
}

.option-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    position: relative;
}

.option-icon {
    font-size: 2em;
}

.option-header h4 {
    margin: 0;
    color: var(--text-color);
}

.featured-badge {
    position: absolute;
    right: 0;
    top: -5px;
    background: var(--success-color);
    color: white;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.7em;
    font-weight: bold;
}

.option-description {
    color: var(--text-light);
    margin-bottom: 20px;
    line-height: 1.5;
}

.backup-form {
    margin-top: 20px;
}

.form-options {
    margin-bottom: 15px;
}

.checkbox-option {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    cursor: pointer;
}

.checkbox-option input[type="checkbox"] {
    margin: 0;
}

.db-info {
    background: rgba(0, 120, 212, 0.1);
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
}

/* ===== PANEL DE BACKUPS EXISTENTES ===== */
.existing-backups-panel {
    background: var(--card-background);
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: var(--shadow);
}

.existing-backups-panel h3 {
    margin-top: 0;
    margin-bottom: 25px;
    color: var(--primary-color);
}

.backups-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-item {
    text-align: center;
    padding: 20px;
    background: var(--background-color);
    border-radius: 8px;
}

.stat-number {
    display: block;
    font-size: 2em;
    font-weight: bold;
    color: var(--primary-color);
}

.stat-label {
    display: block;
    color: var(--text-light);
    font-size: 0.9em;
    margin-top: 5px;
}

.backups-table-container {
    overflow-x: auto;
}

.backups-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.backups-table th,
.backups-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.backups-table th {
    background: var(--background-color);
    font-weight: 600;
    color: var(--text-color);
}

.backup-row:hover {
    background: rgba(0, 120, 212, 0.05);
}

.backup-type {
    display: flex;
    align-items: center;
    gap: 10px;
}

.type-icon {
    font-size: 1.2em;
}

.backup-filename code {
    background: var(--background-color);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.9em;
}

.time-ago {
    display: block;
    color: var(--text-light);
    font-style: italic;
}

.backup-actions {
    white-space: nowrap;
}

.backup-actions .btn {
    margin-right: 5px;
}

/* ===== PANEL DE AYUDA ===== */
.help-panel {
    background: var(--card-background);
    border-radius: 12px;
    padding: 30px;
    box-shadow: var(--shadow);
}

.help-panel h3 {
    margin-top: 0;
    margin-bottom: 25px;
    color: var(--primary-color);
}

.help-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.help-item {
    padding: 20px;
    background: var(--background-color);
    border-radius: 8px;
}

.help-item h4 {
    margin-top: 0;
    margin-bottom: 10px;
    color: var(--text-color);
}

.help-item p {
    color: var(--text-light);
    line-height: 1.5;
    margin: 0;
}

.warning-box {
    background: rgba(255, 152, 0, 0.1);
    border: 1px solid rgba(255, 152, 0, 0.3);
    border-radius: 8px;
    padding: 20px;
}

.warning-box strong {
    color: var(--warning-color);
}

.warning-box ul {
    margin: 10px 0 0 20px;
    color: var(--text-light);
}

/* ===== ESTADO VACÍO ===== */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 4em;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h4 {
    color: var(--text-color);
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--text-light);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .backup-manager-container {
        padding: 10px;
    }
    
    .backup-creation-panel,
    .existing-backups-panel,
    .help-panel {
        padding: 20px;
    }
    
    .backup-options-grid {
        grid-template-columns: 1fr;
    }
    
    .backups-stats {
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    }
    
    .help-grid {
        grid-template-columns: 1fr;
    }
    
    .backup-actions .btn {
        display: block;
        margin: 2px 0;
        width: 100%;
    }
}
</style>

<!-- ===== JAVASCRIPT PARA FUNCIONALIDAD ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Indicador de progreso para operaciones largas
    const forms = document.querySelectorAll('.backup-form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Cambiar texto del botón
            submitBtn.innerHTML = '⏳ Procesando...';
            submitBtn.disabled = true;
            
            // Mostrar indicador de progreso
            const progressDiv = document.createElement('div');
            progressDiv.className = 'progress-indicator';
            progressDiv.innerHTML = `
                <div style="text-align: center; margin-top: 15px; padding: 15px; background: rgba(0,120,212,0.1); border-radius: 6px;">
                    <div style="display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid var(--primary-color); border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <p style="margin: 10px 0 0 0; color: var(--primary-color);">Creando backup... Esta operación puede tardar varios minutos.</p>
                </div>
            `;
            
            this.appendChild(progressDiv);
            
            // Restaurar botón en caso de error (timeout)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                if (progressDiv.parentNode) {
                    progressDiv.remove();
                }
            }, 300000); // 5 minutos
        });
    });
    
    // Confirmación adicional para backups completos
    const completeBackupForm = document.querySelector('form[action*="create_complete"]');
    if (completeBackupForm) {
        completeBackupForm.addEventListener('submit', function(e) {
            const confirmed = confirm(
                'BACKUP COMPLETO\n\n' +
                'Esta operación creará un backup completo del proyecto y la base de datos.\n' +
                'Puede tardar varios minutos dependiendo del tamaño de sus datos.\n\n' +
                '¿Desea continuar?'
            );
            
            if (!confirmed) {
                e.preventDefault();
            }
        });
    }
});

// Animación de rotación para el indicador de progreso
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Modal de confirmación para restauración de backups
function showRestoreConfirmation(encodedFilename, filename, backupType, fileSize, backupDate, token) {
    // Crear overlay para modal
    const overlay = document.createElement('div');
    overlay.className = 'restore-modal-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.7);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    // Crear contenido del modal
    const modal = document.createElement('div');
    modal.className = 'restore-confirmation-modal';
    modal.style.cssText = `
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        max-width: 550px;
        width: 90%;
        padding: 0;
        animation: modalFadeIn 0.3s;
    `;
    
    // Añadir animación CSS
    const modalStyle = document.createElement('style');
    modalStyle.textContent = `
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .btn-proceed {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        .btn-proceed:hover {
            background-color: #bd2130;
        }
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            margin-right: 10px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        .restore-modal-footer label {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-weight: normal;
            cursor: pointer;
        }
        .restore-modal-footer input[type="checkbox"] {
            margin-right: 10px;
        }
    `;
    document.head.appendChild(modalStyle);
    
    // Contenido HTML del modal
    modal.innerHTML = `
        <div class="restore-modal-header" style="background-color: #f8d7da; color: #721c24; padding: 15px; border-top-left-radius: 8px; border-top-right-radius: 8px; border-bottom: 1px solid #f5c6cb;">
            <h3 style="margin: 0; display: flex; align-items: center; font-size: 1.25rem;">
                <span style="font-size: 24px; margin-right: 10px;">⚠️</span> Confirmación de Restauración
            </h3>
        </div>
        <div class="restore-modal-body" style="padding: 20px;">
            <p style="font-weight: bold; font-size: 16px;">Está a punto de restaurar el siguiente backup:</p>
            
            <div style="background-color: #f8f9fa; border-radius: 5px; padding: 15px; margin-bottom: 20px;">
                <div style="margin-bottom: 10px;"><strong>Archivo:</strong> ${filename}</div>
                <div style="margin-bottom: 10px;"><strong>Tipo:</strong> ${backupType}</div>
                <div style="margin-bottom: 10px;"><strong>Tamaño:</strong> ${fileSize}</div>
                <div><strong>Fecha:</strong> ${backupDate}</div>
            </div>
            
            <div style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <p style="font-weight: bold; margin-top: 0;">⚠️ ADVERTENCIA: Esta acción tiene las siguientes implicaciones:</p>
                <ul style="padding-left: 20px; margin-bottom: 0;">
                    <li>Se reemplazarán todos los archivos y/o datos actuales</li>
                    <li>Se creará un backup de seguridad automático antes de la operación</li>
                    <li>El proceso puede tardar varios minutos dependiendo del tamaño</li>
                    <li>No se debe interrumpir el proceso para evitar daños</li>
                    <li>Es recomendable reiniciar la sesión después de la restauración</li>
                </ul>
            </div>
        </div>
        <div class="restore-modal-footer" style="padding: 15px; border-top: 1px solid #dee2e6; text-align: right; background-color: #f8f9fa; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
            <label>
                <input type="checkbox" id="confirm-restore-checkbox"> Entiendo las implicaciones y deseo continuar
            </label>
            <div>
                <button class="btn-cancel" onclick="closeRestoreModal()">Cancelar</button>
                <button class="btn-proceed" id="proceed-restore-btn" disabled>Restaurar Ahora</button>
            </div>
        </div>
    `;
    
    // Añadir modal al DOM
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    
    // Manejar checkbox y botón de proceder
    const checkbox = document.getElementById('confirm-restore-checkbox');
    const proceedBtn = document.getElementById('proceed-restore-btn');
    
    checkbox.addEventListener('change', function() {
        proceedBtn.disabled = !this.checked;
    });
    
    proceedBtn.addEventListener('click', function() {
        if (checkbox.checked) {
            // Mostrar spinner
            this.innerHTML = '<span style="display: inline-block; width: 16px; height: 16px; border: 2px solid #f3f3f3; border-top: 2px solid white; border-radius: 50%; animation: spin 1s linear infinite; vertical-align: middle; margin-right: 10px;"></span> Restaurando...';
            this.disabled = true;
            
            // Redirigir a la página de restauración
            window.location.href = `restore_backup.php?file=${encodedFilename}&token=${token}`;
        }
    });
}

// Cerrar el modal de confirmación
function closeRestoreModal() {
    const overlay = document.querySelector('.restore-modal-overlay');
    if (overlay) {
        overlay.style.opacity = '0';
        overlay.style.transition = 'opacity 0.2s';
        setTimeout(() => {
            overlay.remove();
        }, 200);
    }
}
</script>
