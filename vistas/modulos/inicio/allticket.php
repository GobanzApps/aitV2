<?php
/*=============================================
allticket.php — Tickets pendientes (Inicio)
=============================================*/

require_once __DIR__ . "/allticket_core.php";

$resultado = allticketCompute();

if(!$resultado['ok']){
    echo '<div class="alert alert-danger">'
       . '<i class="fas fa-exclamation-triangle me-2"></i>'
       . htmlspecialchars($resultado['error'])
       . '</div>';
    return;
}

$esAdminReal      = $resultado['esAdminReal'];
$esCentroAtencion = $resultado['esCentroAtencion'];
$ticketsFiltrados = $resultado['tickets'];
$tbodyHtml        = allticketRenderTbody($resultado);
?>

<!-- ✅ CSS restaurado de tstatus.php -->
<style>
    /* ─── Chips de tiempo ─── */
    .tstatus-readtime {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        color: #0f5132;
        background: rgba(25,135,84,0.12);
        border: 1px solid rgba(25,135,84,0.25);
        border-radius: 999px;
        padding: 1px 8px;
        margin-left: 4px;
    }
    [data-bs-theme="dark"] .tstatus-readtime {
        color: #75d9a3;
        background: rgba(25,135,84,0.18);
        border-color: rgba(25,135,84,0.35);
    }
    .tstatus-pendingtime {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        color: #7a5300;
        background: rgba(255,193,7,0.18);
        border: 1px solid rgba(255,193,7,0.45);
        border-radius: 999px;
        padding: 1px 8px;
        margin-left: 4px;
    }
    [data-bs-theme="dark"] .tstatus-pendingtime {
        color: #ffce4d;
        background: rgba(255,193,7,0.16);
        border-color: rgba(255,193,7,0.4);
    }

    /* ═══ NUEVO ═══  Chip azul "sin cambios" (status N/R ya aceptado) */
    .tstatus-waitingtime {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        color: #055160;
        background: rgba(13,202,240,0.15);
        border: 1px solid rgba(13,202,240,0.40);
        border-radius: 999px;
        padding: 1px 8px;
        margin-left: 4px;
    }
    [data-bs-theme="dark"] .tstatus-waitingtime {
        color: #6ee7f5;
        background: rgba(13,202,240,0.18);
        border-color: rgba(13,202,240,0.35);
    }
    /* ═══ FIN NUEVO ═══ */

    /* ─── Caja del comentario ─── */
    .tstatus-comment {
        display: flex;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 14px;
        background: linear-gradient(135deg, #f8f9ff 0%, #eef3ff 100%);
        border: 1px solid rgba(13,110,253,0.15);
        position: relative;
        transition: box-shadow .2s ease, transform .15s ease;
        overflow: hidden;
    }
    .tstatus-comment:hover {
        box-shadow: 0 6px 18px rgba(13,110,253,0.12);
        transform: translateY(-1px);
    }
    .tstatus-comment::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, #0d6efd, #6ea8fe);
    }
    .tstatus-comment__icon {
        flex-shrink: 0;
        width: 38px; height: 38px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0d6efd, #4d8bfd);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        box-shadow: 0 4px 10px rgba(13,110,253,0.35);
    }
    .tstatus-comment__body { flex: 1; min-width: 0; }
    .tstatus-comment__header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
    }
    .tstatus-comment__label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #0d6efd;
    }
    .tstatus-comment__time {
        font-size: 0.72rem;
        color: #8a94a6;
        margin-left: auto;
    }
    .tstatus-comment__text {
        color: #2c3345;
        line-height: 1.5;
        font-size: 0.92rem;
        white-space: pre-wrap;
        word-break: break-word;
    }
    [data-bs-theme="dark"] .tstatus-comment {
        background: linear-gradient(135deg, #1b2233 0%, #1f2740 100%);
        border-color: rgba(110,168,254,0.2);
    }
    [data-bs-theme="dark"] .tstatus-comment__text { color: #dde3ee; }
    [data-bs-theme="dark"] .tstatus-comment__time { color: #98a1b3; }
</style>

<div class="card" id="allticket-card">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h3 class="card-title mb-0">
            <i class="fas fa-ticket-alt me-2"></i>
            Tickets Pendientes
            <span class="badge text-bg-primary ms-2" id="allticket-count">
                <?php echo count($ticketsFiltrados); ?>
            </span>
        </h3>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <small class="text-muted" id="allticket-scope">
                <?php if($esAdminReal): ?>
                    <i class="fas fa-user-shield me-1"></i>
                    Vista Administrador — todos los tickets
                <?php elseif($esCentroAtencion): ?>
                    <i class="fas fa-headset me-1"></i>
                    Vista Centro de Atención — todos los tickets
                <?php else: ?>
                    <i class="fas fa-user me-1"></i>
                    Solo tus coordinaciones o tickets asignados
                <?php endif; ?>
            </small>

            <small class="text-muted" id="allticket-last-update" title="Última actualización">
                <i class="fas fa-sync-alt me-1" id="allticket-refresh-icon"></i>
                <span>Actualizado ahora</span>
            </small>
        </div>
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover table-striped align-middle tablas"
               style="width:100%" id="tablaAllTicket">
            <thead class="table-dark">
                <tr>
                    <th style="width:50px;" class="text-center">#</th>
                    <th style="width:130px;">Ticket</th>
                    <th>Entidad(es)</th>
                    <th style="width:280px;">Último Status</th>
                    <th style="width:160px;">Usuario(s) Asignado(s)</th>
                    <th>Coordinación(es)</th>
                    <th>Prioridad(es)</th>
                    <th style="width:90px;" class="text-center">Acción</th>
                </tr>
            </thead>
            <tbody id="allticket-tbody">
                <?php echo $tbodyHtml; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    'use strict';

    const REFRESH_MS = 60000;
    const ENDPOINT   = 'ajax/allticket.ajax.php';

    let refreshTimer = null;
    let isRefreshing = false;

    const table          = document.getElementById('tablaAllTicket');
    const countBadge     = document.getElementById('allticket-count');
    const lastUpdateSpan = document.querySelector('#allticket-last-update span');
    const refreshIcon    = document.getElementById('allticket-refresh-icon');

    if (!table) return;

    /* Formatear tiempo transcurrido */
    function formatearTiempoDesde(fechaStr) {
        if (!fechaStr) return '';
        const fecha = new Date(fechaStr.replace(' ', 'T'));
        const ahora = new Date();
        let seg = Math.floor((ahora - fecha) / 1000);
        if (seg < 0) seg = 0;

        const min = Math.floor(seg / 60);
        if (min < 1)  return 'menos de 1 min';
        if (min < 60) return min + ' min';

        const hrs     = Math.floor(min / 60);
        const minRest = min % 60;
        if (hrs < 24) return hrs + ' h' + (minRest > 0 ? ' ' + minRest + ' min' : '');

        const d       = Math.floor(hrs / 24);
        const hrsRest = hrs % 24;
        return d + ' d' + (hrsRest > 0 ? ' ' + hrsRest + ' h' : '');
    }

    /* Refresca los chips "hace X" del status (naranja = pendiente) */
    function refreshTstatusPendingTimes() {
        document.querySelectorAll('.tstatus-pendingtime').forEach(function(el) {
            const desde = el.getAttribute('data-desde');
            if (!desde) return;
            const txt = el.querySelector('.tstatus-pendingtime__text');
            if (!txt) return;
            txt.textContent = 'hace ' + formatearTiempoDesde(desde);
        });
    }

    /* ═══ NUEVO ═══  Chip azul "hace X sin cambios" (N/R aceptado) */
    function refreshTstatusWaitingTimes() {
        document.querySelectorAll('.tstatus-waitingtime').forEach(function(el) {
            const desde = el.getAttribute('data-desde');
            if (!desde) return;
            const txt = el.querySelector('.tstatus-waitingtime__text');
            if (!txt) return;
            txt.textContent = 'hace ' + formatearTiempoDesde(desde) + ' sin cambios';
        });
    }

    /* ═══ NUEVO ═══  Refresca ambos tipos de chips */
    function refreshTstatusAllTimes() {
        refreshTstatusPendingTimes();
        refreshTstatusWaitingTimes();
    }

    /* Actualiza la hora del header */
    function actualizarHora(iconoGirando) {
        if (lastUpdateSpan) {
            const now = new Date();
            const hh = String(now.getHours()).padStart(2,'0');
            const mm = String(now.getMinutes()).padStart(2,'0');
            const ss = String(now.getSeconds()).padStart(2,'0');
            lastUpdateSpan.textContent = 'Actualizado ' + hh + ':' + mm + ':' + ss;
        }
        if (refreshIcon) refreshIcon.classList.toggle('fa-spin', !!iconoGirando);
    }

    /* Inicializa DataTables */
    function initDataTable() {
        if (!window.jQuery || !jQuery.fn.DataTable) return;
        if (jQuery.fn.DataTable.isDataTable(table)) return;

        jQuery(table).DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Todos"]],
            language: {
                sProcessing:     "Procesando...",
                sLengthMenu:     "Mostrar _MENU_ registros",
                sZeroRecords:    "No se encontraron resultados",
                sEmptyTable:     '<div class="text-center text-muted py-4">'
                               + '<i class="fas fa-check-circle text-success fa-2x d-block mb-2"></i>'
                               + 'No hay tickets pendientes para mostrar'
                               + '</div>',
                sInfo:           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_",
                sInfoEmpty:      "Mostrando registros del 0 al 0 de un total de 0",
                sInfoFiltered:   "(filtrado de un total de _MAX_ registros)",
                sSearch:         "Buscar:",
                sLoadingRecords: "Cargando...",
                oPaginate: {
                    sFirst:    "Primero",
                    sLast:     "Último",
                    sNext:     "Siguiente",
                    sPrevious: "Anterior"
                },
                oAria: {
                    sSortAscending:  ": Activar para ordenar ascendente",
                    sSortDescending: ": Activar para ordenar descendente"
                }
            },
            columnDefs: [
                { orderable: false, targets: -1, responsivePriority: 1 }
            ]
        });
    }

    /* Refresco vía AJAX */
    async function refrescar() {
        if (isRefreshing) return;
        isRefreshing = true;
        actualizarHora(true);

        try {
            const res = await fetch(ENDPOINT, {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin'
            });

            if (res.status === 401) {
                if (refreshTimer) clearInterval(refreshTimer);
                console.warn('[allticket] Sesión expirada. Auto-refresh detenido.');
                return;
            }
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const html = await res.text();

            if (window.jQuery && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(table)) {
                jQuery(table).DataTable().destroy(false);
            }

            const tbodyFresh = document.getElementById('allticket-tbody');
            if (!tbodyFresh) {
                console.warn('[allticket] tbody no encontrado tras destroy');
                return;
            }
            tbodyFresh.innerHTML = html;

            // ✅ SOLUCIÓN: Contar las filas REALES antes de inicializar DataTables
            // Si el HTML está vacío, la cuenta es 0. De lo contrario, cuenta las filas.
            const filasReales = html.trim() === '' ? 0 : tbodyFresh.querySelectorAll('tr').length;
            if (countBadge) countBadge.textContent = filasReales;

            // Inicializamos DataTables DESPUÉS de contar
            initDataTable();

            /* ═══ NUEVO ═══  refresca los dos tipos de chips */
            refreshTstatusAllTimes();

            actualizarHora(false);
        } catch (err) {
            console.error('[allticket refresh]', err);
            actualizarHora(false);
        } finally {
            isRefreshing = false;
        }
    }

    function iniciarTimer() {
        if (refreshTimer) clearInterval(refreshTimer);
        refreshTimer = setInterval(function () {
            if (document.visibilityState === 'visible') {
                refrescar();
                refreshTstatusAllTimes();
            }
        }, REFRESH_MS);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') refrescar();
    });

    function arrancar() {
        iniciarTimer();
        actualizarHora(false);
        refreshTstatusAllTimes();
    }

    if (document.readyState === 'complete') {
        setTimeout(arrancar, 100);
    } else {
        window.addEventListener('load', function () {
            setTimeout(arrancar, 100);
        });
    }

})();
</script>