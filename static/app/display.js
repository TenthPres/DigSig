let featuredEventList = [],
    currentEventList = [],
    nextEventToSlideify = -1,
    previousSlide = null,
    currentSlide = null,
    nextSlide = null;


Date.prototype.addDays = function(days) {
    let date = new Date(this.valueOf());
    date.setDate(date.getDate() + days);
    return date;
}

/**
 * This is modeled after the PHP format strings.
 *
 * @param string
 * @return {string}
 */
Date.prototype.toString = function(string = "%Y-%m-%d %h:%i:%s") {
    let Y = this.getFullYear().toString(),
        m = ("0" + (this.getMonth()+1).toString()).slice(-2),
        j  = this.getDate().toString(),
        d  = ("0" + j).slice(-2),
        D  = this.toLocaleDateString("en-US", { weekday: 'short' }),
        a  = this.getHours() > 11 ? "pm" : "am",
        A  = this.getHours() > 11 ? "PM" : "AM",
        G  = this.getHours(),
        g  = G % 12 < 1 ? 12 : G % 12,
        H = ("0" + G).slice(-2),
        h = ("0" + g).slice(-2),
        i = ("0" + this.getMinutes().toString()).slice(-2),
        s = ("0" + this.getSeconds().toString()).slice(-2),
        l = this.toLocaleDateString("en-US", { weekday: 'long' }),
        M = this.toLocaleDateString("en-US", { month: 'long' });

    return string
        .replace("%Y", Y)
        .replace("%m", m)
        .replace("%j", j)
        .replace("%d", d)
        .replace("%D", D)
        .replace("%a", a)
        .replace("%A", A)
        .replace("%G", G)
        .replace("%g", g)
        .replace("%H", H)
        .replace("%h", h)
        .replace("%i", i)
        .replace("%s", s)
        .replace("%l", l)
        .replace("%M", M);
};


Date.prototype.toDateStringFormatted = function() {
    /* Today */
    let now = new Date();
    if (this.toString("%Y%M%d") === now.toString("%Y%M%d")) {
        if (parseInt(this.toString('G')) >= 17) { // 5pm or later
            return "Tonight";
        } else {
            return "Today";
        }
    }

    /* Tomorrow */
    if (now.addDays(1).toString('%Y%M%d') === this.toString('%Y%M%d')) {
        return "Tomorrow";
    }

    /* This week */
    if (this.getTime() - now.getTime() < 7 * 86400 * 1000) {
        return "This " + this.toString('%l');
    }

    /* Next week */
    if (this.getTime() - now.getTime() < 14 * 86400 * 1000) {
        return "Next " + this.toString('%D, %M %j');
    }

    /* Other Times */
    return this.toString('%l, %M %j');
};


Date.prototype.getWeek = function() {
    let date = new Date(this.getTime());
    date.setHours(0, 0, 0, 0);
    // Thursday in current week decides the year.
    date.setDate(date.getDate() + 3 - (date.getDay() + 6) % 7);
    // January 4 is always in week 1.
    let week1 = new Date(date.getFullYear(), 0, 4);
    // Adjust to Thursday in week 1 and count number of weeks from date to week1.
    return 1 + Math.round(((date.getTime() - week1.getTime()) / 86400000
        - 3 + (week1.getDay() + 6) % 7) / 7);
}


Date.prototype.toTimeStringFormatted = function() {
    /** @var String string */
    let string = this.toString('%g:%i%a');

    string = string
        .replace(":00", "")
        .replace("12pm", "Noon");

    return string;
};

// Reload window every 8 hours
setTimeout(() => window.location.reload(), 1000 * 60 * 60 * 8);


function updateEventLists() {

    let params = window.location.search;
        xhr = new XMLHttpRequest();
    xhr.addEventListener("load", featuredCallback);
    xhr.open("GET", "/digsig/data" + params, featuredCallback);
    xhr.send();

    function featuredCallback() {
        let evts = JSON.parse(this.responseText).events,
            newEventList = [];

        for (let ei in evts) {
            if (!evts.hasOwnProperty(ei))
                continue;

            if (!evts[ei].hasOwnProperty('title'))
                continue;

            let evtObj = {
                title: evts[ei].title,
                allDay: evts[ei].all_day,
                shortUrl: evts[ei].short_url ?? "",
                dtStart: new Date(evts[ei].start_date),
                dtEnd: new Date(evts[ei].end_date),
                ministry: '', // TODO evts[ei].ministry ? evts[ei].ministry : '',
                location: evts[ei].venue.venue ?? '',
                category: evts[ei].categories.name ?? null,
                imageUrl: evts[ei].slide ?? (evts[ei].image === false ? null : evts[ei].image.url),
                url: evts[ei].url ?? "",
                hasSlideImage: !!evts[ei].slide
            };
            newEventList.push(evtObj)
        }

        // remove duplicates
        let newEventList2 = [],
            indexedUrls = [];
        for (const ei in newEventList.sort((a, b) => a.dtStart.getTime() - b.dtStart.getTime())) {
            if (!newEventList.hasOwnProperty(ei))
                continue;
            let url = newEventList[ei].url.substring(29).split("/", 2);
            if (indexedUrls.indexOf(url[0]) === -1) {
                newEventList2.push(newEventList[ei]);
                indexedUrls.push(url[0])
            }
        }

        // apply list
        featuredEventList = newEventList2;

        if (currentSlide === null)
            changeSlide();
    }

    // function allCallback(error, response, body) {
    //     let evts = JSON.parse(body),
    //         newEventList = [],
    //         now = new Date(),
    //         later = (new Date()).addHours(24);
    //
    //     for (let ei in evts) {
    //         if (!evts.hasOwnProperty(ei))
    //             continue;
    //
    //         if (!evts[ei].hasOwnProperty('title'))
    //             continue;
    //
    //         let evtObj = {
    //             title: evts[ei].title,
    //             dtStart: new Date(Date.parse(evts[ei].start)),
    //             dtEnd: new Date(Date.parse(evts[ei].end)),
    //             ministry: evts[ei].ministry ? evts[ei].ministry : '',
    //             location: evts[ei].location,
    //             diff: null
    //         };
    //
    //         evtObj.diff = now.compareTo(evtObj.dtStart); // -1 if starts in the future.
    //
    //         if (now.compareTo(evtObj.dtEnd) > 0)
    //             continue;
    //
    //         if (later.compareTo(evtObj.dtStart) < 0)
    //             continue;
    //
    //         newEventList.push(evtObj)
    //     }
    //
    //     currentEventList = newEventList;
    // }
}
updateEventLists();


function changeSlide() {
    let now = new Date();

    if (previousSlide !== null) {
        document.getElementById('slideSpace').removeChild(previousSlide);
    }
    if (currentSlide !== null) {
        currentSlide.style.opacity = "0";
    }
    if (nextSlide !== null) {
        nextSlide.style.opacity = "1";
        if (typeof nextSlide.onShow === 'function')
            setTimeout(nextSlide.onShow, 100);
    }

    previousSlide = currentSlide;
    currentSlide = nextSlide;

    nextSlide = document.createElement('div');
    nextSlide.style.opacity = "0";
    document.getElementById('slideSpace').appendChild(nextSlide);

    if (nextEventToSlideify === -1) {
        nextSlide.innerHTML = "<div><h2>Welcome to Tenth</h2></div>";
        nextSlide.classList.add('welcome');
        nextSlide.style.backgroundPositionY = "100%";
        nextSlide.style.backgroundPositionX = "0";
        nextSlide.time = 8000;
        let _slide = nextSlide;
        nextSlide.onShow = function() {
            _slide.style.backgroundPositionY = "0";
            _slide.style.backgroundPositionX = "100%";
        }

    } else {
        let event;

        // skip any past events
        do {
            event = featuredEventList[nextEventToSlideify++];
            if (event !== undefined) {
                event.tense = getTense(event);
            }
        } while ((event?.tense ?? 0) < 0);
        nextEventToSlideify--;

        // events happening now
        if (event.tense === 0) {
            nextSlide.time = 3200;
            nextSlide.classList.add('happeningNow');
            let count = 0;
            let html = "<div><h2>Happening Now</h2><table>";

            while (event.tense === 0 && count++ < 3) {
                html += "<tr><td>" + event.title + "</td><td>" + event.location + "</td></tr>";
                nextSlide.time += 800;

                event = featuredEventList[++nextEventToSlideify];
                if (event !== undefined) {
                    event.tense = getTense(event);
                }
            }
            nextEventToSlideify--;

            html += "</table></div>";
            nextSlide.innerHTML = html;

        // This Sunday
        } else if (event.dtStart.toString('D') === 'Sun' && now.getWeek() === event.dtStart.getWeek()) {
            nextSlide.time = 1500;
            nextSlide.classList.add('thisSunday');
            let count = 0;
            let html = "";

            if (now.toString('D') === "Sun")
                html += "<div><h2>Events Today</h2><table>";
            else
                html += "<div><h2>Events This Sunday</h2><table>";

            /** @var Date event.dtStart */
            while (event.dtStart?.toString('D') === 'Sun' && now.getWeek() === event.dtStart?.getWeek() && count++ < 7) {
                html += "<tr><td class='right'>" + event.dtStart.toTimeStringFormatted() + "</td><td>" + event.title + "</td><td>" + event.location + "</td></tr>";

                nextSlide.time += 750;

                event = featuredEventList[++nextEventToSlideify];
            }
            nextEventToSlideify--;

            html += "</table></div>";
            nextSlide.innerHTML = html;

        // Everything else
        } else {
            if (event.hasOwnProperty('hasSlideImage') && event.hasSlideImage !== true) {
                let shortUrl = "";
                if (event.shortUrl && event.shortUrl !== "") {
                    shortUrl = "<p>" + event.shortUrl + "</p>";
                }

                let div = document.createElement('div');

                if (event.allDay) {
                    div.innerHTML = "<div><h2>" + event.title + "</h2><p>" + event.dtStart.toDateStringFormatted() + "</p><p>" + event.location + "</p>" + shortUrl + "</div>";
                } else {
                    div.innerHTML = "<div><h2>" + event.title + "</h2><p>" + event.dtStart.toDateStringFormatted() + " &sdot; " + event.dtStart.toTimeStringFormatted() + "</p><p>" + event.location + "</p>" + shortUrl+ "</div>";
                }

                nextSlide.appendChild(div);

                // reduce font size to fit on screen when needed.
                let height = div.clientHeight;
                let fontSize = 1;
                while (height > (1/3) * window.innerHeight) {
                    fontSize -= 0.05;
                    if (fontSize < 0.5) {
                        break;
                    }
                    div.style.fontSize = fontSize + "em";
                    height = div.clientHeight;
                }


            } else {
                nextSlide.innerHTML = "";
            }
            nextSlide.classList.add('single');
            nextSlide.time = 6000;

            if (event.hasOwnProperty('imageUrl') && event.imageUrl !== null)
                nextSlide.style.backgroundImage = "url('" + event.imageUrl + "')";
        }
    }

    nextEventToSlideify++;
    if (nextEventToSlideify >= featuredEventList.length) {
        updateEventLists();
        nextEventToSlideify = -1;
    }

    if (currentSlide === null)
        changeSlide();
    else
        setTimeout(changeSlide, currentSlide.time);
}

function getTense(eventObj) {
    const now = new Date();
    return (eventObj?.dtStart ?? 0 < now) + (eventObj?.dtEnd ?? 0 < now);
}
