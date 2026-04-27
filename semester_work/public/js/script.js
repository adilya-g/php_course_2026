
const demoNotifications = [
    { title: "Напоминание", text: "У вас встреча через 30 минут", time: "10:30" },
    { title: "Новое письмо", text: "Получено новое письмо от Ивана", time: "09:45" },
    { title: "Задача выполнена", text: "Проект 'Веб-сайт' отмечен как завершенный", time: "09:00" },
    { title: "Календарь", text: "Добавлено новое событие на завтра", time: "08:30" }
];

// Инициализация календаря
let currentDate = new Date();

function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    const firstDayOfMonth = new Date(year, month, 1);
    const startDayOfWeek = firstDayOfMonth.getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const monthNames = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    document.getElementById('monthTitle').textContent = `${monthNames[month]} ${year}`;

    const calendarDays = document.getElementById('calendarDays');
    calendarDays.innerHTML = '';

    const today = new Date();
    const todayDate = today.getDate();
    const todayMonth = today.getMonth();
    const todayYear = today.getFullYear();

    const startOffset = startDayOfWeek === 0 ? 6 : startDayOfWeek - 1;

    for (let i = 0; i < startOffset; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'day other-month';
        emptyDay.textContent = '';
        calendarDays.appendChild(emptyDay);
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const dayElement = document.createElement('div');
        dayElement.className = 'day';
        dayElement.textContent = day;

        if (day === todayDate && month === todayMonth && year === todayYear) {
            dayElement.classList.add('today');
        }

        dayElement.addEventListener('click', () => {
            console.log(`Выбран день: ${day}.${month + 1}.${year}`);
        });

        calendarDays.appendChild(dayElement);
    }
}

async function renderTimeline() {
    const timelineHours = document.getElementById('timelineHours');
    // Показываем состояние загрузки
    timelineHours.innerHTML = '<div class="timeline-loading">⏳ Загрузка расписания...</div>';

    try {
        const response = await fetch('/home/events');
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const events = await response.json();
        timelineHours.innerHTML = ''; // Очищаем лоадер после успешной загрузки

        const now = new Date();
        const currentHour = now.getHours();

        // Группируем события по часам для быстрого поиска
        const eventsByHour = {};
        if (Array.isArray(events)) {
            events.forEach(evt => {
                if (evt.date !== currentDate) {
                    return;
                }
                // Пытаемся извлечь час из разных возможных полей ответа API
                let hour = evt.hour;
                if (hour === undefined && evt.start) hour = new Date(evt.start).getHours();
                if (hour === undefined && evt.date) hour = new Date(evt.date).getHours();
                if (hour === undefined && evt.time) hour = parseInt(evt.time.split(':')[0], 10);

                if (hour >= 8 && hour <= 20) {
                    if (!eventsByHour[hour]) eventsByHour[hour] = [];
                    eventsByHour[hour].push(evt);
                }
            });
        }

        for (let hour = 8; hour <= 20; hour++) {
            const slot = document.createElement('div');
            slot.className = 'timeline-slot';
            const timeStr = `${hour.toString().padStart(2, '0')}:00`;

            let eventsHtml = '';

            // Индикатор текущего времени
            if (hour === currentHour) {
                eventsHtml += '<div class="timeline-now">🔵 Сейчас</div>';
            }

            // Рендер событий для этого часа
            if (eventsByHour[hour]) {
                eventsByHour[hour].forEach(evt => {
                    const title = evt.title || evt.summary || evt.name || 'Событие';
                    // Если в событии есть точное время, показываем его
                    const timeStrEvt = evt.time || (evt.start ? new Date(evt.start).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }) : '');
                    eventsHtml += `<div class="timeline-event-item">📅 ${timeStrEvt ? timeStrEvt + ' ' : ''}${title}</div>`;
                });
            }

            slot.innerHTML = `
                <div class="timeline-time">${timeStr}</div>
                <div class="timeline-events">
                    ${eventsHtml || '<span class="timeline-empty">—</span>'}
                </div>
            `;

            timelineHours.appendChild(slot);
        }
    } catch (error) {
        console.error('Ошибка рендера таймлайна:', error);
        timelineHours.innerHTML = `<div class="timeline-error">❌ Не удалось загрузить расписание: ${error.message}</div>`;
    }
}
async function renderEmails() {
    const gmailList = document.getElementById('gmailList');
    gmailList.innerHTML = '<div class="loading">Загрузка...</div>';

    try {
        // 1. Ждем завершения fetchEmails. Если он возвращает Promise, без await тут будет ошибка.
        let demoEmails = await this.fetchEmails();

        // 2. Если данные пришли в виде строки (JSON), преобразуем в объект
        if (typeof demoEmails === 'string') {
            demoEmails = JSON.parse(demoEmails);
        }

        // 3. Проверяем, что получен массив.
        // Иногда Google API возвращает объект вида { messages: [...] } или { result: [...] }
        if (!Array.isArray(demoEmails)) {
            if (demoEmails.messages && Array.isArray(demoEmails.messages)) {
                demoEmails = demoEmails.messages;
            } else if (demoEmails.result && Array.isArray(demoEmails.result)) {
                demoEmails = demoEmails.result;
            } else {
                console.error("Неверный формат данных от сервера:", demoEmails);
                gmailList.innerHTML = '<div class="empty-state">Ошибка формата данных</div>';
                return;
            }
        }

        gmailList.innerHTML = ''; // Очищаем индикатор загрузки

        if (demoEmails.length === 0) {
            gmailList.innerHTML = '<div class="empty-state">Нет писем</div>';
            return;
        }

        demoEmails.forEach(email => {
            const emailElement = document.createElement('div');
            emailElement.className = 'email-item';

            // 4. Правильное извлечение данных согласно вашему JSON файлу
            // Subject и From находятся внутри массива headers объекта payload
            const headers = email.payload?.headers || [];

            // Ищем нужный заголовок в массиве
            const subjectHeader = headers.find(h => h.name === 'Subject');
            const fromHeader = headers.find(h => h.name === 'From');

            const subject = subjectHeader ? subjectHeader.value : 'Без темы';
            const from = fromHeader ? fromHeader.value : 'Неизвестно';
            const snippet = email.snippet || 'Нет текста';

            emailElement.innerHTML = `
                <div class="email-subject">${escapeHtml(subject)}</div>
                <div class="email-from">${escapeHtml(from)}</div>
                <div class="email-snippet">${escapeHtml(snippet)}</div>
            `;

            emailElement.addEventListener('click', () => {
                console.log('Открыто письмо:', subject);
                // Здесь можно вызвать метод открытия полного письма
            });

            gmailList.appendChild(emailElement);
        });

    } catch (error) {
        console.error('Ошибка при рендере писем:', error);
        gmailList.innerHTML = '<div class="empty-state">Ошибка загрузки</div>';
    }
}

async function fetchEmails() {
    try {
        const response = await fetch('/home/mails');
        const demoEmails = await response.json();
        return demoEmails;
    } catch (error) {
        console.error('Error fetching emails:', error);
        return [];
    }
}

function renderNotifications() {
    const notificationsList = document.getElementById('notificationsList');
    notificationsList.innerHTML = '';

    if (demoNotifications.length === 0) {
        notificationsList.innerHTML = '<div class="empty-state">Нет уведомлений</div>';
        return;
    }

    demoNotifications.forEach(notification => {
        const notificationElement = document.createElement('div');
        notificationElement.className = 'notification-item';
        notificationElement.innerHTML = `
            <div class="notification-title">${escapeHtml(notification.title)}</div>
            <div class="notification-text">${escapeHtml(notification.text)}</div>
            <div class="notification-time">${escapeHtml(notification.time)}</div>
        `;
        notificationElement.addEventListener('click', () => {
            console.log('Открыто уведомление:', notification.title);
        });
        notificationsList.appendChild(notificationElement);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Обработчики для кнопок
document.getElementById('prevMonthBtn').addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar();
});

document.getElementById('nextMonthBtn').addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar();
});

document.getElementById('settingsBtn').addEventListener('click', () => {
    console.log('Открыть настройки');
    // Здесь будет логика открытия настроек
});

document.getElementById('profileBtn').addEventListener('click', () => {
    console.log('Открыть профиль');
    // Здесь будет логика открытия профиля
});

// Инициализация
renderCalendar();
renderTimeline();
renderEmails();
renderNotifications();
