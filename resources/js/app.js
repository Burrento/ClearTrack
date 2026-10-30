// ACR122U card UIDs arrive from tools/acr122u_bridge.py over Server-Sent Events.
const nfcInput = document.querySelector('[data-nfc]');

if (nfcInput) {
    const status = document.getElementById('nfc-status');
    const scans = new EventSource('http://127.0.0.1:8765/scans');

    scans.onopen = () => status && (status.textContent = 'Scanner connected');
    scans.onerror = () => status && (status.textContent = 'Scanner not connected - run tools/acr122u_bridge.py');
    scans.onmessage = (e) => {
        if (nfcInput.hasAttribute('data-nfc-submit')) {
            nfcInput.value = e.data;
            nfcInput.form.submit();
        } else {
            nfcInput.value = e.data.match(/.{1,2}/g).join(':'); // 04:A1:B2..., server strips the colons
        }
    };
}
