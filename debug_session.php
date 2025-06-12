<?php
/**
 * Script de debug para verificar el estado de la sesión
 */

session_start();

echo "<h2>Debug de Sesión para Backups</h2>";

echo "<h3>Variables de $_SESSION:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Variables de $_GET:</h3>";
echo "<pre>";
print_r($_GET);
echo "</pre>";

echo "<h3>Verificaciones:</h3>";
echo "<ul>";
echo "<li>¿Existe \$_SESSION['user_id']? " . (isset($_SESSION['user_id']) ? 'SÍ' : 'NO') . "</li>";
echo "<li>¿Existe \$_SESSION['usuario']? " . (isset($_SESSION['usuario']) ? 'SÍ' : 'NO') . "</li>";
echo "<li>¿Existe \$_SESSION['rol']? " . (isset($_SESSION['rol']) ? 'SÍ' : 'NO') . "</li>";
echo "<li>¿Rol es admin? " . (($_SESSION['rol'] ?? '') === 'admin' ? 'SÍ' : 'NO') . "</li>";
echo "</ul>";

if (isset($_GET['simulate'])) {
    echo "<h3>Simulando llamada a download_backup.php:</h3>";
    
    // Verificaciones que hace download_backup.php
    if (!isset($_SESSION['user_id'])) {
        echo "<span style='color: red;'>ERROR: No hay \$_SESSION['user_id']</span><br>";
    } else {
        echo "<span style='color: green;'>OK: \$_SESSION['user_id'] existe</span><br>";
    }
    
    if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
        echo "<span style='color: red;'>ERROR: Rol no es admin o no existe</span><br>";
    } else {
        echo "<span style='color: green;'>OK: Rol es admin</span><br>";
    }
    
    if (!isset($_GET['file']) || empty($_GET['file'])) {
        echo "<span style='color: red;'>ERROR: No se especificó archivo</span><br>";
    } else {
        echo "<span style='color: green;'>OK: Archivo especificado: " . $_GET['file'] . "</span><br>";
    }
}

echo "<hr>";
echo "<a href='?simulate=1&file=test.zip'>Simular descarga con archivo test.zip</a>";
?>
