self.addEventListener('message', async (event) => {
    const { challenge, difficulty, loguear, progress, start = 0, end = Infinity, timeFactor = 1 } = event.data;

    const target = '0'.repeat(difficulty);
    const encoder = new TextEncoder();
    const sha256 = async (str) => {
        const data = encoder.encode(str);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        return Array.from(new Uint8Array(hashBuffer))
            .map(b => b.toString(16).padStart(2, '0'))
            .join('');
    };

    let nonce = start;
    const startTime = Date.now();
    const baseTimeLimit = 10000 + difficulty * 3000;
    const currentTimeLimit = baseTimeLimit * timeFactor;
    let lastPerc = 0;

    while (nonce < end) {
        const hash = await sha256(challenge + nonce);

        if (hash.startsWith(target)) {
            self.postMessage({ nonce });
            return;
        }

        nonce++;

        if (progress && nonce % 100 === 0) {
            let perc = ((nonce - start) / (end - start)) * 100;
            if (perc > 100) perc = 100;
            if (perc >= lastPerc) {
                lastPerc = perc;
                self.postMessage({ perc: perc.toFixed(2), start, end });
            }
        }

        if (Date.now() - startTime > currentTimeLimit) {
            self.postMessage({ done: true, partial: true, start, end });
            return;
        }

        if (loguear && nonce % 10000 === 0) {
            self.postMessage({ log: `Calculating... (${nonce - start} attempts)` });
        }
    }

    self.postMessage({ done: true, start, end });
});