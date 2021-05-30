import { Calendar } from '@fullcalendar/core';
import listPlugin from '@fullcalendar/list';
import iCalendarPlugin from '@fullcalendar/icalendar';
import * as ICAL from 'ical.js';

function parse_ical(content) {
    var jcalData = ICAL.parse(content.trim());
    var comp = new ICAL.Component(jcalData);
    var eventComps = comp.getAllSubcomponents("vevent");

    console.log(JSON.stringify(eventComps));
}

window.parse_ical = parse_ical;

document.addEventListener('DOMContentLoaded', function() {
    let make_calendar = (id, url) => {
        let calendarEl = document.getElementById(id);
        return new Calendar(calendarEl, {
            plugins: [ listPlugin, iCalendarPlugin ],
            initialView: 'listYear',
            displayEventTime: false,
            locale: 'fr',
            height: '100%',
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
