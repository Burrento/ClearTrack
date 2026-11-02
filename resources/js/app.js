// ACR122U card UIDs arrive from tools/acr122u_bridge.py over Server-Sent Events.
const nfcInput = document.querySelector('[data-nfc]');

if (nfcInput) {
    const status = document.getElementById('nfc-status');
    const scans = new EventSource('http://127.0.0.1:8765/scans');

    // EventSource keeps retrying, so the status flips back to "on" once the bridge starts.
    const setStatus = (state, text) => status && (status.dataset.state = state, status.textContent = text);
    scans.onopen = () => setStatus('on', 'Card reader connected');
    scans.onerror = () => setStatus('off', 'Card reader offline. Start the reader program on this computer.');
    scans.onmessage = (e) => {
        if (nfcInput.hasAttribute('data-nfc-submit')) {
            nfcInput.value = e.data;
            nfcInput.form.submit();
        } else {
            nfcInput.value = e.data.match(/.{1,2}/g).join(':'); // 04:A1:B2..., server strips the colons
        }
    };
}
