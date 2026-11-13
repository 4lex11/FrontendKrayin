# Changelog

## Unreleased

### Added
- [Churn] Seguimiento manual por cliente/corte/versión con responsable, nota, estados, autoría, historial y control de cambios concurrentes.
- [Churn] Sugerencias orientativas deterministas en listado y detalle, con prioridad de calidad/elegibilidad y revisión humana obligatoria.
- Added persistent Churn prediction queue, permission-protected updates, scoped progress, and bounded worker command with duplicate protection.

- [Churn] Detalle autorizado por cliente con variables históricas, cotejo de hash de entrada, historial por corte/versión y ejecuciones recientes.
- [Churn] Listado paginado por cliente/corte/versión, filtros y búsqueda, contadores consistentes y estados de predicción ausente o actualización fallida.
- [Churn] MenÃº y pÃ¡gina base con layout de Krayin, permisos ACL, prefijo administrativo configurable y conteo limitado por visibilidad global/grupo/individual.
- [Churn] Persistencia versionada e inmutable de predicciones, historial de ejecuciones, control de duplicados y opciÃƒÂ³n --save del comando histÃƒÂ³rico.
- [Churn] Cliente HTTPS para predicciÃƒÆ’Ã‚Â³n transaccional, verificaciÃƒÆ’Ã‚Â³n TLS, contratos, reintentos acotados y comando de consulta histÃƒÆ’Ã‚Â³rica sin persistencia.
- [Churn] Pruebas del consumidor PHP y aceptaciÃƒÆ’Ã‚Â³n contra el servicio Python real.
- [Churn] ImportaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n transaccional e idempotente del dataset Superstore, con validaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n SHA-256, mapeo de contactos existentes y tablas propias de historial de ventas.
- [Churn] Pruebas del lector CSV, modo de simulaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n, repeticiÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n sin duplicados y cancelaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n ante conflictos.
