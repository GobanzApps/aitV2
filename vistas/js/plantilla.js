/*=============================================
DATATABLES RESPONSIVE - GENÉRICO
=============================================*/
$(document).ready(function () {

    if (typeof jQuery === 'undefined') {
        console.error('jQuery no está cargado');
        return;
    }

    // Inicializar cualquier tabla con la clase "tablas"
    $('.tablas').each(function () {
        var $tabla = $(this);

        // Evitar reinicialización
        if ($.fn.DataTable.isDataTable(this)) return;

        // ✅ Orden por defecto: [[0, 'asc']]
        //    Si la tabla define data-order (ej: '[[0, "desc"]]'),
        //    se usa ese orden para el render inicial.
        var orderConfig = [[0, 'asc']];
        var customOrder = $tabla.attr('data-order');
        if (customOrder) {
            try {
                var parsed = JSON.parse(customOrder);
                if (Array.isArray(parsed) && parsed.length) {
                    orderConfig = parsed;
                }
            } catch (e) {
                console.warn('[DataTables] data-order inválido en tabla:', customOrder, e);
            }
        }

        $tabla.DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Todos"]],
            order: orderConfig,
            language: {
                sProcessing:     "Procesando...",
                sLengthMenu:     "Mostrar _MENU_ registros",
                sZeroRecords:    "No se encontraron resultados",
                sEmptyTable:     "Ningún dato disponible en esta tabla",
                sInfo:           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_",
                sInfoEmpty:      "Mostrando registros del 0 al 0 de un total de 0",
                sInfoFiltered:   "(filtrado de un total de _MAX_ registros)",
                sInfoPostFix:    "",
                sSearch:         "Buscar:",
                sUrl:            "",
                sInfoThousands:  ",",
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
    });

});

/*=============================================
ENLACES INTERNOS
=============================================*/
$(document).ready(function () {

    // Convertir rutas absolutas /algo a relativas algo
    $('a[href^="/"]:not([href*="http"])').each(function () {
        var href = $(this).attr('href');
        if (href.startsWith('/')) {
            $(this).attr('href', href.substring(1));
        }
    });

    // Navegación para enlaces internos
    $(document).on('click', 'a', function (e) {
        var href = $(this).attr('href');

        if ($(this).attr('target') === '_blank') return;
        if ($(this).data('bs-toggle') || $(this).data('toggle')) return;
        if (
            href &&
            !href.startsWith('http') &&
            !href.startsWith('#') &&
            !href.startsWith('javascript:') &&
            !href.startsWith('mailto:') &&
            !href.startsWith('tel:')
        ) {
            e.preventDefault();
            window.location.href = href;
        }
    });
});