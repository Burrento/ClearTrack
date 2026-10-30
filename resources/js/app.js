// ACR122U card UIDs arrive from tools/acr122u_bridge.py over Server-Sent Events.
const nfcInput = document.querySelector('[data-nfc]');

if (nfcInput) {
    const status = document.getElementById('nfc-status');
    const scans = new EventSource('http://127.0.0.1:8765/scans');

    scans.onopen = () => status && (status.textContent = 'Scanner connected');
    scans.onerror = () => status && (status.textContent = 'Scanner not connected - run tools/acr122u_bridge.py');
    scans.onmessage = (e) => {
        nfcInput.value = e.data;
        if (nfcInput.hasAttribute('data-nfc-submit')) nfcInput.form.submit();
    };
}
