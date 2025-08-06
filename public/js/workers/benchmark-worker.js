self.onmessage = function (e) {
	const iterations = e.data.iterations || 1000000;
	for (let i = 0; i < iterations; i++) {
		Math.sqrt(i);
	}
	self.postMessage({ done: true });
};