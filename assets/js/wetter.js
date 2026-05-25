/**
 * Fahrtenbuch Beta — wetter.js
 * Lädt Wind, Pegel, Wassertemperatur und füllt Header + Wetter-Modal.
 */
(function () {
    'use strict';

    let _lastData = null;

    // ── Hilfsfunktionen ────────────────────────────────────────────

    function windDir(deg) {
        if (deg === null || deg === undefined) return '';
        const dirs = ['N','NO','O','SO','S','SW','W','NW'];
        return dirs[Math.round(deg / 45) % 8];
    }

    function windArrow(deg) {
        if (deg === null || deg === undefined) return '';
        // Pfeil zeigt in Windrichtung (woher der Wind kommt → umdrehen für "geht nach")
        const arrows = ['↓','↙','←','↖','↑','↗','→','↘'];
        return arrows[Math.round(deg / 45) % 8];
    }

    function windBft(kmh) {
        if (kmh === null) return '';
        const t = [1,6,12,20,29,39,50,62,75,89,103,117];
        for (let i = 0; i < t.length; i++) if (kmh < t[i]) return i + ' Bft';
        return '12 Bft';
    }

    function windLabel(kmh) {
        if (kmh === null) return '—';
        if (kmh < 1)  return 'Windstill';
        if (kmh < 12) return 'Leicht';
        if (kmh < 29) return 'Mäßig';
        if (kmh < 50) return 'Frisch';
        if (kmh < 75) return 'Stark';
        return 'Sturm';
    }

    // WMO Weather Interpretation Codes → Bootstrap Icon + Label
    function wmoIcon(code) {
        if (code === null || code === undefined) return 'bi-cloud';
        if (code === 0)        return 'bi-sun';
        if (code <= 2)         return 'bi-cloud-sun';
        if (code === 3)        return 'bi-clouds';
        if (code <= 48)        return 'bi-cloud-fog';
        if (code <= 55)        return 'bi-cloud-drizzle';
        if (code <= 65)        return 'bi-cloud-rain';
        if (code <= 77)        return 'bi-snow';
        if (code <= 82)        return 'bi-cloud-rain-heavy';
        if (code <= 86)        return 'bi-cloud-snow';
        if (code >= 95)        return 'bi-lightning';
        return 'bi-cloud';
    }

    function wmoLabel(code) {
        if (code === null || code === undefined) return '';
        if (code === 0)  return 'Sonnig';
        if (code <= 2)   return 'Heiter';
        if (code === 3)  return 'Bewölkt';
        if (code <= 48)  return 'Nebel';
        if (code <= 55)  return 'Nieselregen';
        if (code <= 65)  return 'Regen';
        if (code <= 77)  return 'Schnee';
        if (code <= 82)  return 'Schauer';
        if (code <= 86)  return 'Schneeschauer';
        if (code >= 95)  return 'Gewitter';
        return '';
    }

    function fmtTime(isoStr) {
        if (!isoStr) return '';
        const d = new Date(isoStr);
        return d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    }

    function fmtDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
    }

    function escHtml(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Header befüllen ────────────────────────────────────────────

    function setIconColor(id, cls) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('kcs-wi--green', 'kcs-wi--blue', 'kcs-wi--orange', 'kcs-wi--red');
        if (cls) el.classList.add(cls);
    }

    function updateHeader(data) {
        const wind      = data?.wind;
        const pegel     = data?.pegel;
        const watertemp = data?.watertemp;
        const cfg       = KCS.weatherConfig || {};

        const iconEl  = document.getElementById('weatherIcon');
        const tempEl  = document.getElementById('weatherTemp');
        const condEl  = document.getElementById('weatherCond');
        const windEl  = document.getElementById('weatherWind');
        const waterEl = document.getElementById('weatherWater');
        const pegelEl = document.getElementById('weatherPegel');

        if (wind?.current) {
            const spd   = wind.current.windspeed;
            const dir   = wind.current.winddirection;
            const tmp   = wind.current.temp;
            const wcode = wind.current.windcode ?? null;

            if (iconEl) {
                iconEl.className = 'bi ' + wmoIcon(wcode) + ' kcs-weather__icon';
            }
            if (tempEl && tmp !== null && tmp !== undefined)
                tempEl.textContent = Math.round(tmp) + '°C';
            if (condEl) {
                const lbl = wmoLabel(wcode);
                condEl.textContent = lbl || windLabel(spd);
            }
            if (windEl && spd !== null)
                windEl.textContent = (dir !== null ? windArrow(dir) + ' ' : '') + Math.round(spd) + ' km/h';

            if (spd !== null) {
                const warn   = cfg.windWarn   ?? 25;
                const danger = cfg.windDanger ?? 35;
                setIconColor('weatherWindIcon',
                    spd < warn   ? 'kcs-wi--green' :
                    spd < danger ? 'kcs-wi--orange' : 'kcs-wi--red');
            }
        }

        if (watertemp?.current !== null && watertemp?.current !== undefined && waterEl) {
            const val = watertemp.current;
            waterEl.textContent = String(val).replace('.', ',') + ' °C';
            setIconColor('weatherWaterIcon',
                val < (cfg.watertempWarm ?? 17) ? 'kcs-wi--blue' : 'kcs-wi--green');
        }

        if (pegel?.current_cm !== null && pegel?.current_cm !== undefined && pegelEl) {
            const val = pegel.current_cm;
            pegelEl.textContent = val + ' cm';
            setIconColor('weatherPegelIcon',
                val < (cfg.pegelLow ?? 290) ? 'kcs-wi--orange' : 'kcs-wi--green');
        }
    }

    // ── Modal befüllen ─────────────────────────────────────────────

    function renderModal(data) {
        const body = document.getElementById('wetterBody');
        if (!body) return;

        const wind      = data?.wind;
        const pegel     = data?.pegel;
        const watertemp = data?.watertemp;

        let html = '';

        // ── Wind + Vorschau ──────────────────────────────────────
        if (wind) {
            const cur = wind.current;
            const spd = cur?.windspeed;
            const dir = cur?.winddirection;
            const gst = cur?.windgusts;
            const tmp = cur?.temp;

            html += `
            <div class="kcs-weather-section">
                <div class="kcs-weather-section__title"><i class="bi bi-wind"></i> Wind aktuell</div>
                <div class="kcs-weather-current-row">
                    <div class="kcs-weather-current-val">${spd !== null ? Math.round(spd) : '—'} <span class="kcs-weather-unit">km/h</span></div>
                    <div class="kcs-weather-current-meta">
                        ${dir !== null ? '<span>' + windArrow(dir) + ' ' + windDir(dir) + '</span>' : ''}
                        ${gst !== null && gst !== undefined ? '<span>Böen ' + Math.round(gst) + ' km/h</span>' : ''}
                        ${tmp !== null && tmp !== undefined ? '<span>' + Math.round(tmp) + '°C Luft</span>' : ''}
                        <span>${windBft(spd)}</span>
                    </div>
                </div>`;

            // Vorschau-Tabelle
            const fc = wind.forecast || [];
            if (fc.length) {
                html += `<div class="kcs-weather-forecast">`;
                const show = fc.slice(0, 12);
                show.forEach(row => {
                    const d = row.dir !== null ? windArrow(row.dir) : '';
                    const s = row.speed !== null ? Math.round(row.speed) : '—';
                    const g = row.gust  !== null ? Math.round(row.gust)  : null;
                    const ic = wmoIcon(row.wcode ?? null);
                    html += `
                    <div class="kcs-weather-forecast-row">
                        <span class="kcs-wf-time">${fmtTime(row.time)}</span>
                        <span class="kcs-wf-wicon"><i class="bi ${ic}"></i></span>
                        <span class="kcs-wf-arrow">${escHtml(d)}</span>
                        <span class="kcs-wf-speed">${s} km/h</span>
                        ${g !== null ? `<span class="kcs-wf-gust">↑${g}</span>` : '<span></span>'}
                        <span class="kcs-wf-bft">${windBft(row.speed)}</span>
                    </div>`;
                });
                html += `</div>`;
            }
            html += `</div>`;
        }

        // ── Wassertemperatur ─────────────────────────────────────
        if (watertemp) {
            const hist7 = (watertemp.history || []).slice(0, 7);
            html += `
            <div class="kcs-weather-section">
                <div class="kcs-weather-section__title"><i class="bi bi-thermometer-half"></i> Wassertemperatur</div>
                <div class="kcs-weather-current-row">
                    <div class="kcs-weather-current-val">${watertemp.current !== null ? String(watertemp.current).replace('.', ',') : '—'} <span class="kcs-weather-unit">°C</span></div>
                </div>`;
            if (hist7.length > 1) {
                html += `<div class="kcs-weather-history">`;
                hist7.forEach((r, i) => {
                    if (i === 0) return; // aktuell schon oben
                    html += `<div class="kcs-weather-history-row">
                        <span>${fmtDate(r.date)}</span>
                        <span>${String(r.value).replace('.', ',')} °C</span>
                    </div>`;
                });
                html += `</div>`;
            }
            html += `</div>`;
        }

        // ── Pegel ────────────────────────────────────────────────
        if (pegel) {
            const hist7 = (pegel.history || []).slice(0, 7);
            html += `
            <div class="kcs-weather-section">
                <div class="kcs-weather-section__title"><i class="bi bi-water"></i> Pegel</div>
                <div class="kcs-weather-current-row">
                    <div class="kcs-weather-current-val">${pegel.current_cm !== null ? pegel.current_cm : '—'} <span class="kcs-weather-unit">cm ü.A.</span></div>
                </div>`;
            if (hist7.length > 1) {
                html += `<div class="kcs-weather-history">`;
                hist7.forEach((r, i) => {
                    if (i === 0) return;
                    html += `<div class="kcs-weather-history-row">
                        <span>${fmtDate(r.date)}</span>
                        <span>${r.value} cm</span>
                    </div>`;
                });
                html += `</div>`;
            }
            html += `</div>`;
        }

        if (!html) {
            html = '<div class="kcs-sp-empty">Keine Wetterdaten verfügbar</div>';
        }

        body.innerHTML = html;
    }

    // ── Open-Meteo client-seitig fetchen ──────────────────────────

    async function fetchWindLive(lat, lon) {
        const url = 'https://api.open-meteo.com/v1/forecast'
            + '?latitude='  + encodeURIComponent(lat)
            + '&longitude=' + encodeURIComponent(lon)
            + '&current_weather=true'
            + '&hourly=windspeed_10m,winddirection_10m,windgusts_10m,temperature_2m,weathercode'
            + '&forecast_days=2&timezone=Europe%2FBerlin';

        const res  = await fetch(url);
        if (!res.ok) return null;
        const om   = await res.json();
        if (!om.current_weather || !om.hourly) return null;

        const cw     = om.current_weather;
        const times  = om.hourly.time              || [];
        const speeds = om.hourly.windspeed_10m     || [];
        const dirs   = om.hourly.winddirection_10m || [];
        const gusts  = om.hourly.windgusts_10m     || [];
        const temps  = om.hourly.temperature_2m    || [];
        const wcodes = om.hourly.weathercode       || [];

        const nowMs   = Date.now();
        const forecast = [];
        for (let i = 0; i < times.length && forecast.length < 24; i++) {
            if (new Date(times[i]).getTime() < nowMs - 1800000) continue;
            forecast.push({ time: times[i], speed: speeds[i] ?? null,
                            dir: dirs[i] ?? null, gust: gusts[i] ?? null,
                            temp: temps[i] ?? null, wcode: wcodes[i] ?? null });
        }

        return {
            temp: cw.temperature ?? null, windspeed: cw.windspeed ?? null,
            winddirection: cw.winddirection ?? null, windcode: cw.weathercode ?? null,
            forecast,
        };
    }

    async function maybeRefreshWind(currentData) {
        // Nur fetchen wenn letzter Wind-Eintrag älter als 4h (= max. 6×/Tag)
        const lastUpdated = currentData?.wind?.updated;
        if (lastUpdated) {
            const ageH = (Date.now() - new Date(lastUpdated).getTime()) / 3600000;
            if (ageH < 4) return currentData;
        }

        const cfg = KCS.weatherConfig || {};
        const lat = cfg.lat || '47.67';
        const lon = cfg.lon || '9.17';

        try {
            const wind = await fetchWindLive(lat, lon);
            if (!wind) return currentData;

            // In DB speichern
            const form = new FormData();
            form.append('type', 'wind');
            form.append('data', JSON.stringify(wind));
            const saveRes = await fetch(KCS.apiBase + '/weather/save.php',
                                        { method: 'POST', body: form });
            const saved = await saveRes.json();

            if (saved.skipped) return currentData;

            // Lokales Objekt mit frischen Werten zurückgeben
            return {
                ...currentData,
                wind: { updated: new Date().toISOString(), current: wind, forecast: wind.forecast },
            };
        } catch (e) { return currentData; }
    }

    // ── Laden ──────────────────────────────────────────────────────

    async function loadWeather() {
        try {
            let data = await KCS.api('/weather/data.php');
            data     = await maybeRefreshWind(data);
            _lastData = data;
            updateHeader(data);
        } catch (e) { /* silent */ }
    }

    // ── Init ───────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', () => {
        loadWeather();

        // Header-Strip klickbar → Modal öffnen + befüllen
        const strip = document.getElementById('headerWeather');
        if (strip) {
            strip.style.cursor = 'pointer';
            strip.addEventListener('click', () => {
                if (_lastData) renderModal(_lastData);
                KCS.openModal('wetterModal');
            });
        }
    });

    document.addEventListener('kcs:poll', loadWeather);

})();
