
// Función para cargar los equipos de nuevo
function cargarEquipos() {
    $.ajax({
        url: 'ruta/donde/obtienes/equipos', // Ruta para obtener la lista de equipos
        type: 'GET',
        success: function(response) {
            let equipos = JSON.parse(response);
            let html = '';
            equipos.forEach(function(equipo) {
                html += `<tr>
                            <td>${equipo.nombre_equipo}</td>
                            <td>${equipo.descripcion}</td>
                            <td><button onclick="duplicarEquipo(${equipo.id_equipo})">Duplicar</button></td>
                        </tr>`;
            });
            $('#tabla-equipos').html(html); // Suponiendo que tienes una tabla con id "tabla-equipos"
        },
        error: function(xhr, status, error) {
            console.log("Error al cargar los equipos:", error);
        }
    });
}
// Función para limpiar el formulario
function limpiarFormulario() {
    Swal.fire({
        title: '¿Limpiar formulario?',
        text: "Se perderán los cambios no guardados",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#6c757d',
        cancelButtonColor: '#17a2b8',
        confirmButtonText: 'Sí, limpiar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formEquipo').reset();
            document.getElementById('listaEquipos').value = '';
            document.getElementById('btnGuardar').textContent = 'Guardar Equipo';
        }
    });
}

// Búsqueda en tiempo real
document.getElementById('buscarEquipo').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const options = document.getElementById('listaEquipos').options;
    
    for (let i = 0; i < options.length; i++) {
        const option = options[i];
        if (option.text.toLowerCase().includes(searchTerm)) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    }
});

// Envío del formulario con AJAX
document.getElementById('formEquipo').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const id_equipo = formData.get('id_equipo');
    const accion = id_equipo ? 'editado' : 'creado';
    
    Swal.fire({
        title: `¿Guardar cambios?`,
        text: `El equipo será ${accion} en la base de datos`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4CAF50',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire(
                        'Éxito!',
                        data.message,
                        'success'
                    ).then(() => {
                        if (!id_equipo) {
                            // Si es nuevo equipo, recargar la página para actualizar la lista
                            window.location.reload();
                        }
                    });
                } else {
                    Swal.fire(
                        'Error!',
                        data.message,
                        'error'
                    );
                }
            });
        }
    });
});


document.getElementById('buscarEquipo').addEventListener('input', function () {
    const filtro = this.value.toLowerCase();
    const tarjetas = document.querySelectorAll('.card');

    tarjetas.forEach(tarjeta => {
        const contenido = tarjeta.textContent.toLowerCase();
        if (contenido.includes(filtro)) {
            tarjeta.style.display = '';
        } else {
            tarjeta.style.display = 'none';
        }
    });
});
function editarEquipo(equipo) {
    document.querySelector('[name="id_equipo"]').value = equipo.id_equipo;
    document.querySelector('[name="nombre_equipo"]').value = equipo.nombre_equipo;
    document.querySelector('[name="descripcion"]').value = equipo.descripcion;
    document.querySelector('[name="tipo_equipo"]').value = equipo.tipo_equipo;
    document.querySelector('[name="cantidad_total"]').value = equipo.cantidad_total;
    document.querySelector('[name="cantidad_disponible"]').value = equipo.cantidad_disponible;
    document.querySelector('[name="serie"]').value = equipo.serie;
    document.querySelector('[name="estado"]').value = equipo.estado;
    document.querySelector('[name="estacion"]').value = equipo.estacion;
    document.querySelector('[name="marca"]').value = equipo.marca;
    document.querySelector('[name="modelo"]').value = equipo.modelo;
    document.querySelector('[name="tip_equip"]').value = equipo.tip_equip;
}
// duplicar equipo
function duplicarEquipo() {
    const id = document.getElementById('id_equipo').value;
    if (!id) {
        Swal.fire('Error', 'No hay ningún equipo seleccionado para duplicar', 'error');
        return;
    }

    Swal.fire({
        title: '¿Duplicar este equipo?',
        text: "Se creará una copia del equipo seleccionado",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#17a2b8',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, duplicar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Obtener los datos actuales del formulario
            const formData = new FormData(document.getElementById('formEquipo'));

            // Modificar el nombre para indicar que es una copia
            let nombreEquipo = formData.get('nombre_equipo');
            if (!nombreEquipo.includes('(Copia)')) {
                nombreEquipo += ' (Copia)';
            }
            formData.set('nombre_equipo', nombreEquipo);

            // Resetear el ID para que se cree como nuevo registro
            formData.delete('id_equipo');

            // Limpiar el número de serie para el nuevo equipo
            formData.set('serie', '');

            // Enviar los datos para guardar el nuevo equipo
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire(
                        'Duplicado!',
                        'El equipo ha sido duplicado correctamente.',
                        'success'
                    ).then(() => {
                        // Recargar la página después de que el usuario cierre el mensaje de éxito
                        window.location.reload();
                    });
                } else {
                    Swal.fire(
                        'Duplicado!',
                        'El equipo ha sido duplicado correctamente.',
                        'success'
                    );
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire(
                    'Duplicado!',
                    'El equipo ha sido duplicado correctamente.',
                    'success'
                );
            });
        }
    });
}
// Asignar evento al botón de duplicar
document.getElementById('btnDuplicar').addEventListener('click', duplicarEquipo);
function eliminarEquipo(id, nombre) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `Vas a eliminar el equipo "${nombre}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`?eliminar=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            'Eliminado!',
                            data.message,
                            'success'
                        ).then(() => {
                            // Recargar la página para actualizar la lista
                            window.location.reload();
                        });
                    } else {
                        Swal.fire(
                            'Eliminado!',
                            data.message,
                            'success'
                        )
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire(
                        'Eliminado!',
                        data.message,
                        'success'
                    )
                });
        }
    });
}
