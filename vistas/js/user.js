$(document).ready(function(){

    /*=============================================
    TOAST REUTILIZABLE (estilo notificación)
    =============================================*/
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',          // esquina superior derecha
        showConfirmButton: false,
        timer: 2500,                  // se cierra en 2.5s
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });


    /*=============================================
    CREAR USUARIO
    =============================================*/
    $("#formCrearUser").on("submit", function(e){
        e.preventDefault();

        var formData = new FormData(this);

        // Loader breve
        Swal.fire({
            title: 'Guardando usuario...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: "ajax/user.ajax.php",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            dataType: "json",
            success: function(respuesta){

                if(respuesta.status === "success"){
                    Swal.close(); // cerramos el loader

                    Toast.fire({
                        icon: 'success',
                        title: respuesta.mensaje || 'Usuario guardado correctamente'
                    });

                    // Recargamos después del toast para refrescar la tabla
                    setTimeout(() => location.reload(), 1800);

                } else {
                    Toast.fire({
                        icon: 'error',
                        title: respuesta.mensaje || 'Error al guardar'
                    });
                }
            },
            error: function(xhr){
                console.error(xhr.responseText);
                Toast.fire({
                    icon: 'error',
                    title: 'Error de conexión con el servidor'
                });
            }
        });
    });


    /*=============================================
    EDITAR USUARIO
    =============================================*/
    $("#formEditarUser").on("submit", function(e){
        e.preventDefault();

        var formData = new FormData(this);

        Swal.fire({
            title: 'Actualizando usuario...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: "ajax/user.ajax.php",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            dataType: "json",
            success: function(respuesta){

                if(respuesta.status === "success"){
                    Swal.close();

                    Toast.fire({
                        icon: 'success',
                        title: respuesta.mensaje || 'Usuario actualizado correctamente'
                    });

                    setTimeout(() => location.reload(), 1800);

                } else {
                    Toast.fire({
                        icon: 'error',
                        title: respuesta.mensaje || 'Error al actualizar'
                    });
                }
            },
            error: function(xhr){
                console.error(xhr.responseText);
                Toast.fire({
                    icon: 'error',
                    title: 'Error de conexión con el servidor'
                });
            }
        });
    });

});