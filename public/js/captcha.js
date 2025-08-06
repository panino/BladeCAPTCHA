	async function initCaptcha(config) {
		// Defaults y destructuring
		config = {
			mode: 'manualHandling',
			onSuccess: null,
			onError: null,
			formSelector: '',
			inputName: 'captcha_token',
			statusSelector: '',
			verifyButtonSelector: '',
			submitButtonSelector: '',
			onLoading: null,
			cancelLoading: null,
			onProgress: null,
			manualHandlingAutoStartOnLoad: false,
			...config
		};
		
		// Selectores seguros (no llamar document.querySelector(null))
		const verifyButton = config.verifyButtonSelector ? document.querySelector(config.verifyButtonSelector) : null;
		const statusElement = config.statusSelector ? document.querySelector(config.statusSelector) : null;
		let inputToken = config.inputName ? document.querySelector(`[name="${config.inputName}"]`) : null;
		const form = config.formSelector ? document.querySelector(config.formSelector) : null;
		const submitButton = config.submitButtonSelector ? document.querySelector(config.submitButtonSelector) : null;
		// Estado
		let enProceso = false;
		let memo = null;
		let callbacksFired = {
			success: false,
			error: false
		};

		// Helpers
		function setStatus(msg, className = '') {
			if (statusElement) {
				statusElement.textContent = msg;
				statusElement.className = className;
			}
		}
		function getErrorMessage(err) {
			if (err instanceof Error && err.message) {
				return err.message;
			}
			if (typeof err === 'string') {
				return err;
			}
			try { 
				return JSON.stringify(err); 
			} catch { 
				return String(err); 
			}
		}

		// callOnce: garantiza single-shot para success/error
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
			}
		}

		// Fetch wrapper robusto
		async function fetchWithErrorHandling(url, options = {}) {
			try {
				const response = await fetch(url, options);
				if (!response.ok) {
					let errorData;
					try { 
						errorData = await response.json(); 
					}
					catch (jsonErr) {
						errorData = { message: await response.text().catch(()=>'') || `HTTP ${response.status}` };
					}
					throw new Error(errorData.message || `HTTP ${response.status}`);
				}
				const contentType = response.headers.get('content-type') || '';
				if (contentType.includes('application/json')) {
					return await response.json();
				}
				return await response.text();
			} catch (err) { 
				throw err; 
			}
		}

		// solvePoW (usa worker)
		function solvePoW(challenge, difficulty) {
			return new Promise(
				(resolve, reject) => {
					const worker = new Worker('../js/workers/pow-worker.js');
					const progressEnabled = typeof config.onProgress === 'function';
					const loguear = !!statusElement;
					try {
						worker.postMessage({ challenge, difficulty, loguear, progress: progressEnabled });
					} catch (e) {
						worker.terminate();
						return reject(e);
					}
					let settled = false; // evita doble resolve/reject
					worker.onmessage = (ev) => {
						const data = ev.data || {};
						if (data.error) {
							if (!settled) {
								settled = true;
								if (typeof config.onProgress === 'function') {
									config.onProgress(100);
								}
								reject(new Error(data.error));
								worker.terminate();
							}
							return;
						}
						if (data.log) {
							setStatus(data.log);
							return;
						}
						if (data.perc !== undefined) {
							if (typeof config.onProgress === 'function') {
								config.onProgress(Number(data.perc));
							}
							return;
						}
						if (data.nonce !== undefined) {
							if (!settled) {
								settled = true;
								if (typeof config.onProgress === 'function') {
									config.onProgress(100);
								}
								resolve(data.nonce);
								worker.terminate();
							}
						}
					};
					worker.onerror = (err) => {
						if (!settled) {
							settled = true;
							if (typeof config.onProgress === 'function') {
								config.onProgress(100);
							}
							reject(new Error('Error en worker: ' + (err?.message || err)));
							worker.terminate();
						}
					};
				}
			);
		}

		// handleVerification: devuelve { success, token, message }
		async function handleVerification() {
			const actionGet = 'GET_POW_CHALLENGE';
			setStatus('Obteniendo desafío...', 'loading');
			if (enProceso) {
				return { success: false, message: 'Otro proceso en curso' };
			}
			enProceso = true;
			callbacksFired = { success: false, error: false }; // reset por intento
			if (verifyButton) {
				verifyButton.disabled = true;
			}
			try {
				const { challenge, difficulty } = await fetchWithErrorHandling(
					'../php/captcha.php', 
					{
						method: 'POST',
						headers: { 'Accept': 'application/json' },
						body: JSON.stringify({ proceso: actionGet })
					}
				);
				setStatus('Resolviendo desafío...', 'loading');
				let nonce;
				try {
					nonce = await solvePoW(challenge, difficulty);
				} catch (err) {
					const msg = `Error: ${getErrorMessage(err)}`;
					setStatus(msg, 'error');
					callOnce('onError', err);
					return { success: false, message: msg };
				}
				setStatus('Enviando resultado...', 'loading');
				const validateAction = 'VALIDATE_POW_CHALLENGE';
				const result = await fetchWithErrorHandling(
					'../php/captcha.php',
					{
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ challenge, nonce, proceso: validateAction })
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
					const errObj = new Error(result.message || 'Validación fallida');
					callOnce('onError', errObj);
					return { success: false, message: result.message || 'Validación fallida' };
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
					const worker = new Worker('../js/workers/benchmark-worker.js');
					worker.postMessage({ iterations: target_iterations });
					worker.onmessage = (e) => {
						if (e.data && e.data.done) {
							resolve();
						} else {
							reject(new Error('Benchmark no finalizó correctamente'));
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
				'../php/captcha.php',
				{
					method: 'POST',
					headers: { 'Accept': 'application/json' },
					body: JSON.stringify({ proceso })
				}
			);
			try { 
				await benchmark(target_iterations); 
			} catch (err) { 
				console.log('Benchmark falló', err); 
			}
			// avisamos al servidor que terminó el benchmark (no necesitamos la respuesta)
			await fetchWithErrorHandling(
				'../php/captcha.php', 
				{
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token, proceso: 'verifyPerformanceChallenge' })
				}
			);
		}
		
		async function runVerificationSequence() {
			// protege doble click a nivel botón
			if (enProceso) {
				return;
			}
			// onLoading
			if (typeof config.onLoading === 'function') {
				try { 
					config.onLoading(); 
				} catch(e){
					console.error(e);
				}
			}
			// reset progress
			if (typeof config.onProgress === 'function') {
				try { 
					config.onProgress(0);
				} catch(e){
					console.error(e);
				}
			}
			await ejecutarBenchmarkYEnviar();
			await handleVerification();
			if (typeof config.cancelLoading === 'function') {
				try { 
					config.cancelLoading(); 
				} catch(e){
					console.error(e);
				}
			}
			if (typeof config.onProgress === 'function') {
				try { 
					config.onProgress(0);
				} catch(e){
					console.error(e);
				}
			}
		}

		/* === Listeners / modos === */
		if (config.mode === 'manualHandling') {
			if (!verifyButton && !config.manualHandlingAutoStartOnLoad) {
				console.warn(
					'⚠️ No se encontró "verifyButtonSelector" y "manualHandlingAutoStartOnLoad" está desactivado.\n' +
					'   La verificación no podrá iniciarse de forma manual ni automática.'
				);
			}
			if (verifyButton && config.manualHandlingAutoStartOnLoad) {
				console.info(
					'ℹ️ "manualHandlingAutoStartOnLoad" está activado y también existe un botón de verificación.\n' +
					'   Esto puede ser redundante: la verificación se ejecutará automáticamente al cargar la página, ' +
					'pero el botón seguirá visible para volver a ejecutarla si se desea.'
				);
			}
			document.addEventListener(
				'DOMContentLoaded', 
				async () => {
					if (verifyButton) {
						verifyButton.addEventListener(
							'click', 
							runVerificationSequence
						);
					} 
					if (config.manualHandlingAutoStartOnLoad) {
						await runVerificationSequence();
					}
				}
			);
		} else if (config.mode === 'autoFormIntegration') {
			if (!submitButton) {
				console.warn('No se encontró submitButtonSelector');
				return;
			}
			if (!form) {
				console.warn('No se encontró formSelector');
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
							if (typeof config.onLoading === 'function') {
								try { 
									config.onLoading();
								} catch(e){ 
									console.error(e); 
								}
							}
							if (typeof config.onProgress === 'function') {
								config.onProgress(0);
							}
							await ejecutarBenchmarkYEnviar();
							try {
								const res = await handleVerification();
								const token = res.token || (inputToken ? inputToken.value : '');
								const tokenOk = !!token && /^[a-f0-9]{32}$/i.test(token);
								if (res.success && tokenOk) {
									form.submit();
									return;
								}
								const message = res.message || 'No se pudo completar la verificación.';
								setStatus(message, 'error');
								callOnce('onError', new Error(message));
							} catch (err) {
								const msg = `Error: ${getErrorMessage(err)}`;
								setStatus(msg, 'error');
								callOnce('onError', err);
							} finally {
								enProceso = false;
								submitButton.disabled = false;
								if (typeof config.cancelLoading === 'function') {
									try { 
										config.cancelLoading(); 
									} catch(e){ 
										console.error(e); 
									}
								}
								if (typeof config.onProgress === 'function') {
									try { 
										config.onProgress(0);
									} catch(e){
										console.error(e);
									}
								}
							}
						}
					);
				}
			);
		}
	} // end initCaptcha
	
	