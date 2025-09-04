# BladeCAPTCHA

BladeCAPTCHA es un sistema de verificación tipo CAPTCHA accesible, auto-hosteado y respetuoso con la privacidad, desarrollado en PHP y JavaScript.  
Permite dos modos de uso:
1. **Integración automática con formularios** (agrega el token oculto automáticamente).
2. **Verificación manual** (el desarrollador gestiona el flujo de validación).

---

## Características

- **Sin dependencias externas**: no requiere librerías de terceros ni servicios externos.
- **Privacidad**: no rastrea al usuario ni guarda información personal.
- **No utiliza cookies**: importante para cumplir con regulaciones de privacidad.
- **Accesible**: diseñado para ser usable con lectores de pantalla.
- **Auto-hosteado**: control total sobre el código y la configuración.
- **Protección adaptativa**: ajusta la ejecución del desafío según la capacidad y el rendimiento del dispositivo del usuario.

---

## Privacidad

BladeCaptcha está diseñado para minimizar la recolección de datos y facilitar el cumplimiento de regulaciones como el RGPD (UE) y CCPA (California):

- No utiliza cookies ni almacenamiento local persistente.
- No recopila direcciones IP ni huellas del navegador.
- Todo el procesamiento se realiza en su propio servidor (self-hosted).

> Nota: la conformidad final dependerá de su implementación específica.

---

## Estructura del proyecto

```
BladeCAPTCHA/
│
├── public/                   
│   ├── css/
│   │   └── captcha.css
│   ├── js/
│   │   ├── captcha.js
│   │   └── workers/
│   │       ├── pow-worker.js
│   │       └── benchmark-worker.js
│   ├── examples/
│   │   ├── form-integration.html
│   │   ├── manual-verification.html
│   │   └── dual-forms.html
│   ├── php/
│   │   ├── captcha.php
│   │   ├── captcha-lib.php
│   │   └── procesar-formulario.php
│   └── index.html
│
├── config/                    
│   └── config.php
│
├── .gitignore
├── README.md
└── LICENSE
```

---

## Instalación

1. Clonar el repositorio:
   ```bash
   git clone https://github.com/panino/BladeCAPTCHA.git
   ```
2. Renombrar `config/config.sample.php` a `config/config.php` y editar los valores:
   ```bash
   mv config/config.sample.php config/config.php
   nano config/config.php
   ```
3. Asegurarse de que PHP 8.0+ y las siguientes extensiones estén instaladas: `openssl`, `mbstring` y `json`.

---

## Uso

### Modo 1: Integración automática con formularios

Ejemplo: [`public/examples/form-integration.html`](public/examples/form-integration.html)

```js
import { initCaptcha } from './js/captcha.js';

(async () => {
  try {
    await initCaptcha({
      mode: 'autoFormIntegration',
      // otros parámetros...
    });
  } catch (err) {
    console.error(err || err.message);
  }
})();
```

### Modo 2: Manejo manual

Ejemplo: [`public/examples/manual-verification.html`](public/examples/manual-verification.html)

```js
import { initCaptcha } from './js/captcha.js';

(async () => {
  try {
    await initCaptcha({
      mode: 'manualHandling',
      // otros parámetros...
    });
  } catch (err) {
    console.error(err || err.message);
  }
})();
```

---

## Ajuste de la ejecución

BladeCAPTCHA optimiza dinámicamente los parámetros de ejecución de su proof-of-work (como el número de workers en paralelo o el tamaño de los lotes de cálculo) en función de las capacidades del hardware del dispositivo.

Esta optimización garantiza que dispositivos con menor potencia de procesamiento, como los móviles, completen la verificación con agilidad, mientras se mantiene un alto nivel de seguridad contra bots.

Todo el proceso de cálculo y ajuste se realiza localmente en el navegador. En ningún caso se envía información del hardware o métricas de rendimiento a servidores externos, garantizando así la plena privacidad del usuario.

---

## Requisitos

- Navegador con soporte para **Web Workers** y **JavaScript ES6+**.
- Servidor que ejecute PHP 8.0+ con extensiones: `openssl`, `mbstring` y `json`.

---

## 🌐 Documentación y ejemplos en vivo

- 📚 **Documentación completa (ES)**: [https://bladecaptcha.com.ar/documentacion.html](https://bladecaptcha.com.ar/documentacion.html)  
- 📚 **Documentación completa (EN)**: [https://bladecaptcha.com.ar/en/documentacion.html](https://bladecaptcha.com.ar/en/documentacion.html)  

**Ejemplos interactivos**:
- 🔹 **Integración automática**: [form-integration.html (ES)](https://bladecaptcha.com.ar/BladeCAPTCHA/public/examples/form-integration.html) | [EN](https://bladecaptcha.com.ar/BladeCAPTCHA/public/examples/form-integration-en.html)  
- 🔹 **Manejo manual**: [manual-verification.html (ES)](https://bladecaptcha.com.ar/BladeCAPTCHA/public/examples/manual-verification.html) | [EN](https://bladecaptcha.com.ar/BladeCAPTCHA/public/examples/manual-verification-en.html)  
- 🔹 **Integración automática con múltiples formularios**: [dual-forms.html (ES)](https://bladecaptcha.com.ar/BladeCAPTCHA/public/examples/dual-forms.html) | [EN](https://bladecaptcha.com.ar/BladeCAPTCHA/public/examples/dual-forms-en.html)

---

## Licencia

Este proyecto está licenciado bajo la licencia MIT. Consulta el archivo [LICENSE](LICENSE) para más información.

---

## Créditos

La fuente de emoji utilizada en los ejemplos es **Twemoji**, cortesía de Twitter.  
La fuente específica proviene del proyecto **twemoji-colr** de Mozilla.  
Ambos proyectos están bajo licencia **CC-BY-4.0**.

- Twemoji: [https://github.com/twitter/twemoji](https://github.com/twitter/twemoji)  
- twemoji-colr: [https://github.com/mozilla/twemoji-colr](https://github.com/mozilla/twemoji-colr)
