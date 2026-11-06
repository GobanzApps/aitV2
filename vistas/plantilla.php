<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="vistas/img/plantilla/icono-negro.png">
    <title>Centro de Atencion AIT</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4/dist/css/adminlte.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- Tom Select -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">

    <!-- Quill 2 (editor de texto enriquecido) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css">

    <!-- Forzar tema claro/oscuro ANTES de pintar (evita FOUC) -->
    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = stored === 'dark' ? 'dark'
                        : stored === 'light' ? 'light'
                        : (systemDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>

    <style>
        /* ====== Botón +/- de filas colapsadas (Responsive) ====== */
        table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:before,
        table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control:before {
            content: '\f067';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            background-color: #0d6efd !important;
            border: 1px solid #0d6efd !important;
            color: #fff !important;
            border-radius: 50% !important;
            box-shadow: none !important;
            width: 20px;
            height: 20px;
            line-height: 20px;
            text-align: center;
            display: inline-block;
            font-size: 10px;
        }
        table.dataTable.dtr-inline.collapsed > tbody > tr.parent > td.dtr-control:before,
        table.dataTable.dtr-inline.collapsed > tbody > tr.parent > th.dtr-control:before {
            content: '\f068';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            background-color: #dc3545 !important;
            border: 1px solid #dc3545 !important;
            color: #fff !important;
        }

        /* ====== Layout interno de las filas colapsadas ====== */
        .dtr-details {
            width: 100%;
            margin: 0;
            word-break: break-word;
        }
        .dtr-details li {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 8px;
            padding: 6px 10px;
            border-bottom: 1px solid #eee;
            list-style: none;
        }
        .dtr-details li:last-child { border-bottom: none; }
        .dtr-details li span.dtr-title {
            font-weight: 600;
            color: #555;
            margin-right: 10px;
        }
        .dtr-details li span.dtr-data {
            text-align: right;
            flex: 1;
            word-break: break-word;
        }

        /* ====== DataTables wrapper ====== */
        .dataTables_wrapper { width: 100%; }

        /* ====== FIX: solo los badges pueden hacer wrap ======
           (NO tocar td/th en general, o se rompe el Responsive) */
        .table td .badge {
            white-space: normal !important;
            word-break: break-word;
            display: inline-block;
            max-width: 100%;
            line-height: 1.25;
        }

        /* ====== Tom Select: tags con color ====== */
        .ts-wrapper .ts-control .item {
            background-color: var(--tag-color, #0d6efd) !important;
            color: #fff !important;
            border: none !important;
            border-radius: 6px !important;
            padding: 3px 10px !important;
            margin: 2px !important;
            font-weight: 500 !important;
        }
        .ts-wrapper .ts-control .item .remove {
            border-left: 1px solid rgba(255,255,255,0.4) !important;
            color: #fff !important;
            padding: 0 6px !important;
        }
        .ts-dropdown .option .tag-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
        }
        .ts-dropdown .option {
            display: flex;
            align-items: center;
        }
        .ts-dropdown .option .option-label { flex: 1; }
        .ts-wrapper.multi .ts-control > div { cursor: pointer; }

        /* ══════════════════════════════════════════════════════
           ✅ TOM SELECT — MODO OSCURO
           Corrige el texto ilegible en selects, dropdowns
           y opciones activas cuando data-bs-theme="dark"
           ══════════════════════════════════════════════════════ */

        /* ── Control (la cajita donde se eligen los items) ── */
        [data-bs-theme="dark"] .ts-wrapper .ts-control {
            background-color: #2b3035 !important;
            border-color: #495057 !important;
            color: #e9ecef !important;
        }
        [data-bs-theme="dark"] .ts-wrapper .ts-control input,
        [data-bs-theme="dark"] .ts-wrapper .ts-control input:focus {
            color: #e9ecef !important;
            background: transparent !important;
        }
        [data-bs-theme="dark"] .ts-wrapper .ts-control input::placeholder {
            color: #adb5bd !important;
        }
        [data-bs-theme="dark"] .ts-wrapper .ts-control .placeholder,
        [data-bs-theme="dark"] .ts-wrapper .ts-control .ts-placeholder {
            color: #adb5bd !important;
        }

        /* ── Chips seleccionados (item / item-tag) ── */
        [data-bs-theme="dark"] .ts-wrapper .ts-control .item,
        [data-bs-theme="dark"] .ts-wrapper .ts-control .item-tag {
            color: #fff !important;
        }

        /* ── Dropdown ── */
        [data-bs-theme="dark"] .ts-dropdown {
            background-color: #212529 !important;
            border-color: #495057 !important;
            color: #e9ecef !important;
        }
        [data-bs-theme="dark"] .ts-dropdown .option {
            color: #e9ecef !important;
        }
        [data-bs-theme="dark"] .ts-dropdown .option .option-label {
            color: inherit !important;
        }

        /* ── Opción activa / hover (la que se ve resaltada) ── */
        [data-bs-theme="dark"] .ts-dropdown .option.active,
        [data-bs-theme="dark"] .ts-dropdown .option:hover,
        [data-bs-theme="dark"] .ts-dropdown .option.selected {
            background-color: #0d6efd !important;
            color: #fff !important;
        }
        [data-bs-theme="dark"] .ts-dropdown .option.active .option-label,
        [data-bs-theme="dark"] .ts-dropdown .option:hover .option-label,
        [data-bs-theme="dark"] .ts-dropdown .option.selected .option-label {
            color: #fff !important;
        }

        /* ── Textos secundarios del dropdown ── */
        [data-bs-theme="dark"] .ts-dropdown .no-results,
        [data-bs-theme="dark"] .ts-dropdown .create,
        [data-bs-theme="dark"] .ts-dropdown .optgroup-header {
            color: #adb5bd !important;
            background-color: #212529 !important;
        }

        /* ── Input nativo (fallback si no aplica Tom Select) ── */
        [data-bs-theme="dark"] select.form-select,
        [data-bs-theme="dark"] select.form-control {
            color: #e9ecef !important;
            background-color: #2b3035 !important;
        }

        /* ====== Dropdown de temas ====== */
        .theme-toggle-btn {
            background: transparent;
            border: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 6px;
            transition: background 0.15s ease;
        }
        .theme-toggle-btn:hover { background: rgba(0,0,0,0.06); }
        [data-bs-theme="dark"] .theme-toggle-btn:hover { background: rgba(255,255,255,0.08); }
        .theme-toggle-btn .theme-icon { font-size: 1.05rem; }
        .dropdown-menu .theme-option { display: flex; align-items: center; gap: 8px; }
        .dropdown-menu .theme-option i { width: 16px; text-align: center; }
        .dropdown-menu .theme-option .check { margin-left: auto; visibility: hidden; }
        .dropdown-menu .theme-option.active .check { visibility: visible; }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
        <?php include "vistas/modulos/header.php"; ?>
        <?php include "vistas/modulos/sidebar.php"; ?>
        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="mb-0"><?php echo $titulo ?? 'Dashboard'; ?></h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="inicio">Inicio</a></li>
                                <li class="breadcrumb-item active"><?php echo $titulo ?? 'Dashboard'; ?></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div class="app-content">
                <div class="container-fluid">
                    <?php
                    if (isset($_GET["ruta"])) {
                        $rutas = ["inicio", "users", "user", "perfil", "salir", "coordinacion", "rol", "tag",
                                "tipoEntidad", "host-tipo", "host", "hosts", "entidad", "medio", "prioridad",
                                "status", "tickets", "ticket", "doc-tipo", "doc", "actividad", "tag-tipo"];
                        if (in_array($_GET["ruta"], $rutas)) {
                            include "vistas/modulos/" . $_GET["ruta"] . ".php";
                        }
                    } else {
                        include "vistas/modulos/inicio.php";
                    }
                    ?>
                </div>
            </div>
        </main>
        <?php include "vistas/modulos/footer.php"; ?>
    </div>

    <!-- jQuery PRIMERO -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4/dist/js/adminlte.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- Tom Select -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Quill 2 -->
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

    <!-- ============================================================
         Color Modes (Light / Dark / Auto) — compatible AdminLTE 4
         ============================================================ -->
    <script>
        (function () {
            'use strict';

            const STORAGE_KEY = 'theme';
            const getStored = () => localStorage.getItem(STORAGE_KEY);
            const systemDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

            function applyTheme(mode) {
                let resolved = mode;
                if (mode === 'auto') {
                    resolved = systemDark() ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-bs-theme', resolved);
                updateUI(mode);
            }

            function updateUI(mode) {
                const current = mode || 'auto';
                document.querySelectorAll('.theme-option').forEach(el => {
                    el.classList.toggle('active', el.dataset.themeValue === current);
                });
                const icon = document.getElementById('themeIcon');
                if (icon) {
                    icon.className = 'theme-icon';
                    if (current === 'light') {
                        icon.classList.add('fas', 'fa-sun');
                    } else if (current === 'dark') {
                        icon.classList.add('fas', 'fa-moon');
                    } else {
                        icon.classList.add('fas', 'fa-circle-half-stroke');
                    }
                }
            }

            const stored = getStored() || 'auto';
            applyTheme(stored);

            document.addEventListener('click', function (e) {
                const opt = e.target.closest('.theme-option');
                if (!opt) return;
                e.preventDefault();
                const mode = opt.dataset.themeValue;
                if (mode === 'auto') {
                    localStorage.removeItem(STORAGE_KEY);
                } else {
                    localStorage.setItem(STORAGE_KEY, mode);
                }
                applyTheme(mode);
            });

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                const current = getStored() || 'auto';
                if (current === 'auto') applyTheme('auto');
            });
        })();
    </script>

    <script src="vistas/js/plantilla.js"></script>
    <script src="vistas/js/user.js"></script>
</body>
</html>