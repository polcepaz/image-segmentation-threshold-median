# Trabajo Encargado 1 (TE1) — Sistema Web de Procesamiento y Segmentación de Imágenes Médicas

Aplicación web para cargar, almacenar, visualizar, procesar y analizar imágenes
médicas mediante técnicas de procesamiento digital de imágenes.

## Funcionalidades

- **Dashboard** con indicadores y gráficos (Chart.js).
- **Gestión de imágenes** (PNG, JPG, JPEG, TIFF) con validación, metadatos y
  clasificación por modalidad (TC, OCT, Otra).
- **Histograma** de 256 niveles con estadísticas (mín, máx, media, desviación).
- **Filtro de mediana** (kernels 3×3, 5×5, 7×7).
- **Umbralización fija** (T configurable, máscara invertible).
- **Umbral automático de Otsu**.
- **Comparación** de original / escala de grises / mediana / umbral fijo / Otsu.
- **Gestión de experimentos** (parámetros, métricas y archivos persistidos).
- **Informes** en PDF y CSV.
- **Auditoría** de acciones.

## Tecnologías

| Capa          | Tecnología                                        |
|---------------|---------------------------------------------------|
| Backend       | PHP 8.2 (MVC, PDO, API REST interna)              |
| Base de datos | MySQL 8 / MariaDB (8 tablas, integridad referencial) |
| Frontend      | HTML5, Bootstrap 5, JavaScript ES6, Chart.js, SweetAlert2, DataTables |
| Procesamiento | Python + OpenCV + NumPy (con fallback a PHP GD)   |

## Arquitectura

```
web14/
├── app/
│   ├── Controllers/      # Controladores MVC
│   ├── Models/           # Modelos (PDO)
│   ├── Services/         # Lógica de negocio y procesamiento
│   ├── Middleware/       # CSRF
│   └── Core/             # Router, Database, View, Session
├── config/               # Configuración global
├── database/
│   ├── schema.sql        # Esquema de la base de datos
│   └── seed.sql          # Datos iniciales
├── public/               # DocumentRoot (front controller + assets)
├── resources/views/      # Plantillas (layouts, dashboard, ...)
├── routes/               # web.php y api.php
├── storage/              # originals, processed, reports, logs (protegido)
├── python/
│   ├── processor.py      # Procesamiento OpenCV (JSON stdin/stdout)
│   └── requirements.txt
└── tests/                # Pruebas funcionales
```

## Requisitos

- XAMPP (Apache + PHP 8.2 + MySQL/MariaDB) u otro servidor equivalente.
- Extensiones PHP: `pdo_mysql`, `gd`, `mbstring`, `json`, `fileinfo`.
- (Opcional, recomendado) Python 3.10–3.12 con `opencv-python` y `numpy`.
  Sin Python, el sistema usa automáticamente el motor **GD** como fallback.

## Instalación

1. Copie el proyecto en `C:\xampp\htdocs\web14` (o la carpeta deseada).

2. Ajuste las credenciales de base de datos en `config/config.php`:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_NAME', 'simed');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

3. Ejecute el instalador (crea la base de datos, carga el seed e importa el
   dataset inicial de 5 imágenes):
   ```bash
   php install.php
   ```

4. Acceda a `http://localhost/web14/` desde su navegador.

### Procesamiento con Python/OpenCV (opcional)

Para usar OpenCV (mayor fidelidad), instale las dependencias en un entorno
Python 3.10–3.12:

```bash
pip install -r python/requirements.txt
```

El sistema detecta OpenCV automáticamente; si no está disponible, usa GD.

## Dataset inicial

Las cinco imágenes del dataset se importan automáticamente durante la
instalación:

| Archivo            | Modalidad | Descripción        |
|--------------------|-----------|--------------------|
| 01_1_original.png  | OCT       | Imagen OCT         |
| P2_IMG0218.png     | TC        | Corte TC de tórax  |
| P6_IMG0175.png     | TC        | Corte TC de tórax  |
| P7_IMG0183.png     | TC        | Corte TC de tórax  |
| P10_IMG0106.png    | TC        | Corte TC de tórax  |

Los archivos originales se conservan intactos en `storage/originals/`.

## Pruebas

Para verificar que el histograma, el filtro de mediana y las umbralizaciones
producen resultados reales:

```bash
php tests/test_processing.php
```

## Seguridad

- Consultas preparadas (PDO) contra inyección SQL.
- Protección CSRF en formularios y API.
- Escape de salida HTML (XSS).
- Validación de archivos (extensión, MIME real, tamaño, dimensiones).
- Directorios `storage/` y `python/` protegidos contra acceso web.
- Registro de auditoría.
- El módulo Python no permite ejecución arbitraria de código (operaciones y
  parámetros estrictamente tipados y validados).
