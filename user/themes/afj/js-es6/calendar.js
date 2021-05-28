import { Calendar } from '@fullcalendar/core';
import listPlugin from '@fullcalendar/list';
import iCalendarPlugin from '@fullcalendar/icalendar';

document.addEventListener('DOMContentLoaded', function() {
    let make_calendar = (id, url) => {
        let calendarEl = document.getElementById(id);
        return new Calendar(calendarEl, {
            plugins: [ listPlugin, iCalendarPlugin ],
            initialView: 'listYear',
            displayEventTime: false,
            locale: 'fr',
            events: {
                url: url,
                format: 'ics'
            },
            headerToolbar: {
              left: '',//'prev,next today',
              center: '', //'title',
              right: '',
            }
        });
    };

    window.render_cal = (id, url) => {
        let calendar = make_calendar(id, url);
        calendar.render();
        return calendar;
    };
});
