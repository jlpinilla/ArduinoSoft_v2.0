<?php
// ===== GESTIÓN COMPLETA DE USUARIOS DEL SISTEMA =====
/**
 * INCLUDES/USUARIOS.PHP - Módulo de Gestión de Usuarios
 * 
 * Este archivo maneja todas las operaciones CRUD (Create, Read, Update, Delete)
 * para los usuarios del sistema de monitoreo ambiental.
 * 
 * Funcionalidades principales:
 * - Listado de usuarios con filtros de búsqueda
 * - Creación de nuevos usuarios con validación
 * - Edición de usuarios existentes
 * - Eliminación de usuarios con confirmación
 * - Gestión de roles (admin/operador)
 * - Protección contra auto-eliminación
 */

// ===== INICIALIZACIÓN DE VARIABLES =====
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';  // Acción a realizar
$message = '';  // Mensaje de éxito
$error = '';    // Mensaje de error

// Inicialización de variables para la vista
$search = $_GET['search'] ?? '';       // Búsqueda por nombre
$rol_filter = $_GET['rol_filter'] ?? ''; // Filtro por rol
$usuarios = [];                        // Lista de usuarios (inicializar como array vacío)

try {    // === Conexión optimizada usando DatabaseManager ===
    require_once __DIR__ . '/database_manager.php';
    $dbManager = getDBManager($config ?? null);
    $pdo = $dbManager->getConnection();
    
    // ===== PROCESAMIENTO DE ACCIONES DEL USUARIO =====
    switch ($action) {
        
        // === ACCIÓN: CREAR NUEVO USUARIO ===
        case 'create':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Sanitización de datos de entrada
                $nuevo_usuario = trim($_POST['usuario'] ?? '');
                $nueva_contrasena = trim($_POST['contrasena'] ?? '');
                $nuevo_rol = $_POST['rol'] ?? 'operador';  // Rol por defecto: operador
                
                // Validación de campos obligatorios
                if (empty($nuevo_usuario) || empty($nueva_contrasena)) {
                    $error = 'Usuario y contraseña son obligatorios.';
                } else {
                    // Inserción en tabla 'usuarios' (actualizada de 'users')
                    $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, contrasena, rol) VALUES (?, ?, ?)");
                    if ($stmt->execute([$nuevo_usuario, $nueva_contrasena, $nuevo_rol])) {
                        $message = "Usuario '$nuevo_usuario' creado exitosamente.";
                        $action = 'list';  // Redireccionar a lista
                    } else {
                        $error = 'Error al crear el usuario. Posiblemente ya existe.';
                    }
                }
            }
            break;
            
        // === ACCIÓN: EDITAR USUARIO EXISTENTE ===
        case 'edit':
            $user_id = $_GET['id'] ?? $_POST['id'] ?? 0;
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Sanitización de datos
                $nuevo_usuario = trim($_POST['usuario'] ?? '');
                $nueva_contrasena = trim($_POST['contrasena'] ?? '');
                $nuevo_rol = $_POST['rol'] ?? 'operador';
                
                // Validación de nombre de usuario
                if (empty($nuevo_usuario)) {
                    $error = 'El nombre de usuario es obligatorio.';
                } else {
                    // Actualización condicional según si se cambia contraseña
                    if (!empty($nueva_contrasena)) {
                        // Actualizar con nueva contraseña
                        $stmt = $pdo->prepare("UPDATE usuarios SET usuario=?, contrasena=?, rol=? WHERE id=?");
                        $params = [$nuevo_usuario, $nueva_contrasena, $nuevo_rol, $user_id];
                    } else {
                        // Actualizar sin cambiar contraseña
                        $stmt = $pdo->prepare("UPDATE usuarios SET usuario=?, rol=? WHERE id=?");
                        $params = [$nuevo_usuario, $nuevo_rol, $user_id];
                    }
                    
                    if ($stmt->execute($params)) {
                        $message = "Usuario actualizado exitosamente.";
                        $action = 'list';
                    } else {
                        $error = 'Error al actualizar el usuario.';
                    }
                }
            }
            break;
            
        // === ACCIÓN: ELIMINAR USUARIO ===
        case 'delete':
            $user_id = $_GET['id'] ?? 0;
            if ($user_id && isset($_GET['confirm'])) {
                // Protección: no permitir eliminar el usuario actual
                if ($user_id == $_SESSION['user_id']) {
                    $error = 'No puede eliminar su propia cuenta.';
                } else {
                    // Eliminación de tabla 'usuarios'
                    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                    if ($stmt->execute([$user_id])) {
                        $message = "Usuario eliminado exitosamente.";
                    } else {
                        $error = 'Error al eliminar el usuario.';
                    }
                }
                $action = 'list';
            }
            break;    }
    
    // ===== OBTENCIÓN DE LISTA DE USUARIOS CON FILTROS =====
    // (Solo si la acción es 'list' y no hay errores de conexión)
    if ($action === 'list') {
        // Construcción dinámica de consulta SQL
        $sql = "SELECT * FROM usuarios WHERE 1=1";
        $params = [];
        
        // Aplicar filtro de búsqueda por nombre
        if (!empty($search)) {
            $sql .= " AND usuario LIKE ?";
            $params[] = "%$search%";
        }
        
        // Aplicar filtro por rol
        if (!empty($rol_filter)) {
            $sql .= " AND rol = ?";
            $params[] = $rol_filter;
        }
          // Ordenar por fecha de creación descendente
        $sql .= " ORDER BY fecha_creacion DESC";
          
        // === Ejecución de la consulta con parámetros ===
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $usuarios = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    // === Manejo de errores de base de datos ===
    $error = "Error: " . $e->getMessage();
}
?>

<!-- ===== ENCABEZADO DE LA PÁGINA DE GESTIÓN ===== -->
<h2 class="page-title">Gestión de Usuarios</h2>

<?php if ($message): ?>
    <!-- Mensaje de éxito -->
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <!-- Mensaje de error -->
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- ===== VISTA DE LISTA DE USUARIOS ===== -->
    
    <!-- === Barra de herramientas con filtros y acciones === -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <!-- Formulario de búsqueda y filtros -->
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="seccion" value="usuarios">
                <!-- Campo de búsqueda por nombre de usuario -->
                <input type="text" name="search" placeholder="Buscar usuario..." 
                       value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="width: auto;">
                <!-- Filtro por rol de usuario -->
                <select name="rol_filter" class="form-control" style="width: auto;">
                    <option value="">Todos los roles</option>
                    <option value="admin" <?php echo $rol_filter === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                    <option value="operador" <?php echo $rol_filter === 'operador' ? 'selected' : ''; ?>>Operador</option>
                </select>
                <button type="submit" class="btn btn-secondary">Buscar</button>
            </form>
        </div>
        <div>
            <!-- Botón para crear nuevo usuario -->
            <a href="?seccion=usuarios&action=create" class="btn btn-primary">+ Nuevo Usuario</a>
        </div>
    </div>
    
    <!-- === Lista de usuarios en formato de tarjetas === -->
    <?php if (count($usuarios) > 0): ?>
        <div class="users-grid">
            <?php foreach ($usuarios as $user): ?>
                <div class="user-card">
                    <!-- Avatar con inicial del usuario -->
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['usuario'], 0, 1)); ?>
                    </div>
                    <!-- Información del usuario -->
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['usuario']); ?></div>
                        <div class="user-details">
                            <div class="detail-row">
                                <span class="detail-label">ID:</span>
                                <span class="detail-value"><?php echo $user['id']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Rol:</span>
                                <span class="detail-value"><?php echo ucfirst($user['rol']); ?></span>
                            </div>                            <div class="detail-row">
                                <span class="detail-label">Creado:</span>
                                <span class="detail-value"><?php echo date('d/m/Y', strtotime($user['fecha_creacion'])); ?></span>
                            </div>
                        </div>
                    </div>
                    <!-- Acciones disponibles para el usuario -->
                    <div class="user-actions">
                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                            <!-- Indicador de usuario actual -->
                            <span class="status-badge" style="background: var(--primary-color); color: white;">Actual</span>
                        <?php endif; ?>
                        <!-- Botón de edición -->
                        <a href="?seccion=usuarios&action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-secondary">
                            Editar
                        </a>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <!-- Botón de eliminación (solo si no es el usuario actual) -->
                            <a href="?seccion=usuarios&action=delete&id=<?php echo $user['id']; ?>" 
                               onclick="return confirm('¿Está seguro que desea eliminar al usuario \'<?php echo htmlspecialchars($user['usuario']); ?>\'?')"
                               class="btn btn-sm" style="background: var(--error-color); color: white;">
                                Eliminar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Estado vacío cuando no hay usuarios -->
        <div class="empty-state">
            <div class="empty-icon">👥</div>
            <h3>No se encontraron usuarios</h3>
            <p>No hay usuarios que coincidan con los criterios de búsqueda.</p>
            <a href="?seccion=usuarios&action=create" class="btn btn-primary">Crear Primer Usuario</a>
        </div>
    <?php endif; ?>

<?php elseif ($action === 'create'): ?>
    <!-- ===== VISTA DE CREACIÓN DE USUARIO ===== -->
    
    <!-- Formulario de creación de nuevo usuario -->
    <form method="POST" action="">
        <input type="hidden" name="action" value="create">
        
        <div class="form-section">
            <h3>Nuevo Usuario</h3>
              
            <!-- Campos del formulario de creación -->
            <div class="form-row">
                <div class="form-group">
                    <label for="usuario" class="form-label">Usuario *</label>
                    <input type="text" id="usuario" name="usuario" class="form-control" required
                           value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>"
                           placeholder="Nombre de usuario único">
                </div>
                
                <div class="form-group">
                    <label for="contrasena" class="form-label">Contraseña *</label>
                    <input type="password" id="contrasena" name="contrasena" class="form-control" required
                           placeholder="Contraseña segura">
                </div>
            </div>
            
            <!-- Selector de rol de usuario -->
            <div class="form-group">
                <label for="rol" class="form-label">Rol</label>
                <select id="rol" name="rol" class="form-control">
                    <option value="operador" <?php echo ($_POST['rol'] ?? '') === 'operador' ? 'selected' : ''; ?>>Operador</option>
                    <option value="admin" <?php echo ($_POST['rol'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                </select>
                <small class="form-help">
                    • <strong>Operador:</strong> Acceso a monitoreo y consultas<br>
                    • <strong>Administrador:</strong> Acceso completo al sistema
                </small>
            </div>
        </div>
        
        <!-- Botones de acción -->
        <div class="text-center mt-xl">
            <button type="submit" class="btn btn-primary">Crear Usuario</button>
            <a href="?seccion=usuarios" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>

<?php elseif ($action === 'edit'): ?>
    <!-- ===== VISTA DE EDICIÓN DE USUARIO ===== -->
    
    <?php
    // === Obtener datos del usuario a editar ===
    $user_id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $edit_user = $stmt->fetch();
    if (!$edit_user) {
        // Usuario no encontrado
        echo '<div class="alert alert-error">Usuario no encontrado.</div>';
        echo '<a href="?seccion=usuarios" class="btn btn-primary">Volver a la lista</a>';
    } else {
    ?>
    <!-- Formulario de edición de usuario existente -->
    <form method="POST" action="">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?php echo $user_id; ?>">
        
        <div class="form-section">
            <h3>Editar Usuario: <?php echo htmlspecialchars($edit_user['usuario']); ?></h3>
            
            <!-- Campos editables del usuario -->
            <div class="form-row">
                <div class="form-group">
                    <label for="usuario" class="form-label">Usuario *</label>
                    <input type="text" id="usuario" name="usuario" class="form-control" required
                           value="<?php echo htmlspecialchars($_POST['usuario'] ?? $edit_user['usuario']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="contrasena" class="form-label">Nueva Contraseña</label>
                    <input type="password" id="contrasena" name="contrasena" class="form-control"
                           placeholder="Dejar vacío para mantener la actual">
                    <small class="form-help">Solo ingrese una nueva contraseña si desea cambiarla</small>
                </div>
            </div>
            
            <!-- Selector de rol -->
            <div class="form-group">
                <label for="rol" class="form-label">Rol</label>
                <select id="rol" name="rol" class="form-control">
                    <option value="operador" <?php echo ($edit_user['rol'] === 'operador') ? 'selected' : ''; ?>>Operador</option>
                    <option value="admin" <?php echo ($edit_user['rol'] === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                </select>
            </div>
            
            <!-- Información adicional del usuario -->            <div class="info-box">
                <strong>Información:</strong>
                <ul>
                    <li>Usuario creado: <?php echo date('d/m/Y H:i', strtotime($edit_user['fecha_creacion'])); ?></li>
                    <li>Si no ingresa una nueva contraseña, se mantendrá la actual</li>
                    <?php if ($edit_user['id'] == $_SESSION['user_id']): ?>
                        <li><strong>⚠️ Está editando su propio usuario</strong></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <!-- Botones de acción para edición -->
        <div class="text-center mt-xl">
            <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
            <a href="?seccion=usuarios" class="btn btn-secondary">Cancelar</a>
            <?php if ($user_id != $_SESSION['user_id']): ?>
                <!-- Botón de eliminación (solo si no es el usuario actual) -->
                <a href="?seccion=usuarios&action=delete&id=<?php echo $user_id; ?>" 
                   onclick="return confirm('¿Está seguro que desea eliminar este usuario? Esta acción no se puede deshacer.')"
                   class="btn" style="background: var(--error-color); color: white;">
                    Eliminar Usuario
                </a>
            <?php endif; ?>
        </div>
    </form>
    <?php } ?>

<?php elseif ($action === 'delete'): ?>
    <!-- ===== VISTA DE CONFIRMACIÓN DE ELIMINACIÓN ===== -->
    
    <?php
    // === Obtener datos del usuario a eliminar ===
    $user_id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $delete_user = $stmt->fetch();
    if (!$delete_user) {
        // Usuario no encontrado
        echo '<div class="alert alert-error">Usuario no encontrado.</div>';
        echo '<a href="?seccion=usuarios" class="btn btn-primary">Volver a la lista</a>';
    } else {
    ?>
    <!-- Confirmación de eliminación con advertencia -->
    <div class="alert alert-error">
        <h3>⚠️ Confirmar Eliminación</h3>
        <p>¿Está seguro que desea eliminar al usuario <strong><?php echo htmlspecialchars($delete_user['usuario']); ?></strong>?</p>
        <p><strong>Esta acción no se puede deshacer.</strong></p>
        
        <!-- Información del usuario a eliminar -->        <div style="margin: 15px 0; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 5px;">
            <strong>Detalles del usuario:</strong><br>
            • ID: <?php echo $delete_user['id']; ?><br>
            • Rol: <?php echo ucfirst($delete_user['rol']); ?><br>
            • Creado: <?php echo date('d/m/Y H:i', strtotime($delete_user['fecha_creacion'])); ?>
        </div>
    </div>
    
    <!-- Botones de confirmación -->
    <div class="text-center mt-xl">
        <a href="?seccion=usuarios&action=delete&id=<?php echo $user_id; ?>&confirm=1" 
           class="btn" style="background: var(--error-color); color: white;">
            Sí, Eliminar Usuario
        </a>
        <a href="?seccion=usuarios" class="btn btn-primary">Cancelar</a>
    </div>
    <?php } ?>

<?php endif; ?>
