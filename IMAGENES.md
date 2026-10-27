# Dónde vive cada imagen

Regla principal: **cada imagen se guarda una sola vez**. Si dos páginas usan la
misma imagen, las dos apuntan al mismo archivo; nunca se copia.

| Qué | Carpeta | Quién la usa |
|---|---|---|
| Logo del gremio | `assets/logo-favor-de-jack.webp` | Portada, login, Banca, Dados (`FAVOR_LOGO` en `includes/paths.php`) |
| Imágenes de la portada y del sitio | `assets/` | Portada |
| Iconos de jugadores y personajes | `media/icons/<jugador>/` | Banca, Archivos, Relaciones, Inventario, Mi perfil |
| Retratos de personajes | `media/characters/<jugador>/` | Banca, Archivos, Relaciones |
| Fotos de perfil | `media/profiles/` | Reservada (hoy ninguna página la usa) |
| Vendedores y dueños de tienda | `media/shops/<tienda>/` | La tienda **y** el mapa |
| Fondo interior y dibujos propios de una tienda | `tiendas/<tienda>/assets/` | Solo esa tienda |
| Cartas del Emporio | `tiendas/emporio/Cartas/<Nombre>.webp` | Emporio |
| Mapa de Waterdeep | `mapa/assets/mapa-waterdeep.webp` | Mapa |

## Reglas

1. **Una imagen, un archivo.** Lo que se usa en más de una página va en `media/`.
2. **Formato WebP.** Las fotos y dibujos se guardan en `.webp` (pesan entre 5 y 10
   veces menos que un PNG y se ven igual). Al subir un retrato en Archivos, la web
   lo endereza, lo reduce y lo guarda en WebP sola.
3. **Nombres en minúsculas, sin espacios dobles ni `.webp.webp`.** La carpeta del
   jugador es la de su nombre en la base de datos (`lis`, no `liz`).
4. **La base de datos guarda la ruta `media/…`** y todas las páginas la leen con la
   misma función (`favor_asset_url` en PHP, `resolveAssetSrc` en la Banca). No hay
   copias en `bancadigital/`.
5. **Las miniaturas no son copias.** `tiendas/emporio/Cartas/mini/` guarda una versión
   pequeña de cada carta para que la cuadrícula cargue rápido. Se crea sola desde la
   carta; si se borra, se vuelve a crear.
6. **Nada se borra a mano.** Lo que sobra se aparta en `respaldos/` con
   *Mi cuenta → Ordenar imágenes* (iconos, retratos y carpetas antiguas) o con
   *Admin del Emporio → Duplicados y limpieza* (cartas).

## Cómo saber si hay duplicados

*Mi cuenta → Ordenar imágenes* revisa toda la web y lista las imágenes guardadas
más de una vez (mismo contenido aunque tengan otro nombre). El Emporio además
compara la ilustración de cada carta, así que encuentra la misma carta aunque el
archivo sea distinto.

## De dónde salían las copias (octubre de 2026)

- La Banca guardaba en la base de datos `media/…` pero buscaba los archivos en
  `bancadigital/`, así que cada icono y retrato se subía dos veces.
- El Emporio existía dos veces (`emporio/` y `tiendas/emporio/`), con las 140 cartas
  en cada uno, y además en PNG y en WebP.
- Los retratos de los vendedores estaban en el mapa y otra vez en cada tienda.
- El logo estaba tres veces con nombres distintos.
