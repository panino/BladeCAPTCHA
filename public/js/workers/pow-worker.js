self.addEventListener(
	'message', 
	async (event) => {
		const { challenge, difficulty, loguear, progress } = event.data;
		const target = '0'.repeat(difficulty);
		let nonce = 0;
		const encoder = new TextEncoder();
		const sha256 = async (str) => {
			const data = encoder.encode(str);
			const hashBuffer = await crypto.subtle.digest('SHA-256', data);
			return Array.from(new Uint8Array(hashBuffer))
			.map(b => b.toString(16).padStart(2, '0'))
			.join('');
		};
		const start = Date.now();
		const maxTime = 10000 + difficulty * 3600;
		while (true) {
			const hash = await sha256(challenge + nonce);
			if (hash.startsWith(target)) {
				self.postMessage({ nonce });
				return;
			}
			nonce++;
			if (loguear && nonce % 10000 === 0) {
				self.postMessage({ log : `Calculando... (${nonce} intentos)`});
			}
			if (progress && nonce % 100 === 0) {
				self.postMessage({ perc: ((Date.now() - start) * 100 / maxTime).toFixed(2) });
			}
			if (Date.now() - start > maxTime) {
				self.postMessage({ error: 'Tiempo de cálculo excedido' });
				return;
			}
		}
	}
);