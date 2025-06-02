<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario Interactivo con BD</title>
    <link rel="stylesheet" href="calendar.css">
    <link rel="shortcut icon" href="./ico.ico" />
</head>
<body>
  <center>
    <div class="container10">
        <!-- Botón Volver (si es necesario) -->
        <!-- <a href="pagina_anterior.php" style="text-decoration:none"> ... </a> -->

        <header>
            <h1>Calendario Interactivo</h1>
            <div class="month-navigation">
                <button id="prev-month">< Anterior</button>
                <h2 id="current-month-year"></h2>
                <button id="next-month">Siguiente ></button>
            </div>
        </header>

        <div class="calendar-grid">
            <div class="weekday">Dom</div>
            <div class="weekday">Lun</div>
            <div class="weekday">Mar</div>
            <div class="weekday">Mié</div>
            <div class="weekday">Jue</div>
            <div class="weekday">Vie</div>
            <div class="weekday">Sáb</div>
            <div id="calendar-days" class="days-grid">
                <!-- Los días se generarán aquí -->
            </div>
        </div>

        <div class="info-panel">
            <h3>Información del Día Seleccionado</h3>
            <p id="selected-date-info">Haz clic en un día para ver sus eventos.</p>
            <div id="day-events-list">
                <!-- Eventos del día seleccionado -->
            </div>

            <h3>Próximos Eventos Importantes</h3>
            <ul id="upcoming-events-list">
                <!-- Lista de próximos eventos -->
            </ul>
        </div>

        <div class="event-form-container">
            <h3>Agregar Nuevo Evento</h3>
            <form id="add-event-form">
                <label for="event-date">Fecha:</label>
                <input type="date" id="event-date" required>
                
                <label for="event-name">Nombre del Evento:</label>
                <input type="text" id="event-name" placeholder="Ej: Cumpleaños de Ana" required>
                
                <label for="event-details">Detalles (Opcional):</label>
                <textarea id="event-details" placeholder="Ej: Salida del operador X, detalles adicionales..."></textarea>

                <label for="event-type">Tipo de Evento:</label>
                <select id="event-type">
                    <option value="General">General</option>
                    <option value="Operador Telecomunicaciones">Operador Telecomunicaciones</option>
                    <option value="Cumpleaños">Cumpleaños</option>
                    <option value="Festivo">Festivo</option>
                    <option value="Importante">Importante</option>
                    <option value="Otro">Otro</option>
                </select>
                
                <button type="submit">Agregar Evento</button>
            </form>
        </div>

        <div id="notifications-area">
            <!-- Las notificaciones aparecerán aquí -->
        </div>
    
    </div>
  </center>
  <script src="calendar.js"></script>
</body>
</html>