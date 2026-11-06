<?php
// Valores por defecto si no existen en sesión
$userNombre = $_SESSION["name"] ?? ($_SESSION["user"] ?? "Invitado"); 
$userFoto   = $_SESSION["foto"] ?? "";

if(empty($userFoto)){
    $userFoto = "vistas/img/usuarios/default/anonymous.png";
}
?>
<header class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>
        <ul class="navbar-nav ms-auto">

            <!-- ════════════════ Campanita de notificaciones ════════════════ -->
            <li class="nav-item dropdown me-2">
                <a href="#"
                   class="nav-link position-relative"
                   data-bs-toggle="dropdown"
                   id="notifBell"
                   title="Notificaciones"
                   aria-expanded="false">
                    <i class="fas fa-bell"></i>
                    <span id="notifBadge"
                          class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
                          style="font-size:0.62rem; padding:0.25em 0.5em;">
                        0
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow"
                    id="notifDropdown"
                    style="width:360px; max-height:440px; overflow-y:auto; padding:0;">
                    <li class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                        <strong><i class="fas fa-bell me-1 text-primary"></i> Notificaciones</strong>
                        <span id="notifCountText" class="text-muted small">0 pendientes</span>
                    </li>
                    <div id="notifList">
                        <li class="px-3 py-4 text-center text-muted">
                            <i class="fas fa-spinner fa-spin me-2"></i>Cargando...
                        </li>
                    </div>
                </ul>
            </li>

            <!-- ════════════════ Selector de Tema ════════════════ -->
            <li class="nav-item dropdown me-2">
                <a href="#" class="theme-toggle-btn nav-link" data-bs-toggle="dropdown" aria-expanded="false" title="Cambiar tema">
                    <i id="themeIcon" class="theme-icon fas fa-circle-half-stroke"></i>
                    <i class="fas fa-caret-down" style="font-size:0.7rem;"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a href="#" class="dropdown-item theme-option" data-theme-value="light">
                            <i class="fas fa-sun"></i>
                            <span>Light</span>
                            <i class="fas fa-check check text-primary"></i>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="dropdown-item theme-option" data-theme-value="dark">
                            <i class="fas fa-moon"></i>
                            <span>Dark</span>
                            <i class="fas fa-check check text-primary"></i>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="dropdown-item theme-option" data-theme-value="auto">
                            <i class="fas fa-circle-half-stroke"></i>
                            <span>Auto</span>
                            <i class="fas fa-check check text-primary"></i>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ════════════════ Menú de Usuario ════════════════ -->
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="<?php echo htmlspecialchars($userFoto); ?>"
                         class="user-image rounded-circle shadow"
                         alt="User Image"
                         style="width:30px; height:30px; object-fit:cover;"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($userNombre); ?>&background=0d6efd&color=fff';">
                    <span class="d-none d-md-inline ms-2"><?php echo htmlspecialchars($userNombre); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="perfil">
                            <i class="fas fa-user me-2"></i> Perfil
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="salir">
                            <i class="fas fa-sign-out-alt me-2"></i> Salir
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</header>

<!-- ═══════════════ SCRIPTS DE NOTIFICACIÓN ═══════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function(){

    const notifBadge = document.getElementById('notifBadge');
    const notifList  = document.getElementById('notifList');
    const notifCount = document.getElementById('notifCountText');

    if(!notifBadge || !notifList) return;

    /*=============================================
    Utilidades
    =============================================*/
    function tiempoRelativo(fechaStr){
        if(!fechaStr) return '';
        const fecha = new Date(fechaStr.replace(' ', 'T'));
        const ahora = new Date();
        const diffMs = ahora - fecha;
        if(diffMs < 0) return '0 min';

        const diffMin = Math.floor(diffMs / 60000);
        if(diffMin < 1)  return '0 min';
        if(diffMin < 60) return diffMin + ' min';

        const diffHrs = Math.floor(diffMin / 60);
        if(diffHrs < 24) return diffHrs + ' h';

        const diffD = Math.floor(diffHrs / 24);
        return diffD + ' d';
    }

    function esc(str){
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    /*=============================================
    Renderizar notificaciones
    =============================================*/
    function renderNotifs(data){
        const arr = Array.isArray(data) ? data : [];

        if(arr.length === 0){
            notifBadge.classList.add('d-none');
            notifBadge.textContent = '0';
            if(notifCount) notifCount.textContent = '0 pendientes';
            notifList.innerHTML = `
                <li class="px-3 py-4 text-center text-muted">
                    <i class="fas fa-check-circle me-1 text-success"></i>
                    Sin notificaciones nuevas
                </li>`;
            return;
        }

        notifBadge.classList.remove('d-none');
        notifBadge.textContent = arr.length;
        if(notifCount) notifCount.textContent = arr.length + ' pendiente' + (arr.length === 1 ? '' : 's');

        let html = '';
        arr.forEach(function(n){
            /* ✅ Se usa "fecha" (creación) para calcular el tiempo transcurrido,
               NO "modificacion" (que puede cambiar al editar el comentario). */
            const mins    = tiempoRelativo(n.fecha || n.modificacion);
            const ticket  = esc(n.id_ticket);
            const status  = esc(n.status_nombre || '');
            const color   = esc(n.status_color || '#0d6efd');
            const id      = parseInt(n.id) || 0;

            html += `
                <li class="px-3 py-2 border-bottom">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div style="min-width:0; flex:1;">
                            <div class="fw-semibold" style="font-size:0.88rem;">
                                <i class="fas fa-ticket-alt me-1 text-primary"></i>
                                Ticket #${ticket}
                            </div>
                            <div class="d-flex align-items-center gap-1 mt-1" style="font-size:0.72rem;">
                                <span class="badge"
                                      style="background-color:${color}; color:#fff; font-size:0.62rem;">
                                    ${status}
                                </span>
                                <span class="text-muted">
                                    <i class="far fa-clock ms-1"></i> ${mins}
                                </span>
                            </div>
                        </div>
                        <button type="button"
                                class="btn btn-sm btn-primary btnAceptarNotif"
                                data-id="${id}"
                                style="white-space:nowrap;">
                            <i class="fas fa-check me-1"></i> Aceptar
                        </button>
                    </div>
                </li>`;
        });
        notifList.innerHTML = html;
    }

    /*=============================================
    Cargar notificaciones (AJAX)
    =============================================*/
    function cargarNotifs(){
        const fd = new FormData();
        fd.append('listarNotificaciones', '1');

        fetch('ajax/tstatus.ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(resp => {
                if(resp.status === 'ok'){
                    renderNotifs(resp.data || []);
                }
            })
            .catch(err => console.error('[Notif]', err));
    }

    cargarNotifs();

    /* Polling cada 30 segundos */
    let notifInterval = setInterval(cargarNotifs, 30000);

    /* Pausar polling cuando la pestaña no está visible */
    document.addEventListener('visibilitychange', function(){
        if(document.hidden){
            clearInterval(notifInterval);
        } else {
            cargarNotifs();
            notifInterval = setInterval(cargarNotifs, 30000);
        }
    });

    /*=============================================
    ✅ Aceptar notificación (delegado)
    → Al aceptar, redirige a la URL del ticket
    =============================================*/
    document.addEventListener('click', async function(e){
        const btn = e.target.closest('.btnAceptarNotif');
        if(!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const id = btn.getAttribute('data-id');
        if(!id) return;

        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const fd = new FormData();
            fd.append('aceptarNotificacion', '1');
            fd.append('idTstatus', id);

            const res  = await fetch('ajax/tstatus.ajax.php', { method: 'POST', body: fd });
            const data = await res.json();

            if(data.status === 'success'){
                const idTicket = parseInt(data.idTicket) || 0;

                if(typeof Swal !== 'undefined'){
                    Swal.fire({
                        icon: 'success',
                        title: '¡Ticket aceptado!',
                        html: (data.mensaje || 'Ticket asignado correctamente') +
                              '<br><small class="text-muted">Redirigiendo al ticket…</small>',
                        timer: 1500,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        if(idTicket > 0){
                            window.location.href = 'index.php?ruta=ticket&id=' + idTicket;
                        } else {
                            cargarNotifs();
                        }
                    });
                } else {
                    // Fallback sin SweetAlert
                    if(idTicket > 0){
                        window.location.href = 'index.php?ruta=ticket&id=' + idTicket;
                    } else {
                        cargarNotifs();
                    }
                }
            } else {
                if(typeof Swal !== 'undefined'){
                    Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje || 'No se pudo aceptar' });
                } else {
                    alert(data.mensaje || 'No se pudo aceptar');
                }
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        } catch(err) {
            console.error(err);
            if(typeof Swal !== 'undefined'){
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión con el servidor' });
            }
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    });

});
</script>