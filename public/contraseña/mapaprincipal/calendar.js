document.addEventListener('DOMContentLoaded', () => {
    const currentMonthYear = document.getElementById('current-month-year');
    const calendarDays = document.getElementById('calendar-days');
    const prevMonthButton = document.getElementById('prev-month');
    const nextMonthButton = document.getElementById('next-month');
    const addEventForm = document.getElementById('add-event-form');
    const eventDateInput = document.getElementById('event-date');
    const eventNameInput = document.getElementById('event-name');
    const eventDetailsInput = document.getElementById('event-details');
    const eventTypeInput = document.getElementById('event-type');
    const upcomingEventsList = document.getElementById('upcoming-events-list');
    const notificationsArea = document.getElementById('notifications-area');
    const selectedDateInfo = document.getElementById('selected-date-info');
    const dayEventsList = document.getElementById('day-events-list');

    let currentDate = new Date();
    let events = []; // Almacenará los eventos de la BD

    async function fetchEvents() {
        try {
            const response = await fetch('api/get_events.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            events = await response.json();
            renderCalendar();
            displayUpcomingEvents();
        } catch (error) {
            console.error("Error fetching events:", error);
            showNotification(`Error al cargar eventos: ${error.message}`, 'error');
        }
    }

    function renderCalendar() {
        calendarDays.innerHTML = '';
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth(); // 0-11

        const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        currentMonthYear.textContent = `${monthNames[month]} ${year}`;

        const firstDayOfMonth = new Date(year, month, 1).getDay(); // 0 (Dom) - 6 (Sáb)
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Rellenar días vacíos al inicio del mes
        for (let i = 0; i < firstDayOfMonth; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.classList.add('day-cell', 'empty');
            calendarDays.appendChild(emptyCell);
        }

        // Rellenar los días del mes
        for (let day = 1; day <= daysInMonth; day++) {
            const dayCell = document.createElement('div');
            dayCell.classList.add('day-cell');
            
            const dayNumber = document.createElement('span');
            dayNumber.classList.add('day-number');
            dayNumber.textContent = day;
            dayCell.appendChild(dayNumber);

            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            dayCell.dataset.date = dateStr;

            const today = new Date();
            if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                dayCell.classList.add('today');
            }

            // Marcar días con eventos
            const eventsOnThisDay = events.filter(event => event.event_date === dateStr);
            if (eventsOnThisDay.length > 0) {
                // dayCell.classList.add('has-event'); // Puedes usar esto para un estilo general
                eventsOnThisDay.forEach(event => {
                    const eventIndicator = document.createElement('span');
                    eventIndicator.classList.add('event-indicator');
                    // Clase específica por tipo de evento para diferentes colores
                    eventIndicator.classList.add(event.event_type.toLowerCase().replace(/\s+/g, '-')); // ej. "operador-telecomunicaciones"
                    eventIndicator.title = event.event_name;
                    dayCell.appendChild(eventIndicator);
                });
            }

            dayCell.addEventListener('click', () => handleDayClick(dateStr, dayCell));
            calendarDays.appendChild(dayCell);
        }
    }

    function handleDayClick(dateStr, cellElement) {
        // Resaltar día seleccionado
        document.querySelectorAll('.day-cell.selected').forEach(c => c.classList.remove('selected'));
        if (cellElement) cellElement.classList.add('selected');

        selectedDateInfo.textContent = `Eventos para el ${formatDateForDisplay(dateStr)}:`;
        dayEventsList.innerHTML = ''; // Limpiar lista anterior

        const eventsOnThisDay = events.filter(event => event.event_date === dateStr);
        if (eventsOnThisDay.length > 0) {
            eventsOnThisDay.forEach(event => {
                const eventItem = document.createElement('div');
                eventItem.classList.add('event-item');
                eventItem.innerHTML = `
                    <strong>${event.event_name}</strong>
                    <small>Tipo: ${event.event_type}</small>
                    ${event.event_details ? `<p>${event.event_details.replace(/\n/g, '<br>')}</p>` : ''}
                `;
                dayEventsList.appendChild(eventItem);
            });
        } else {
            dayEventsList.innerHTML = '<p>No hay eventos programados para este día.</p>';
        }
        // Auto-rellenar la fecha en el formulario al hacer clic en un día
        eventDateInput.value = dateStr;
    }
    
    function formatDateForDisplay(dateStr) {
        const [year, month, day] = dateStr.split('-');
        return `${day}/${month}/${year}`;
    }

    function displayUpcomingEvents() {
        upcomingEventsList.innerHTML = '';
        const today = new Date();
        today.setHours(0, 0, 0, 0); // Para comparar solo fechas

        const upcoming = events
            .filter(event => new Date(event.event_date) >= today)
            .sort((a, b) => new Date(a.event_date) - new Date(b.event_date))
            .slice(0, 5); // Mostrar los próximos 5

        if (upcoming.length > 0) {
            upcoming.forEach(event => {
                const li = document.createElement('li');
                li.textContent = `${formatDateForDisplay(event.event_date)} - ${event.event_name} (${event.event_type})`;
                if (event.event_details) {
                    li.title = event.event_details;
                }
                upcomingEventsList.appendChild(li);
            });
        } else {
            upcomingEventsList.innerHTML = '<li>No hay eventos próximos.</li>';
        }
    }

    addEventForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const eventData = {
            date: eventDateInput.value,
            name: eventNameInput.value.trim(),
            details: eventDetailsInput.value.trim(),
            type: eventTypeInput.value
        };

        if (!eventData.date || !eventData.name) {
            showNotification('La fecha y el nombre del evento son obligatorios.', 'error');
            return;
        }

        try {
            const response = await fetch('./api/add_event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(eventData),
            });
            const result = await response.json();

            if (result.success) {
                showNotification(result.message, 'success');
                addEventForm.reset();
                fetchEvents(); // Recargar eventos y renderizar calendario
            } else {
                showNotification(result.message || 'Error al agregar evento.', 'error');
            }
        } catch (error) {
            console.error("Error adding event:", error);
            showNotification('Error de conexión al agregar evento.', 'error');
        }
    });

    prevMonthButton.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    });

    nextMonthButton.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    });

    function showNotification(message, type = 'info') {
        notificationsArea.innerHTML = ''; // Limpiar notificaciones anteriores
        const notificationDiv = document.createElement('div');
        notificationDiv.className = `notification ${type}`;
        notificationDiv.textContent = message;
        notificationsArea.appendChild(notificationDiv);

        setTimeout(() => {
            notificationDiv.remove();
        }, 5000); // La notificación desaparece después de 5 segundos
    }

    // Carga inicial
    fetchEvents();
});