self.addEventListener('message', async (event) => {
	const { challenge, difficulty, loguear, progress } = event.data;
	const target = '0'.repeat(difficulty);
	const encoder = new TextEncoder();
	const sha256 = async (str) => {
		const data = encoder.encode(str);
		const hashBuffer = await crypto.subtle.digest('SHA-256', data);
		return Array.from(new Uint8Array(hashBuffer))
			.map(b => b.toString(16).padStart(2, '0'))
			.join('');
	};
	let nonce = 0;
	const startTime = Date.now();
	const baseTimeLimit = 12000 + difficulty * 3800;
	const estimatedMaxIterations = 120000;
	let estimatedTimeToComplete = baseTimeLimit;
	let currentTimeLimit = baseTimeLimit;
	let lastPerc = 0;
	while (true) {
		const hash = await sha256(challenge + nonce);
		if (hash.startsWith(target)) {
			self.postMessage({ nonce });
			return;
		}
		nonce++;
		if (loguear && nonce % 10000 === 0) {
			const elapsed = Date.now() - startTime;
			estimatedTimeToComplete = elapsed * estimatedMaxIterations / nonce;
			currentTimeLimit = Math.max(baseTimeLimit, estimatedTimeToComplete);
			self.postMessage({
				log: `Calculando... (${nonce} intentos)`
			});
		}
		if (progress && nonce % 100 === 0) {
			const elapsed = Date.now() - startTime;
			let perc = (elapsed * 100) / currentTimeLimit;
			if (perc > 100) perc = 100;
			if (perc >= lastPerc) {
				lastPerc = perc;
				self.postMessage({ perc: perc.toFixed(2) });
			}
		}

		if (Date.now() - startTime > currentTimeLimit) {
			self.postMessage({ error: 'Tiempo de cálculo excedido' });
			return;
		}
	}
});