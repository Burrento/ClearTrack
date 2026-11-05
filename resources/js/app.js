// ACR122U card UIDs arrive from tools/acr122u_bridge.py over Server-Sent Events.
const nfcInput = document.querySelector('[data-nfc]');

if (nfcInput) {
    const status = document.getElementById('nfc-status');
    const scans = new EventSource('http://127.0.0.1:8765/scans');
    const record = nfcInput.hasAttribute('data-nfc-submit') ? scanner(nfcInput.form) : null;

    // EventSource keeps retrying, so the status flips back to "on" once the bridge starts.
    const setStatus = (state, text) => status && (status.dataset.state = state, status.textContent = text);
    scans.onopen = () => setStatus('on', 'Card reader connected');
    scans.onerror = () => setStatus('off', 'Card reader offline. Start the reader program on this computer.');
    scans.onmessage = (e) => {
        if (record) {
            record(e.data);
        } else {
            nfcInput.value = e.data.match(/.{1,2}/g).join(':'); // 04:A1:B2..., server strips the colons
        }
    };
}

/**
 * Attendance scanner: posts each tap as JSON instead of reloading the page, so the reader stays
 * connected and quick taps aren't lost. Taps are sent one at a time, in the order they arrived.
 */
function scanner(form) {
    const token = form.querySelector('[name=_token]').value;
    const area = document.querySelector('[data-scan-area]');
    const idle = area.querySelector('[data-scan-idle]');
    const recent = document.querySelector('[data-recent]');
    const beep = sound(document.querySelector('[data-sound-toggle]'));
    let queue = Promise.resolve();
    let resetTimer;

    const line = (className, text) => Object.assign(document.createElement('p'), { className, textContent: text });

    // Full-width result, back to "Ready" after 4 s so an old result isn't mistaken for the next student's
    const show = (colour, label, title, detail, titleSize = 'text-[clamp(2.25rem,6vw,4rem)] leading-[1.05]') => {
        area.querySelectorAll('[data-scan-result]').forEach((el) => el.remove());
        const box = document.createElement('div');
        box.dataset.scanResult = '';
        box.className = `rounded-2xl px-6 py-10 text-white sm:px-10 sm:py-14 ${colour}`;
        box.append(line('text-lg font-semibold text-white/80', label), line(`mt-1 font-bold tracking-tight ${titleSize}`, title));
        if (detail) box.append(line('mt-3 text-white/80', detail));
        idle.before(box);
        idle.classList.add('hidden');
        clearTimeout(resetTimer);
        resetTimer = setTimeout(() => { box.remove(); idle.classList.remove('hidden'); }, 4000);
    };

    const fail = (message) => {
        beep('error');
        show('bg-alarm', 'Not recorded', message, null, 'text-[clamp(1.75rem,4.5vw,3rem)] leading-[1.1]');
    };

    const addRecent = ({ name, status, at }) => {
        const item = document.createElement('li');
        item.className = 'flex items-center justify-between gap-4 px-5 py-3';
        const right = Object.assign(document.createElement('span'), { className: 'flex items-center gap-3 text-sm text-ink-soft' });
        if (status === 'late') right.append(Object.assign(document.createElement('span'), { className: 'badge badge-wait', textContent: 'Late' }));
        right.append(at.replace(/:\d\d /, ' ')); // 9:41:07 AM → 9:41 AM, like the server-rendered rows
        item.append(Object.assign(document.createElement('span'), { className: 'font-semibold', textContent: name }), right);
        recent.prepend(item);
        recent.querySelectorAll('li:nth-child(n+9)').forEach((el) => el.remove());
        recent.classList.remove('hidden');
        document.querySelector('[data-recent-empty]')?.classList.add('hidden');
    };

    const send = async (uid) => {
        let response;
        let data = {};
        try {
            response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ rfid_uid: uid }),
            });
            data = await response.json().catch(() => ({}));
        } catch {
            return fail('No connection to ClearTrack. Check the internet, then tap again.');
        }

        if (response.status === 419) return fail('This page has expired. Refresh the page, then tap again.');
        if (!response.ok) return fail(data.message || 'Something went wrong. Tap again.');

        const late = data.status === 'late';
        beep(data.repeat ? 'repeat' : late ? 'late' : 'ok');
        show(
            late ? 'bg-wait' : 'bg-seal',
            data.repeat ? 'Already recorded' : late ? 'Late' : 'Present',
            data.name,
            data.repeat ? `${late ? 'Late' : 'Present'}, tapped at ${data.at}` : `Recorded at ${data.at}`,
        );
        if (!data.repeat) addRecent(data);
        for (const [key, total] of Object.entries(data.counts)) {
            document.querySelector(`[data-count="${key}"]`).textContent = total;
        }
    };

    return (uid) => { queue = queue.then(() => send(uid)); };
}

/**
 * Short beeps made in the browser (no sound files). Browsers block audio until the page is clicked,
 * so the toggle button both unlocks and switches the sound.
 */
function sound(toggle) {
    let audio = null;
    let on = false;
    // [frequency Hz, seconds] per note
    const tones = { ok: [[1047, 0.12]], late: [[784, 0.1], [784, 0.1]], repeat: [[1047, 0.05]], error: [[196, 0.4]] };

    toggle?.addEventListener('click', () => {
        audio ??= new AudioContext();
        audio.resume();
        on = !on;
        toggle.setAttribute('aria-pressed', on);
        toggle.textContent = on ? 'Beep is on' : 'Turn on beep';
        if (on) play('ok');
    });

    function play(kind) {
        if (!on || !audio) return;
        let t = audio.currentTime;
        for (const [frequency, length] of tones[kind]) {
            const osc = audio.createOscillator();
            const gain = audio.createGain();
            osc.type = kind === 'error' ? 'square' : 'sine';
            osc.frequency.value = frequency;
            gain.gain.setValueAtTime(0.3, t);
            gain.gain.exponentialRampToValueAtTime(0.001, t + length);
            osc.connect(gain).connect(audio.destination);
            osc.start(t);
            osc.stop(t + length);
            t += length + 0.07;
        }
    }

    return play;
}
