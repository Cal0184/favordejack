EL EMPORIO DE ECIX'S — AUTENTICACIÓN COMPARTIDA CON BANCA DIGITAL

Ruta esperada en el servidor:
/home/cal/web/cal.edsn.cl/public_html/favordejack/emporio/

Dependencias compartidas:
/home/cal/web/cal.edsn.cl/public_html/favordejack/bancadigital/session.php
/home/cal/web/cal.edsn.cl/public_html/favordejack/bancadigital/db.php

QUÉ CAMBIÓ
- El Emporio ya NO tiene usuarios propios.
- Se eliminaron data/users.json y data/users-backup.json.
- Ya no existe login propio del Emporio.
- Ya no se crean usuarios desde Administración del Emporio.
- Ya no se cambian contraseñas dentro del Emporio.
- Usa $_SESSION['banca_user'], la misma sesión que BancaDigital.
- Cada carga valida Usuario + Jugador en MySQL para comprobar que ambos sigan activos.
- El nombre visible se toma de Jugador.Nombre.
- El rol admin se toma de Usuario.Rol.
- "Cambiar contraseña" abre bancadigital/cambiar-clave.php.
- "Salir" destruye la sesión compartida: también cierra BancaDigital.
- Favoritos y solicitudes se guardan usando el Username real de BancaDigital.
- El antiguo favorito del usuario local "admin" se recupera automáticamente para "cal" si todavía no existe una entrada propia.

INSTALACIÓN
1. Haz copia de seguridad de la carpeta actual emporio/ del servidor.
2. Reemplaza el contenido de emporio/ por esta carpeta.
3. NO reemplaces ni muevas bancadigital/session.php ni bancadigital/db.php.
4. Comprueba que las rutas sean hermanas:
   favordejack/bancadigital/
   favordejack/emporio/
5. Inicia sesión en BancaDigital.
6. Abre /favordejack/emporio/.

RESULTADO ESPERADO
- Si ya estás conectado en BancaDigital, el Emporio entra directamente.
- Si no hay sesión, el Emporio te envía a /favordejack/bancadigital/.
- Las contraseñas son únicamente las de la tabla Usuario de MySQL.
- Una contraseña cambiada desde BancaDigital funciona automáticamente para todo el sistema.

DATOS QUE SIGUEN SIENDO PROPIOS DEL EMPORIO
- data/catalog.json
- data/cards.json
- data/item_overrides.json
- data/favorites.json
- data/requests.json
- data/services.json
- data/audit.json
- Cartas/

No hace falta ejecutar SQL para esta actualización.
