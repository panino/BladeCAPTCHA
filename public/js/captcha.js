	async function initCaptcha(config) {
		// Defaults y destructuring
		config = {
			mode: 'manualHandling',
			onSuccess: null,
			onError: null,
			formSelector: '',
			inputName: null,
			statusSelector: '',
			verifyButtonSelector: '',
			submitButtonSelector: '',
			onStart: null,
			onEnd: null,
			onProgress: null,
			manualHandlingAutoStartOnLoad: false,
			apiBaseUrl: '../php',
			...config
		};
		
		// Validaciones estrictas para modo manualHandling
		if (config.mode === 'manualHandling') {
			if (config.formSelector || config.submitButtonSelector) {
				return Promise.reject(
					new Error(
						'Manual mode must not use formSelector nor submitButtonSelector'
					)
				);
			}
			if (config.inputName) {
				console.warn(
					'Manual mode does not use inputName for hidden token. This value will be ignored'
				);
			}
		 }
		
		// Obtener form si existe selector y modo es autoFormIntegration
		const form =
			config.mode === 'autoFormIntegration' && config.formSelector
			? document.querySelector(config.formSelector)
			: null;

		// Función auxiliar para seleccionar elemento con lógica robusta
		function selectElement(selector, form) {
			if (!selector) return null;
			if (form) {
				// Intentar selector global primero
				let elGlobal = document.querySelector(selector);
				if (elGlobal && elGlobal.closest && elGlobal.closest('form') === form) {
					return elGlobal;
				}
				// Sino buscar dentro del form
				return form.querySelector(selector);
			} else {
				// Sin form definido, buscar global
				return document.querySelector(selector);
			}
		}
		
		// Función auxiliar para generar una clave extra
		function generarClave() {
			// Crea un arreglo de 16 bytes aleatorios
			const array = new Uint8Array(16);
			crypto.getRandomValues(array);
			// Convierte a hex string
			return Array.from(array, b => b.toString(16).padStart(2, '0')).join('');
		}
		
		// Selección de elementos UI
		let inputToken = null;
		if (config.mode === 'autoFormIntegration' && config.inputName && form) {
			inputToken = form.querySelector(`[name="${config.inputName}"]`) || null;
		}
		const statusElement = selectElement(config.statusSelector, form);
		const verifyButton = selectElement(config.verifyButtonSelector, form);
		const submitButton = selectElement(config.submitButtonSelector, form);
		const claveCaptcha = generarClave();
		
		// Estado
		let enProceso = false;
		let callbacksFired = {
			success: false,
			error: false,
			cancel: false,
			load: false
		};
		let lastProgress = null;
		
		// Helper para llamar onProgress solo si el valor cambia
		function safeOnProgress(value) {
			if (typeof config.onProgress === 'function' && lastProgress !== value) {
				try {
					config.onProgress(value);
					lastProgress = value;
				} catch (e) {
					console.error('onProgress error', e);
				}
			}
		}
		// rutas
		const basePath = new URL('.', import.meta.url).href.replace(/\/$/, '');
		const apiUrl = (path) => `${config.apiBaseUrl.replace(/\/$/, '')}/${path}`;
		
		// Helpers
		function setStatus(msg, className = '') {
			if (statusElement) {
				statusElement.textContent = msg;
				statusElement.className = className;
			}
		}
		function getErrorMessage(err) {
			let msg;
			if (err instanceof Error && err.message) {
				msg = err.message;
			} else if (typeof err === 'string') {
				msg = err;
			} else {
				try { 
					msg = JSON.stringify(err); 
				} catch { 
					msg = String(err); 
				}
			}
			return msg.replace(/^\s*Error\s*:\s*/i, '');
		}

		// callOnce: garantiza single-shot 
		function callOnce(kind, ...args) {
			if (kind === 'onSuccess') {
				if (callbacksFired.success) {
					return;
				}
				callbacksFired.success = true;
				if (typeof config.onSuccess === 'function') {
					try { 
						config.onSuccess(...args); 
					} catch (e) { 
						console.error('onSuccess error', e);
					}
				}
			} else if (kind === 'onError') {
				if (callbacksFired.error) {
					return;
				}
				callbacksFired.error = true;
				if (typeof config.onError === 'function') {
					try { 
						config.onError(...args); 
					} catch (e) { 
						console.error('onError error', e); 
					}
				}
			} else if (kind === 'onEnd') { 
				if (callbacksFired.cancel) {
					return;
				}
				callbacksFired.cancel = true;
				if (typeof config.onEnd === 'function') {
					try { 
						config.onEnd(...args);
					} catch (e) { 
						console.error('onEnd error', e);						
					}
				}
			} else if (kind === 'onStart') { 
				if (callbacksFired.load) {
					return;
				}
				callbacksFired.load = true;
				if (typeof config.onStart === 'function') {
					try { 
						config.onStart(...args); 
					} catch (e) { 
						console.error('onStart error', e); 
					}
				}
			}
		}

		// Fetch wrapper robusto
		async function fetchWithErrorHandling(url, options = {}) {
			const response = await fetch(url, options);
			if (!response.ok) {
				let errorData;
				try {
					errorData = await response.json();
				} catch {
					errorData = { message: await response.text().catch(()=>'') || `HTTP ${response.status}` };
				}
				throw new Error(errorData.message || `HTTP ${response.status}`);
			}
			const contentType = response.headers.get('content-type') || '';
			return contentType.includes('application/json')
				? await response.json()
				: await response.text();
		}
		
		// usa workers en paralelo
		function solvePoW(challenge, difficulty) {
			const isMobile = matchMedia("(pointer: coarse)").matches;

			const baseSubRange = isMobile ? 5000 : 10000;
			const minSubRange = isMobile ? 2000 : 5000;
			const maxSubRange = isMobile ? 10000 : 20000;
			const workersCount = isMobile
				? Math.min(2, navigator.hardwareConcurrency || 1)
				: Math.min(4, navigator.hardwareConcurrency || 4);
			const globalMax = 500000; // límite absoluto de nonces
			const timeFactor = isMobile ? 2 : 1;

			let nextStart = 0;
			let totalNoncesTried = 0;
			let totalAttempts = 0;
			let nonceFound = false;
			let settled = false;

			const workers = [];
			const workerLastSize = Array(workersCount).fill(baseSubRange);
			const workerLastTime = Array(workersCount).fill(0);

			const progressEnabled = typeof config.onProgress === 'function';
			const loguear = !!statusElement;

			function assignNextRange(workerIndex) {
				if (nonceFound || nextStart >= globalMax) return;

				// Ajuste dinámico según rendimiento
				let lastTime = workerLastTime[workerIndex] || 0;
				let lastSize = workerLastSize[workerIndex] || baseSubRange;

				let newSize = lastSize;
				if (lastTime > 0) {
					const targetTime = 2000; // 2 segundos por sub-rango ideal
					newSize = Math.min(
						maxSubRange,
						Math.max(minSubRange, Math.floor(lastSize * (targetTime / lastTime)))
					);
				}

				const start = nextStart;
				const end = Math.min(nextStart + newSize, globalMax);
				nextStart = end;

				workerLastSize[workerIndex] = end - start;
				workerLastTime[workerIndex] = Date.now();

				workers[workerIndex].postMessage({
					challenge,
					difficulty,
					loguear,
					progress: progressEnabled,
					start,
					end,
					timeFactor
				});
			}

			return new Promise((resolve, reject) => {
				for (let i = 0; i < workersCount; i++) {
					const worker = new Worker(`${basePath}/workers/pow-worker.min.js`);
					workers.push(worker);

					worker.onmessage = (ev) => {
						const data = ev.data || {};

						// nonce encontrado → resolve + 100% progreso
						if (data.nonce !== undefined && !settled) {
							settled = true;
							nonceFound = true;
							safeOnProgress(100);
							resolve(data.nonce);
							workers.forEach(w => w.terminate());
							return;
						}

						// sub-rango terminado → reencolar
						if (data.done) {
							const now = Date.now();
							const elapsed = now - workerLastTime[i];
							workerLastTime[i] = elapsed;
							totalNoncesTried += (data.end - data.start || baseSubRange);

							if (!nonceFound) assignNextRange(i);
						}

						// progreso parcial
						if (data.perc !== undefined && !nonceFound) {
							const percGlobal = ((totalNoncesTried + ((data.end - data.start) * data.perc / 100 || 0)) / globalMax) * 100;
							safeOnProgress(Math.min(99.99, percGlobal.toFixed(2)));
						}

						if (data.attempts !== undefined && !nonceFound) {
							totalAttempts += data.attempts;
							setStatus(`Calculating… (${totalAttempts} attempts)`, 'info');
						}
					};

					worker.onerror = (err) => {
						if (!settled) {
							settled = true;
							safeOnProgress(100);
							reject(new Error('Worker error: ' + (err?.message || err)));
							workers.forEach(w => w.terminate());
						}
					};

					assignNextRange(i); // primer sub-rango
				}
			});
		}

		// handleVerification: devuelve { success, token, message }
		async function handleVerification() {
			const actionGet = 'GET_POW_CHALLENGE';
			setStatus('Retrieving challenge...', 'loading');
			if (enProceso) {
				return { success: false, message: 'Another process in progress' };
			}
			enProceso = true;
			callbacksFired = { success: false, error: false, cancel: false, load: false  }; // reset por intento
			if (verifyButton) {
				verifyButton.disabled = true;
			}
			try {
				const { challenge, difficulty } = await fetchWithErrorHandling(
					apiUrl('captcha.php'), 
					{
						method: 'POST',
						headers: { 'Accept': 'application/json' },
						body: JSON.stringify({ proceso: actionGet, claveCaptcha })
					}
				);
				setStatus('Solving challenge...', 'loading');
				let nonce;
				try {
					nonce = await solvePoW(challenge, difficulty);
				} catch (err) {
					const msg = `Error: ${getErrorMessage(err)}`;
					setStatus(msg, 'error');
					callOnce('onError', err);
					return { success: false, message: msg };
				}
				setStatus('Sending result...', 'loading');
				const validateAction = 'VALIDATE_POW_CHALLENGE';
				const result = await fetchWithErrorHandling(
					apiUrl('captcha.php'), 
					{
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ challenge, nonce, proceso: validateAction, claveCaptcha })
					}
				);
				// resultado del servidor esperado: { success: bool, message: '', token_validacion: '...' }
				setStatus(result.message || '', result.success ? 'success' : 'error');
				if (result.success && form) {
					if (!inputToken) {
						inputToken = document.createElement('input');
						inputToken.name = config.inputName;
						inputToken.type = 'hidden';
						form.appendChild(inputToken);
					}
					inputToken.value = result.token_validacion || '';
				}
				if (result.success) {
					callOnce('onSuccess', result.token_validacion || '');
					return { success: true, token: result.token_validacion || '', message: result.message || '' };
				} else {
					const errObj = new Error(result.message || 'Validation failed');
					callOnce('onError', errObj);
					return { success: false, message: result.message || 'Validation failed' };
				}
			} catch (err) {
				const msg = `Error: ${getErrorMessage(err)}`;
				setStatus(msg, 'error');
				callOnce('onError', err);
				return { success: false, message: msg };
			} finally {
				if (verifyButton) verifyButton.disabled = false;
				enProceso = false;
			}
		}

		// benchmark (usa worker)
		function benchmark(target_iterations = 1_000_000) {
			return new Promise(
				(resolve, reject) => {
					const worker = new Worker(`${basePath}/workers/benchmark-worker.min.js`);
					worker.postMessage({ iterations: target_iterations });
					worker.onmessage = (e) => {
						if (e.data && e.data.done) {
							resolve();
						} else {
							reject(new Error('Benchmark did not finish correctly'));
						}
						worker.terminate();
					};
					worker.onerror = (err) => { 
						reject(err);
						worker.terminate(); 
					};
				}
			);
		}

		// ejecutarBenchmarkYEnviar (sin respuesta)
		async function ejecutarBenchmarkYEnviar() {
			const proceso = 'getPerformanceChallenge';
			const { token, target_iterations } = await fetchWithErrorHandling(
				apiUrl('captcha.php'), 
				{
					method: 'POST',
					headers: { 'Accept': 'application/json' },
					body: JSON.stringify({ proceso, claveCaptcha })
				}
			);
			try { 
				await benchmark(target_iterations); 
			} catch (err) { 
				console.log('Benchmark failed', err); 
			}
			// avisamos al servidor que terminó el benchmark (no necesitamos la respuesta)
			await fetchWithErrorHandling(
				apiUrl('captcha.php'), 
				{
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token, proceso: 'verifyPerformanceChallenge', claveCaptcha })
				}
			);
		}
		
		async function runVerificationSequence(e) {
			e.preventDefault();
			if (enProceso) return;

			callOnce('onStart');
			lastProgress = null;
			safeOnProgress(0);

			try {
				await ejecutarBenchmarkYEnviar();
				await handleVerification();
			} catch (err) {
				setStatus(getErrorMessage(err), 'error');
				callOnce('onError', err);
			} finally {
				callOnce('onEnd');
				safeOnProgress(100);
				if (verifyButton) verifyButton.disabled = false;
				enProceso = false;
			}
		}

		/* === Listeners / modos === */
		if (config.mode === 'manualHandling') {
			if (!verifyButton && !config.manualHandlingAutoStartOnLoad) {
				console.warn(
					'⚠️ "verifyButtonSelector" was not found and "manualHandlingAutoStartOnLoad" is disabled.\n' +  
					'   Verification cannot be initiated manually or automatically'  
				);
			}
			if (verifyButton && config.manualHandlingAutoStartOnLoad) {
				console.info(
					'ℹ️ "manualHandlingAutoStartOnLoad" is enabled and a verify button also exists.\n' +
					'   This might be redundant: verification will run automatically when the page loads,\n' +
					'   but the button will remain visible to run it again if desired'
				);
			}
			document.addEventListener(
				'DOMContentLoaded', 
				async () => {
					if (verifyButton) {
						verifyButton.addEventListener(
							'click', 
							async (e) => {
								try {
									try {
										await runVerificationSequence(e);
									} finally {
										callOnce('onEnd');
									}
								} catch (err) {
									setStatus(getErrorMessage(err), 'error');
									callOnce('onError', err);
								}
							}
						);
					} 
					if (config.manualHandlingAutoStartOnLoad) {
						try {
							try {
								await runVerificationSequence();
							} finally {
								callOnce('onEnd');
							}
						} catch (err) {
							setStatus(getErrorMessage(err), 'error');
							callOnce('onError', err);
						}
					}
				}
			);
		} else if (config.mode === 'autoFormIntegration') {
			if (!submitButton) {
				console.warn('submitButtonSelector not found');
				return;
			}
			if (!form) {
				console.warn('formSelector not found');
				return;
			}
			document.addEventListener(
				'DOMContentLoaded',
				() => {
					submitButton.addEventListener(
						'click', 
						async (e) => {
							e.preventDefault();
							// Si ya está deshabilitado, no seguir
							if (submitButton.disabled || enProceso) return;
							// Bloquear ya mismo para prevenir doble click simultáneo
							submitButton.disabled = true;
							callOnce('onStart');
							lastProgress = null;
							safeOnProgress(0);
							try {
								try {
									callOnce('onStart');
									lastProgress = null;
									safeOnProgress(0);

									await ejecutarBenchmarkYEnviar();
									const res = await handleVerification();
									const token = res.token || (inputToken ? inputToken.value : '');
									const tokenOk = !!token && /^[a-f0-9]{32}$/i.test(token);
									if (res.success && tokenOk) {
										form.submit();
										return;
									}
									const message = res.message || 'Verification could not be completed';
									setStatus(message, 'error');
									callOnce('onError', new Error(message));
								} finally {
									callOnce('onEnd');
								}
							} catch (err) {
								const msg = `Error: ${getErrorMessage(err)}`;
								setStatus(msg, 'error');
								callOnce('onError', err);
							} finally {
								enProceso = false;
								submitButton.disabled = false;
								safeOnProgress(100);
							}
						}
					);
				}
			);
		}
	} // end initCaptcha
	
	export { initCaptcha };
	