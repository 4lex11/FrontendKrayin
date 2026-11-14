# Krayin en Render Free

Preparación aislada del XAMPP local. Dockerfile: deploy/render/Dockerfile; contexto: raíz; rama: main; plan: free.

Variables requeridas: APP_KEY estable, APP_URL HTTPS, DB_CONNECTION=mysql, DB_HOST, DB_PORT=4000, DB_DATABASE=krayin_crm, DB_USERNAME, DB_PASSWORD, CHURN_API_URL HTTPS y CHURN_API_KEY. Configurar los secretos únicamente en Render. Reutilizar la clave de aplicación de origen si existen valores cifrados que deban conservarse. No generar una clave nueva en cada arranque.

Antes del primer arranque, ejecutar por Laravel la migración 2026_11_14_000001_create_cloud_sessions_table sobre TiDB. No se ejecutan migraciones destructivas ni seeders al arrancar.

Sesiones en TiDB. Cola propia de Churn en churn_work_items, atendida por churn:work dentro del mismo servicio. Las demás tareas Laravel requieren un worker adicional si se habilitan. No se promete procesar tareas mientras Render duerme. Cache y logs locales son efímeros. Adjuntos y exportaciones locales no persisten: este perfil solo cubre la demostración de datos y predicciones, no almacenamiento de archivos de usuarios.

HTTPS termina en Render. Apache entrega únicamente public/, marca HTTPS para PHP y bloquea el instalador. La API y TiDB verifican certificados con el bundle CA del contenedor.

Validación pendiente de build real Linux y de prueba integral. No hay Docker disponible localmente. Verificar dependencias Composer, límite de memoria, sesión después de reinicio, permisos de Churn, llamada API, cola y registros en TiDB. El arranque de la API tras inactividad puede exceder los tiempos de espera del cliente.
