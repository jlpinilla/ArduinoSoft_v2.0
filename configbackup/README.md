# Carpeta de Copias de Seguridad de Configuración

Esta carpeta almacena automáticamente las copias de seguridad del archivo `config.ini` que se generan cada vez que se modifica la configuración del sistema desde el panel de administración.

## Formato de los archivos de backup

Los archivos se guardan con el siguiente formato:
```
config_backup_YYYY-MM-DD_HH-mm-ss.ini
```

Por ejemplo:
- `config_backup_2025-06-08_23-47-30.ini`
- `config_backup_2025-06-09_10-15-45.ini`

## Propósito

- **Seguridad**: Permite restaurar configuraciones anteriores en caso de errores
- **Auditoría**: Mantiene un historial de cambios de configuración
- **Recuperación**: Facilita la recuperación del sistema ante problemas

## Mantenimiento

Se recomienda revisar periódicamente esta carpeta y eliminar backups antiguos para evitar acumulación excesiva de archivos.
